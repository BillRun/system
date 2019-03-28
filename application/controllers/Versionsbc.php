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
class VersionsBcController extends Yaf_Controller_Abstract {

	use Billrun_Traits_Api_UserPermissions;
		
	public function init() {
		$request = $this->getRequest();
		$version = $request->get('api_version');
		$action = $request->get('api_action');
		$this->forward('Api', "v{$version}_{$action}");
		return false;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

        
}
