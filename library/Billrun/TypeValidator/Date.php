<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Date type validator.
 *
 */
class Billrun_TypeValidator_Date extends Billrun_TypeValidator_Base {

	public function validate($value, $params) {
		return (strtotime($value) === false ? false : true);
	}

}
