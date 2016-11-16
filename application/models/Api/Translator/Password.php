<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Password type translator
 *
 * @package  Api
 * @since    5.3
 */
class Billrun_Api_Translator_Password extends Billrun_Api_Translator_String {
	
	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		$string = parent::internalTranslateField($data);
		$password = password_hash($string, PASSWORD_DEFAULT);
		return $password;
	}
}
