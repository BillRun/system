<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Reversible (2-way) application-level field encryption.
 *
 * Used by the billapi "encrypted" field type to encrypt sensitive PII before
 * it is written to MongoDB (encrypted at rest) and decrypt it when it is
 * fetched.
 *
 * The scheme is DETERMINISTIC and authenticated (SIV-style, RFC 5297 in
 * spirit): a synthetic IV is derived as an HMAC of the plaintext, then the
 * plaintext is encrypted with AES-256-CTR using that IV. The same plaintext
 * therefore always yields the same ciphertext, which lets an encrypted field
 * be queried by EXACT MATCH (encrypt the search term and compare ciphertext)
 * and keeps uniqueness working - at the cost of leaking equality (an observer
 * of the DB can tell which records share a value). On decrypt the synthetic IV
 * is recomputed from the recovered plaintext and compared, which authenticates
 * the value (tamper/wrong-key detection).
 *
 * Stored format: ENC_PREFIX . base64(siv|ciphertext)
 * The self-describing prefix lets any consumer detect ciphertext via
 * isEncrypted() and makes the scheme versionable.
 *
 * Two independent subkeys are derived from the configured master key: one for
 * the CTR cipher and one for the synthetic-IV HMAC.
 *
 * SCOPE: this is for moderately sensitive PII (national id, IBAN, etc.).
 * It is NOT a PCI-DSS control for cardholder data - card PAN/CVV have separate,
 * stricter requirements and continue to use gateway tokenization.
 *
 * @package  Util
 */
class Billrun_Utils_Encryption {

	/**
	 * Self-describing, versioned marker prepended to every encrypted value.
	 */
	const ENC_PREFIX = 'enc:v1:';

	/**
	 * Config path of the DB-stored encryption key (config collection, editable
	 * via the admin config), used as a fallback when no env key is set.
	 */
	const CONFIG_KEY_PATH = 'encryption.key';

	/**
	 * Cipher: AES-256 in CTR mode (keystream cipher; the SIV is its counter/IV).
	 */
	const CIPHER = 'aes-256-ctr';

	/**
	 * Synthetic IV length in bytes (also the AES block / CTR counter size).
	 */
	const SIV_LENGTH = 16;

	/**
	 * Cached 32-byte raw master key.
	 * @var string|null
	 */
	protected static $cachedKey = null;

	/**
	 * Encrypt a value for storage at rest (deterministic).
	 *
	 * No-ops on null/empty (absence is preserved) and is idempotent - an
	 * already-encrypted value is returned unchanged. Fails closed: if the key is
	 * missing or encryption fails, an exception is thrown so plaintext is never
	 * silently persisted.
	 *
	 * @param mixed $plaintext
	 * @return mixed the ENC_PREFIX-marked ciphertext string, or the input unchanged
	 * @throws Billrun_Exceptions_Api
	 */
	public static function encryptValue($plaintext) {
		if (is_null($plaintext) || $plaintext === '') {
			return $plaintext;
		}
		if (self::isEncrypted($plaintext)) {
			return $plaintext;
		}

		$keys = self::getSubKeys();
		if (empty($keys)) {
			Billrun_Factory::log('Field encryption failed: no encryption key configured', Zend_Log::ERR);
			throw new Billrun_Exceptions_Api(0, array(), 'Field encryption is not configured');
		}

		$plaintext = (string) $plaintext;
		$siv = substr(hash_hmac('sha256', $plaintext, $keys['mac'], true), 0, self::SIV_LENGTH);
		$ciphertext = openssl_encrypt($plaintext, self::CIPHER, $keys['enc'], OPENSSL_RAW_DATA, $siv);
		if ($ciphertext === false) {
			Billrun_Factory::log('Field encryption failed: openssl_encrypt returned false', Zend_Log::ERR);
			throw new Billrun_Exceptions_Api(0, array(), 'Field encryption failed');
		}

		return self::ENC_PREFIX . base64_encode($siv . $ciphertext);
	}

	/**
	 * Decrypt a value previously produced by encryptValue().
	 *
	 * Tolerant on read: anything that is not our ciphertext (plaintext, legacy
	 * or not-yet-migrated values) is returned unchanged. Fails open: if
	 * decryption or authentication fails (wrong/rotated key, tampering, missing
	 * key) the input is returned unchanged and a warning is logged, so a single
	 * bad field never breaks a whole fetch.
	 *
	 * @param mixed $stored
	 * @return mixed the decrypted plaintext, or the input unchanged
	 */
	public static function decryptValue($stored) {
		if (!self::isEncrypted($stored)) {
			return $stored;
		}

		$keys = self::getSubKeys();
		if (empty($keys)) {
			Billrun_Factory::log('Field decryption skipped: no encryption key configured', Zend_Log::WARN);
			return $stored;
		}

		$raw = base64_decode(substr($stored, strlen(self::ENC_PREFIX)), true);
		if ($raw === false || strlen($raw) <= self::SIV_LENGTH) {
			Billrun_Factory::log('Field decryption failed: malformed encrypted value', Zend_Log::WARN);
			return $stored;
		}

		$siv = substr($raw, 0, self::SIV_LENGTH);
		$ciphertext = substr($raw, self::SIV_LENGTH);

		$plaintext = openssl_decrypt($ciphertext, self::CIPHER, $keys['enc'], OPENSSL_RAW_DATA, $siv);
		if ($plaintext === false) {
			Billrun_Factory::log('Field decryption failed: openssl_decrypt returned false', Zend_Log::WARN);
			return $stored;
		}

		// authenticate: the synthetic IV must match the HMAC of the recovered plaintext
		$expectedSiv = substr(hash_hmac('sha256', $plaintext, $keys['mac'], true), 0, self::SIV_LENGTH);
		if (!hash_equals($expectedSiv, $siv)) {
			Billrun_Factory::log('Field decryption failed: authentication mismatch (wrong key or tampered data)', Zend_Log::WARN);
			return $stored;
		}

		return $plaintext;
	}

