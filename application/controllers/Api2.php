<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing api controller class
 *
 * @package  Controller
 * @since    0.5
 */
class Api2Controller extends ApiController {

	use Billrun_Traits_Api_UserPermissions {
		allowed as allowedPermissions;
	}

	protected function allowed(array $input = array()) {
		if (Billrun_Factory::config()->getConfigValue('api.api2.allowed', 0)) {
			return true;
		}
		return $this->allowedPermissions($input);
	}

}
