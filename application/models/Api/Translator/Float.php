<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Float type translator
 *
 * @package  Api
 * @since    5.3
 */
class Api_Translator_FloatModel extends Api_Translator_TypeModel {
	
	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		
		if(!is_numeric($data)) {
			// TODO: Create a constant for this code.
			throw new Billrun_Exceptions_Api(99);
		}
		$floatValue = (float)$data;
		return $floatValue;
	}
}
