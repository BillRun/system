<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * THis  calls   enable  local operation to be  doe  through the http api
 *
 * @package  Action
 * @since    0.5
 */
class OperationsAction extends Action_Base {

	const CONCURRENT_CONFIG_ENTRIES = 5;
	
	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute Operations Action", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests		
		$data = $this->parseData($request);
		switch($request['action']) {
			case 'resetModems':
					$this->getController()->setOutput($this->resetModems());
				break;
			case 'reboot':
					$this->getController()->setOutput($this->reboot());
				break;
		
			
		}
		Billrun_Factory::log()->log("Executed Operations Action", Zend_Log::INFO);
		return true;
	}

	/**
	 * Parse the json data from the request and add need values to it.
	 * @param type $request
	 * @return \MongoDate
	 */
	protected function parseData($request) {
		$data = json_decode($request['data'],true);
		Billrun_Factory::log()->log("Received Data : ". print_r($data), Zend_Log::DEBUG);
		return $data;
	}
	/**
	 * reset the connected modems
	 */
	protected function resetModems() {
		Billrun_Factory::log()->log("Reseting  the modems", Zend_Log::INFO);
		$path = APPLICATION_PATH."/scripts/resetModems";
		return $this->runCommand($path);
	}
	
	/**
	 * reboot the system
	 */
	protected function reboot() {
		Billrun_Factory::log()->log("Trying to reboot the computer...", Zend_Log::INFO);
		$path = APPLICATION_PATH."/scripts/reboot";
		return $this->runCommand($path);
	}
	
	/**
	 * Run a command in the system.
	 * @param type $path path to for the  command to run.
	 * @return type FALSE if the command failed  the last line of the output  otherwise.
	 */
	protected function runCommand($path) {
		$output = array();
		Billrun_Factory::log()->log("Running : $path", Zend_Log::INFO);
		$ret =  exec($path);
		Billrun_Factory::log()->log("Command output : ".join("\n",$output), Zend_Log::DEBUG);
		return $ret;
	}

}