<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Basic type validator.
 *
 */
abstract class Billrun_TypeValidator_Base {

	abstract function validate($value, $params);
	
}