	/**
	 * Whether a value is one of our encrypted strings.
	 *
	 * @param mixed $value
	 * @return boolean
	 */
	public static function isEncrypted($value) {
		return is_string($value) && strncmp($value, self::ENC_PREFIX, strlen(self::ENC_PREFIX)) === 0;
	}

	/**
	 * Derive the cipher and MAC subkeys from the configured master key.
	 *
	 * @return array|null ['enc' => 32 bytes, 'mac' => 32 bytes], or null if no key
	 */
	protected static function getSubKeys() {
		$master = self::getKey();
		if (empty($master)) {
			return null;
		}
		return array(
			'enc' => hash_hmac('sha256', 'billrun-field-enc:v1', $master, true),
			'mac' => hash_hmac('sha256', 'billrun-field-siv:v1', $master, true),
		);
	}

	/**
	 * Resolve and cache the 32-byte raw master key.
	 *
	 * Resolution order (mirrors the BR_MDB_* env precedence in Billrun_Config):
	 *   1. BR_ENCRYPTION_KEY_FILE - path to a file holding the key (preferred for prod)
	 *   2. BR_ENCRYPTION_KEY      - the key as an env value
	 *   3. DB config 'encryption.key' (config collection, merged by loadDbConfig)
	 *
	 * Note: the env/file sources keep the key OUT of the database, which is the
	 * stronger posture for at-rest encryption (a DB dump alone is useless). The
	 * DB-config fallback is a convenience; if used, the key lives next to the
	 * data it protects.
	 *
	 * @return string|null 32 raw bytes, or null if no key is configured
	 */
	protected static function getKey() {
		if (!is_null(self::$cachedKey)) {
			return self::$cachedKey ?: null;
		}

		$rawKey = null;
		if (!empty($keyFile = getenv('BR_ENCRYPTION_KEY_FILE')) && is_readable($keyFile)) {
			$rawKey = trim((string) file_get_contents($keyFile));
		} else if (!empty($envKey = getenv('BR_ENCRYPTION_KEY'))) {
			$rawKey = $envKey;
		} else {
			// DB-stored config key (config collection is merged into the runtime config)
			$rawKey = Billrun_Factory::config()->getConfigValue(self::CONFIG_KEY_PATH, null);
		}

		self::$cachedKey = empty($rawKey) ? '' : self::normalizeKey($rawKey);
		return self::$cachedKey ?: null;
	}

	/**
	 * Normalize a configured key string to exactly 32 raw bytes.
	 *
	 * Accepts a 64-char hex string, a base64 string decoding to 32 bytes, or a
	 * raw 32-byte string. Any other value is hashed with SHA-256 (a documented
	 * convenience so an arbitrary passphrase still works); operators SHOULD
	 * provide a proper 32-byte key.
	 *
	 * @param string $key
	 * @return string 32 raw bytes
	 */
	protected static function normalizeKey($key) {
		if (strlen($key) === 64 && ctype_xdigit($key)) {
			return hex2bin($key);
		}
		$decoded = base64_decode($key, true);
		if ($decoded !== false && strlen($decoded) === 32) {
			return $decoded;
		}
		if (strlen($key) === 32) {
			return $key;
		}
		Billrun_Factory::log('Encryption key is not a 32-byte hex/base64/raw value; deriving it via SHA-256. Provide a proper 32-byte key.', Zend_Log::WARN);
		return hash('sha256', $key, true);
	}

	/**
	 * Generate a fresh key suitable for BR_ENCRYPTION_KEY (64-char hex = 32 bytes).
	 * Ops helper, parallels Billrun_Utils_Security::generateSecretKey().
	 *
	 * @return string
	 */
	public static function generateKey() {
		return bin2hex(random_bytes(32));
	}

	/**
	 * Whether an encryption key is supplied via the environment (file or var).
	 * When true, that key is the source of truth and is kept OUT of the DB.
	 *
	 * @return boolean
	 */
	public static function hasEnvKey() {
		return !empty(getenv('BR_ENCRYPTION_KEY_FILE')) || !empty(getenv('BR_ENCRYPTION_KEY'));
	}

	/**
	 * Ensure the given config document carries an encryption key, generating one
	 * if absent. Used by tenant creation (dbinit) and the migration so every
	 * environment ends up with a key.
	 *
	 * No-op when:
	 *  - a key is provided via env/file (that takes precedence and is kept out of
	 *    the DB), or
	 *  - the config already holds a key.
	 *
	 * @param array $config config document (by reference)
	 * @return boolean true if a new key was generated and written into $config
	 */
	public static function ensureConfigKey(array &$config) {
		if (self::hasEnvKey()) {
			return false;
		}
		if (!empty(Billrun_Util::getIn($config, self::CONFIG_KEY_PATH, null))) {
			return false;
		}
		Billrun_Util::setIn($config, self::CONFIG_KEY_PATH, self::generateKey());
		return true;
	}

}
