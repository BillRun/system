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
 * Query values can also arrive as a Mongo operator expression, e.g.
 * {"$regex": "123", "$options": "i"} - the shape the generic grid search box
 * sends for every text filter. Left to the parent StringModel, strval() on
 * that array collapses to the literal string "Array", which then gets
 * encrypted and compared - a query that can never match real data. Ciphertext
 * has no notion of substring or case, so a $regex "contains" search can't be
 * honored as such; it is downgraded to an exact-match query on the typed term
 * (case-sensitive - $options is necessarily ignored), which at least finds the
 * record when the full value is typed.
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
		if (is_array($data)) {
			return $this->translateQueryOperators($data);
		}
		if (Billrun_Utils_Encryption::isEncrypted($data)) {
			return $data;
		}
		return Billrun_Utils_Encryption::encryptValue(parent::internalTranslateField($data));
	}

	/**
	 * Translate a Mongo query-operator expression (e.g. $regex, $eq, $in)
	 * against an encrypted field. Only exact-match operators can be honored
	 * on ciphertext; anything else is passed through unchanged.
	 *
	 * @param array $data
	 * @return mixed
	 */
	protected function translateQueryOperators(array $data) {
		if (isset($data['$regex'])) {
			return Billrun_Utils_Encryption::encryptValue((string) $data['$regex']);
		}
		if (isset($data['$eq'])) {
			$data['$eq'] = Billrun_Utils_Encryption::encryptValue($data['$eq']);
		}
		if (isset($data['$in']) && is_array($data['$in'])) {
			$data['$in'] = array_map(array('Billrun_Utils_Encryption', 'encryptValue'), $data['$in']);
		}
		return $data;
	}
}
