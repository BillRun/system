<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Array type translator
 *
 * @package  Api
 * @since    5.3
 */
class Api_Translator_ArrayModel  extends Api_Translator_TypeModel {
	
	/**
	 * Translate an array
	 * @param mixed $data - Input data
	 * @return mixed Translated value.
	 */
	public function internalTranslateField($data) {
		if(!is_array($data)) {
			return false;
		}
		
		// TODO: Use the internal "options" array to implement more complicated conditions.
		return $data;
	}
}
