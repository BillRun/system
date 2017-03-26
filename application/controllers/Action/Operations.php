<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Operations action class
 *
 * @package  Action
 * 
 */
class OperationsAction extends ApiAction {
	
	protected $orphanTime = '1 day ago';
	
	public function execute() {
		$request = $this->getRequest();
		$action = $request->get('action');
		$filtration = $request->get('filtration');
		$query = array(
			'action' => $action,
			'filtration' => $filtration,
			'start_time' => array('$gt' => new MongoDate(strtotime($this->orphanTime))),
			'end_time' => array('$exists' => true)
		);
		$operationsColl = Billrun_Factory::db()->operationsCollection();
		$activeOperation = $operationsColl->query($query)->cursor()->current();	
		if (!$activeOperation->isEmpty()) {
			$active['start_date'] = $activeOperation['start_time'];
		} else {
			$active = 'No active operation';
		}
		$output = array (
			'status' => $active ? 1 : 0,
			'desc' => $active ? 'success' : 'error',
			'details' =>  !isset($active['start_date']) ? array() : array($active),
		);
		$this->getController()->setOutput(array($output));
	}
}