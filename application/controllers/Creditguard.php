<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing creditguard controller class
 *
 * @package  Controller
 * @since    4.0
 */
class CreditguardController extends ApiController {
	/**
	 * method to set the available actions of the api from config declaration
	 */
	protected function setActions() {
		$this->actions = Billrun_Factory::config()->getConfigValue('creditguard.actions', array());
	}

	/**
	 * method to log api request
	 * 
	 * @todo log response
	 */
	protected function apiLogAction() {
		$request = $this->getRequest();
		$php_input = file_get_contents("php://input");
		if ($request->action == 'index') {
			return;
		}
		$this->logColl = Billrun_Factory::db()->logCollection();
		$saveData = array(
			'source' => 'creditguard',
			'type' => $request->action,
			'process_time' => new MongoDate(),
			'request' => $this->getRequest()->getRequest(),
			'response' => $this->output,
			'request_php_input' => $php_input,
			'server_host' => Billrun_Util::getHostName(),
			'server_pid' => Billrun_Util::getPid(),
			'request_host' => $_SERVER['REMOTE_ADDR'],
			'rand' => rand(1, 1000000),
			'time' => (microtime(1) - $this->start_time) * 1000,
		);
		$saveData['stamp'] = Billrun_Util::generateArrayStamp($saveData);
		$this->logColl->save(new Mongodloid_Entity($saveData), 0);
	}
}
