<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Integer type translator
 *
 * @package  Api
 * @since    5.3
 */
class Api_Translator_IntegerModel extends Api_Translator_TypeModel {
	
	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		
		if(!Billrun_Util::IsIntegerValue($data)) {
			// TODO: Create a constant for this code.
			throw new Billrun_Exceptions_Api(99);
		}
		$intValue = (int)$data;
		return $intValue;
	}
}
