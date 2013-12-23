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
class UpdateconfigAction extends Action_Base {

	const CONCURRENT_CONFIG_ENTRIES = 50;
	
	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Executing Update Config", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$configCol = Billrun_Factory::db()->configCollection();		
		$entity = new Mongodloid_Entity($this->parseData($request),$configCol);		
		
		if ($entity->isEmpty() || $entity->save($configCol) === false) {
			return $this->setError('Failed to store configuration into DB', $request);
		} 
		
		$this->removeOldEnteries($entity['key'],$entity['from'],$entity['to'],$entity['urt']);
		$this->getController()->setOutput(array(array(
			'status' => 1,
			'desc' => 'success',
			'stamp' => $entity['stamp'],
			'input' => $request,
		)));
		
		
		Billrun_Factory::log()->log("Finished Executing Update Config", Zend_Log::INFO);
		return true;
	}

	/**
	 * Parse the json data from the request and add need values to it.
	 * @param type $request
	 * @return \MongoDate
	 */
	protected function parseData($request) {
		$data = json_decode($request['data'],true);
		$data['urt'] = new MongoDate(time());
		$data['key'] = 'call_generator';
		$data['from'] = new MongoDate($data['from']);
		$data['to'] = new MongoDate($data['to']);
		unset($data['_id']);
		return $data;
	}
	
	/**
	 * Remove old config entries
	 * @param type $keythe  key to remove old entries for.
	 */
	protected function removeOldEnteries($key, $from , $to, $urt) {
		$oldEntries = Billrun_Factory::db()->configCollection()->query(array('key' => $key))->cursor()->sort(array('urt'=>-1))->skip(static::CONCURRENT_CONFIG_ENTRIES);
		foreach ($oldEntries as $entry) {
			$entry->collection(Billrun_Factory::db()->configCollection());
			$entry->remove();
		}
		
		return Billrun_Factory::db()->configCollection()->remove(array('key' => $key, 'from' => array('$gte' => $from, '$lte' => $to),'urt' => array('$lt'=> $urt)));
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