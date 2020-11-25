<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Test action class
 *
 * @package  Action
 * 
 * @since    4.0
 */
class TestAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	public function execute() {
		$this->allowed();
		$this->getController()->setOutput(array(array("test" => "action"), true));
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
