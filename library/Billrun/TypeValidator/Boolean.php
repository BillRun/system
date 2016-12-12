<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Boolean type validator.
 *
 * @since 5.1
 */
class Billrun_TypeValidator_Boolean extends Billrun_TypeValidator_Base {

	/**
	 * Check if $value is Boolean
	 * 
	 * @param type $value
	 * @param type $params - optional extra params
	 * @return boolean
	 */
	public function validate($value, $params = array()) {
		return is_bool($value);
	}

}
