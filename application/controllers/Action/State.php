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
class StateAction extends Action_Base {

	const CONCURRENT_CONFIG_ENTRIES = 5;
	
	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute State Action", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests		
		$data = $this->parseData($request);
		switch($data['action']) {
			case 'resetModems':
					$this->setOutput($this->resetModems());
				break;
			case 'stop':
				$this->setOutput($this->stop($data));
				break;
			case 'start':
				$this->setOutput($this->start($data));
				break;
			
		}
		Billrun_Factory::log()->log("Executed State Action", Zend_Log::INFO);
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
		$this->stop();
		system(APPLICATION_PATH."/scripts/reset_modems.sh");
	}
	
	/**
	 * 
	 * @param type $param
	 */
	protected function stop($param = false) {
		$configCol = Billrun_Factory::db()->configCollection();
		$configCol->update(array('$query' => array('key'=>'call_generator'),'sort'=>  array('urt'=> -1)),array('$set'=>array('state'=> 'stop','urt' => new MongoDate(time()))));
	}
	
	/**
	 * 
	 * @param type $param
	 */
	protected function start($param = false) {
		$configCol = Billrun_Factory::db()->configCollection();
		$configCol->update(array('$query' => array('key'=>'call_generator'),'sort'=>  array('urt'=> -1)),array('$set'=>array('state'=> 'start','urt' => new MongoDate(time()))));
	}
}