<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Date type validator.
 *
 * @since 5.1
 */
class Billrun_TypeValidator_Date extends Billrun_TypeValidator_Base {

	/**
	 * Check if $value is a valid Date format
	 * 
	 * @param type $value
	 * @param type $params - optional extra params
	 * @return boolean
	 */
	public function validate($value, $params = array()) {
		if(!is_string($value)) {
			return false;
		}
		$result = strtotime($value);
		return $result !== false;
	}

}
