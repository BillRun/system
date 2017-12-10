<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Realtime event action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.1
 */
class HealthcheckAction extends RealtimeeventAction {

	protected $event = null;
	protected $usaget = null;

	/**
	 * method to execute health check event
	 */
	public function execute() {
		$this->allowed();
		$this->event = $this->getRequestData('event');
		$this->event['usaget'] = $this->usaget = $this->getRequestData('usaget');
		try {
			// DB heartbeat
			if (!Billrun_Factory::config()->getConfigValue('api.maintain', 0)) {
				Billrun_Factory::db()->linesCollection()
					->query()->cursor()->limit(1)->current();
				$this->event['msg'] = 'success';
				$this->event['status'] = 1;
			} else {
				$this->event['msg'] = 'failed';
				$this->event['status'] = 1;
			}
		} catch (Exception $ex) {
			Billrun_Factory::log('API health check failed. Error ' . $ex->getCode() . ": " . $ex->getMessage(), Zend_Log::EMERG);
			$this->event['msg'] = 'failed';
			$this->event['status'] = 0;
		}
		
		$this->respond($this->event);
	}
	
	protected function getRequestData($key) {
		return $this->_request->getParam($key);
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}
}
