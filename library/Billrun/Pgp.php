<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing pgp encrypt and decrypt
 * Based on Gnupg
 *
 * @package  Billing
 * @since    2.7
 */
class Billrun_Pgp {

	/**
	 * the pgp instances for bridged singletones
	 * 
	 * @var array
	 */
	protected static $instances = array();
	
	/**
	 * config
	 * 
	 * @var array
	 */
	protected static $config = array();

	/**
	 * gnupg resource
	 * @var resource
	 */
	protected $res = '';

	/**
	 * Class constructor.
	 *
	 * @param 
	 * @return void
	 */
	public function __construct($config) {
		$this->res = gnupg_init();
		$this->config = $config;
		$key_conf = 'private_key';
		if (isset($this->config['encrypt_type']) && 
		strtolower($this->config['encrypt_type']) === 'response') {
			$key_conf = 'response_public_key';
			$this->config['passphrase'] = '';
		}
		
		if (!isset($this->config['passphrase'])) {
			$this->config['passphrase'] = '';
		}
		
		if (isset($this->config[$key_conf])) {
			$this->setPrivateKey($this->config[$key_conf], $this->config['passphrase']);
		}
	}
	
	/**
	 * Set passphrase and fingerprint for ecnryption and decryption
	 *
	 * @param  string   $private_key_path   private key path
	 * @param  string   $passphrase			passphrase
	 * @return void
	 */
	public function setPrivateKey($private_key_path, $passphrase = '') {
		$key_data = file_get_contents($private_key_path);
		$info = gnupg_import($this->res, $key_data);
		if ($passphrase !== '') {
			gnupg_adddecryptkey($this->res, $info['fingerprint'], $passphrase);
		}
		gnupg_addencryptkey($this->res, $info['fingerprint']);
	}

	/**
	 * For using single instance of same encryption
	 *
	 * @param 
	 * @return class instance
	 */
	public static function getInstance(array $options = array()) {
		$stamp = Billrun_Util::generateArrayStamp($options);
		if (!isset(self::$instances[$stamp])) {
			self::$instances[$stamp] = new self($options);
		}

		return self::$instances[$stamp];
	}

	/**
	 * Decrypts a text
	 *
	 * @param  string   $text   text to decrypt
	 * @return string	decrypted text if success, false otherwise
	 */
	public function decrypt($text) {
		return gnupg_decrypt($this->res, $text);
	}
	
	/**
	 * Decrypts a file and saved it.
	 *
	 * @param  string   $file_path				file to decrypt
	 * @param  string   $decrypted_file_path	decrypted file location
	 * @return true if success, false otherwise
	 */
	public function decrypt_file($file_path, $decrypted_file_path) {
		$file_content = file_get_contents($file_path);
		$decrypted_content = $this->decrypt($file_content);
		
		if (!$decrypted_content) {
			Billrun_Factory::log()->log('failed decrypting file:' . $file_path, Zend_Log::ALERT);
			return;
		}
		
		return (bool) file_put_contents($decrypted_file_path, $decrypted_content);
	}
	
	/**
	 * Encrypts a text
	 *
	 * @param  string   $text   text to encrypt
	 * @return string	encrypted text if success, false otherwise
	 */
	public function encrypt($text) {
		return gnupg_encrypt($this->res, $text);
	}
	
	/**
	 * Decrypts a file and saved it.
	 *
	 * @param  string   $file_path				file to decrypt
	 * @param  string   $encrypted_file_path	encrypted file location
	 * @return true if success, false otherwise
	 */
	public function encrypt_file($file_path, $encrypted_file_path) {
		$file_content = file_get_contents($file_path);
		$encrypted_content = $this->encrypt($file_content);
		
		if (!$encrypted_content) {
			Billrun_Factory::log()->log('failed encrypting file:' . $file_path, Zend_Log::ALERT);
			return;
		}
		
		return (bool) file_put_contents($encrypted_file_path, $encrypted_content);
	}
}
