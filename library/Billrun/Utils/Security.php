<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Static functions extanding security functionality
 *
 */
class Billrun_Utils_Security {

	/**
	 * The name of the timestamp field in the input request.
	 */
	const TIMESTAMP_FIELD = '_t_';
	
	/**
	 * The name of the signature field in the input request.
	 */
	const SIGNATURE_FIELD = '_sig_';
	
	/**
	 * Calculate the 'signature' using the hmac method and add it to the data
	 * under a new field name '_sig_',
	 * This function also adds a timestamp under the field 't'.
	 * @param array $data - Data to sign
 	 * @param array $key - Key to sign with
	 * @return array Output data with signature.
	 */
	public static function addSignature(array $data, $key) {
		// Add the timestamp
		$data[self::TIMESTAMP_FIELD] = (string) time(); // we convert to string because it will be send in http which is string based
		$signature = self::sign($data, $key);
		$data[self::SIGNATURE_FIELD] = $signature;
		return $data;
	}
	
	/**
	 * Validates the input data.
	 * 
	 * @param array $request
	 * @param bool $throwException if true method will throw auth exception, else will return value accordingly
	 * 
	 * @return data - Request data if validated, null if error.
	 */
	public static function validateData(array $request, $throwException = false) {
		// first let's check with oauth2
		if (self::validateOauth($throwException)) {
			return true;
		}
		// Validate the signature and the timestamp.
		if(!isset($request[self::SIGNATURE_FIELD], $request[self::TIMESTAMP_FIELD])) {
			return false;
		}
		
		$signature = $request[self::SIGNATURE_FIELD];

		// Get the secret
		$secrets = Billrun_Factory::config()->getConfigValue("shared_secret");
		if(!is_array(current($secrets))) {  //for backward compatibility 
			$secrets = array($secrets);
		}
		$today = time();
		foreach ($secrets as $secret) {
			if (isset($secret['from']) && isset($secret['to']) && !($secret['from']->sec <= $today && $secret['to']->sec > $today)) {
				continue;
			}
			if (!self::validateSecret($secret)) {
				continue;
			}

			$data = $request;
			unset($data[self::SIGNATURE_FIELD]);
			$hashResult = self::sign($data, $secret['key']);

			if (hash_equals($signature, $hashResult)) {
				return true;
			}
		}
		if ($throwException) {
			throw new Billrun_Exceptions_Auth(40002, array(), 'Invalid Signature');
		}
		return false;
	}
	
	/**
	 * oauth2 authentication including validation
	 * 
	 * @param bool $throwException if required to throw exception when oauth rejected
	 * 
	 * @return bool true if oauth2 authentication passed
	 * @throws Billrun_Exceptions_Auth
	 */
	protected static function validateOauth($throwException = false) {
		$oauth = Billrun_Factory::oauth2();
		$oauthRequest = OAuth2\Request::createFromGlobals();
		$oauth->getResourceController();
		$oauthToken = $oauth->getTokenType();
		if (!$oauthToken->requestHasToken($oauthRequest)) {
			return false;
		}
		if ($oauth->verifyResourceRequest($oauthRequest, null, 'global')) {
			return true;
		} 
		if ($throwException) {
			throw new Billrun_Exceptions_Auth(40001, array(), 'Invalid Token');
		}
		return false;
	}
	
	/**
	 * Sign data with a secret
	 * @param array $data
	 * @param array $secret
	 * @return array Signature
	 */
	protected static function sign(array $data, $secret) {
		$stringData = json_encode($data);
		return hash_hmac("sha512", $stringData, $secret);
	}
	
	/**
	 * Validate a secret's type and crc
	 * @param array $secret - Input secret value to validate.
	 * @return boolean - True if the secret is valid.
	 */
	protected static function validateSecret($secret) {
		if(empty($secret['key']) || !is_string($secret['key'])) {
			return false;
		}
		$crc = $secret['crc'];
		$calculatedCrc = hash("crc32b", $secret['key']);
		
		// Validate checksum
		return hash_equals($crc, $calculatedCrc);
	}
	
	public static function generateSecretKey() {
		$key = bin2hex(openssl_random_pseudo_bytes(16));
		$crc = hash("crc32b", $key);
		return array('key' => $key, 'crc' => $crc);
	}
	
	public static function getValidSharedKey() {
		$secrets = Billrun_Factory::config()->getConfigValue("shared_secret");
		if (!is_array(current($secrets))) {  //for backward compatibility 
			$secrets = array($secrets);
		}
		$today = time();
		foreach ($secrets as $shared) {
			if (!isset($shared['from']) && !isset($shared['to'])) {  //for backward compatibility 
				$secret = $shared;
				break;
			}
			if ($shared['from']->sec < $today && $shared['to']->sec > $today) {
				$secret = $shared;
				break;
			}
		}
		return $secret;
	}

	/**
	 * validate password strength
	 * 
	 * @param string $password
	 * @param array $args arguments for validation (length = 8, upper = true, lower = true, number = true, special = true)
	 * 
	 * @return boolean true if password is strong enough else return the strength issue (-1 length, -2 upper, -3 lower, -4 number, -5 special)
	 */
	public static function validatePasswordStrength($password, $args) {
		$length = $args['length'] ? (int) $args['length'] : 8;
		if (mb_strlen($password) < $length) {
			return -1;
		}

		if (($args['upper'] ?? true) && !preg_match('@[A-Z]@', $password) === false) {
			return -2;
		}

		if (($args['lower'] ?? true) && !preg_match('@[a-z]@', $password)) {
			return -3;
		}

		if (($args['number'] ?? true) && !preg_match('@[0-9]@', $password)) {
			return -4;
		}

		if (($args['special'] ?? true) && !preg_match('@[^\w]@', $password)) {
			return -5;
		}

		return true;
	}

}
