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
		switch($data['action']) {
			case 'resetModems':
					$this->setOutput($this->resetModems());
				break;
			case 'reboot':
					$this->setOutput($this->reboot());
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
		return $data;
	}
	/**
	 * 
	 */
	protected function resetModems() {
		system(APPLICATION_PATH."/scripts/resetModems");
	}
	
	/**
	 * 
	 */
	protected function reboot() {
		system(APPLICATION_PATH."/scripts/reboot");
	}

}