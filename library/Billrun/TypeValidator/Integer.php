<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Integer type validator.
 *
 */
class Billrun_TypeValidator_Integer extends Billrun_TypeValidator_Base {

	public function validate($value, $params) {
		return is_numeric($value) && ($value == intval($value));
	}

}
