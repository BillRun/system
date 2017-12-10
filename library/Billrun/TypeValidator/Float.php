<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Float type validator.
 *
 * @since 5.1
 */
class Billrun_TypeValidator_Float extends Billrun_TypeValidator_Base {

	/**
	 * Check if $value is Float
	 * 
	 * @param type $value
	 * @param type $params - optional extra params
	 * @return boolean
	 */
	public function validate($value, $params = array()) {
		return is_numeric($value);
	}

}
