<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Credit action class
 *
 * @package  Action
 * @since    0.5
 */
class RegisterformanagementAction extends Action_Base {

	
	/**
	 * Method to execute the remote  call generators registering.
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		$remoteIp = $_SERVER['REMOTE_ADDR'];
		Billrun_Factory::log()->log("{$remoteIp} ", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$data = $this->parseData($request);
		$configCol = Billrun_Factory::db()->configCollection();
		$config = $configCol->findAndModify(array('key'=> 'call_generator_management'),array('$set'=>array('generators.'.$remoteIp => $data)),array('upsert'=>1));
		if(abs($data['timestamp']-time()) > 30) {
			$this->setError("Time stamp out of sync.",$data);
			Billrun_Factory::log()->log("Alert! : Generator $remoteIp clock is out of sync!", Zend_Log::ALERT);
		}
		return true;
	}

	/**
	 * Parse the json data from the request and add need values to it.
	 * @param type $request
	 * @return mixed the parsed data.
	 */
	protected function parseData($request) {
		$data = json_decode($request['data'],true);
		return $data;
	}
	
	function setError($error_message, $input = null) {
		Billrun_Factory::log()->log('Got Error : '. $error_message. ' , with input of : ' .print_r($input,1), Zend_Log::ERR);
		$output = array(
			'status' => 0,
			'desc' => $error_message,
		);
		if (!is_null($input)) {
			$output['input'] = $input;
		}
		$this->getController()->setOutput(array($output));
		return;
	}
}