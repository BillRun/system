<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * email type translator
 *
 * @package  Api
 * @since    5.6
 */
class Api_Translator_EmailModel extends Api_Translator_TypeModel {
	
	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	
	protected $regex = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
	
	public function internalTranslateField($data) {
		if(!preg_match($this->regex, $data)) {
			return false;
		}

		return $data;
	}
}
