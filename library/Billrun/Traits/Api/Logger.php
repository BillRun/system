<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This Trait enables API modules to log actions.
 *
 */
trait Billrun_Traits_Api_Logger {

	protected $start_time = 0;

	/**
	 * Get the source to log for API log
	 */
	protected abstract function sourceToLog();

	/**
	 * Get the output to log for API log
	 */
	protected abstract function outputToLog();

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
		$logColl = Billrun_Factory::db()->logCollection();
		$saveData = array(
			'source' => $this->sourceToLog(),
			'type' => $request->action,
			'process_time' => new MongoDate(),
			'request' => $this->getRequest()->getRequest(),
			'response' => $this->outputToLog(),
			'request_php_input' => $php_input,
			'server_host' => Billrun_Util::getHostName(),
			'server_pid' => Billrun_Util::getPid(),
			'request_host' => $_SERVER['REMOTE_ADDR'],
			'rand' => rand(1, 1000000),
			'time' => (microtime(1) - $this->start_time) * 1000,
		);
		$saveData['stamp'] = Billrun_Util::generateArrayStamp($saveData);
		$logColl->save(new Mongodloid_Entity($saveData), 0);
	}

}
