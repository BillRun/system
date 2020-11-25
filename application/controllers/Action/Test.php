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
		$query = ['aid' => 515814];
		$countStart = microtime(true);
		$b = Billrun_Factory::db()->linesCollection()->count($query);
		$countTime = number_format((microtime(true) - $countStart), 3);
		$findStart = microtime(true);
		$a = Billrun_Factory::db()->linesCollection()->find($query)->limit(1)->count();
		$findTime = number_format((microtime(true) - $findStart), 3);
		die(print_R(['count time' => $countTime, 'find time' => $findTime], 1));
		$this->allowed();
		$test = new Billrun_Rate(['key' => 'TEST_SMS']);
		$test = new Billrun_Rate(['id' => '5f4cb9d59be6144137c618f2']);
		print_R($test->getData());die;
		$this->getController()->setOutput(array(array("test" => "action"), true));
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
