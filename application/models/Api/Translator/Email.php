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
	public function internalTranslateField($data) {
		if(!preg_match(Billrun_Factory::config()->getConfigValue('billrun.email.regex'), $data)) {
			return false;
		}

		return $data;
	}
}
