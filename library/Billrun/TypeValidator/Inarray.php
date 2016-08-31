<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * In array type validator.
 *
 */
class Billrun_TypeValidator_Inarray extends Billrun_TypeValidator_Base {

	public function validate($value, $params) {
		return in_array($value, $params);
	}

}
