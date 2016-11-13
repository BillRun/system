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
	 * Calculate the 'signature' using the hmac method and add it to the data
	 * under a new field name '_sig_',
	 * This function also adds a timestamp under the field 't'.
	 * @param array $data - Data to sign
 	 * @param array $key - Key to sign with
	 * @return array Output data with signature.
	 */
	public static function addSignature(array $data, $key) {
		// Add the timestamp
		$data['t'] = date(Billrun_Base::base_datetimeformat);
		$signature = self::sign($data, $key);
		$data['_sig_'] = $signature;
		return $data;
	}
	
	/**
	 * Validates the input data.
	 * @param array $request
	 * @return data - Request data if validated, null if error.
	 */
	public static function validateData(array $request) {
		// Validate the signature and the timestamp.
		if(!isset($request['_sig_'], $request['t'])) {
			return false;
		}
		
		$signature = $request['_sig_'];

		// Get the secret
		$secret = Billrun_Factory::config()->getConfigValue("shared_secret.key");
		if(!self::validateSecret($secret)) {
			return null;
		}
		
		$data = $request;
		unset($data['_sig_']);
		unset($data['t']);
		$hashResult = $this->sign($data, $secret);
		
		// state whether signature is okay or not
		$validData = null;
	
		if(hash_equals($signature, $hashResult)) {
			$validData = $data;
		}
		return $validData;
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
		if(empty($secret) || !is_string($secret)) {
			return false;
		}
		$crc = Billrun_Factory::config()->getConfigValue("shared_secret.crc");
		$calculatedCrc = hash("crc32b", $secret);
		
		// Validate checksum
		return hash_equals($crc, $calculatedCrc);
	}
}
