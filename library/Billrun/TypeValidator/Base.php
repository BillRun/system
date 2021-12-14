<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Basic type validator.
 *
 * @since 5.1
 */
abstract class Billrun_TypeValidator_Base {

	/**
	 * Validate abstract function - validate $value using optional $params
	 * 
	 * @param type $value
	 * @param type $params - optional extra params
	 * @return boolean
	 */
	abstract public function validate($value, $params = array());
}
