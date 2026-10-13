<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Operations action class
 * Returns information regarding the status of actions 
 * regarding billing cycle management
 *
 * @package  Action
 * 
 */
class OperationsAction extends ApiAction {
	
	use Billrun_Traits_Api_UserPermissions;
	
	protected $orphanTime = '1 day ago';
	
	public function execute() {
		$this->allowed();
		$request = $this->getRequest();
		$action = $request->get('action');
		$filtration = $request->get('filtration');
		if (Billrun_Util::IsIntegerValue($filtration)) {
			$filtration = (int) $filtration;
		}
		$query = array(
			'action' => $action,
			'filtration' => $filtration,
			'start_time' => array('$gt' => new Mongodloid_Date(strtotime($this->orphanTime))),
			'end_time' => array('$exists' => false),
		);
		$operationsColl = Billrun_Factory::db()->operationsCollection();
		$activeOperation = $operationsColl->query($query)->cursor()->current();	
		$ret = array();
		if (!$activeOperation->isEmpty()) {
			$ret['start_date'] = $activeOperation['start_time'];
		}
		$output = array (
			'status' => 1,
			'desc' => 'success',
			'details' =>  array($ret),
		);
		$this->getController()->setOutput(array($output));
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}
}