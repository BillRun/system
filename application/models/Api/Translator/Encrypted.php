<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Encrypted type translator.
 *
 * Handles billapi fields declared with type=encrypted: the value is coerced to
 * string (like a plain string field) and then encrypted via
 * Billrun_Utils_Encryption before being written. The matching decryption
 * happens on fetch in Models_Action_Get::processResults().
 *
 * Because the encryption is deterministic, the SAME translator also works on
 * the query path: encrypting the search term reproduces the stored ciphertext,
 * so an exact-match equality query just works.
 *
 * @package  Api
 */
class Api_Translator_EncryptedModel extends Api_Translator_StringModel {

	/**
	 * @param mixed $data - Input data
	 * @return mixed Encrypted value (or the input unchanged for empty/already-encrypted).
	 */
	public function internalTranslateField($data) {
		if (is_null($data) || $data === '') {
			return $data;
		}
		if (Billrun_Utils_Encryption::isEncrypted($data)) {
			return $data;
		}
		return Billrun_Utils_Encryption::encryptValue(parent::internalTranslateField($data));
	}
}
