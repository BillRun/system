<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Integer type validator.
 *
 * @since 5.1
 */
class Billrun_TypeValidator_Integer extends Billrun_TypeValidator_Base {

	/**
	 * Check if $value is Integer
	 * 
	 * @param type $value
	 * @param type $params - optional extra params
	 * @return boolean
	 */
	public function validate($value, $params = array()) {
		return Billrun_Util::IsIntegerValue($value);
	}

}
