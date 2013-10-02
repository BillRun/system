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
class UpdateConfig extends Action_Base {

	const CONCORENT_CONFIG_ENTRIES = 3;
	
	/**
	 * method to execute the refund
	 * it's called automatically by the api main controller
	 */
	public function execute() {
		Billrun_Factory::log()->log("Execute refund", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$configCol = Billrun_Factory::db()->configCollection();		
		
		$entity = new Mongodloid_Entity($this->parseData($request));

		if ($entity->save($configCol) === false) {
			return $this->setError('failed to store into DB', $request);
		} else {
			$this->removeOldEnteries($entity['key']);
			$this->getController()->setOutput(array(array(
					'status' => 1,
					'desc' => 'success',
					'stamp' => $entity['stamp'],
					'input' => $request,
			)));
			return true;
		}
	}

	/**
	 * Parse the json data from the request and  add need values to it.
	 * @param type $request
	 * @return \MongoDate
	 */
	protected function parseData($request) {
		$data = $request; //json_decode($request);
		$data['unified_record_time'] = new MongoDate(); 
		return $data;
	}
	
	/**
	 * Remove old config entries
	 * @param type $keythe  key to remove old entries for.
	 */
	protected function removeOldEnteries($key) {
		$oldEntries = Billrun_Factory::db()->configCollection()->query(array('key' => $key))->cursor()->sort(array('unified_record_time'=>-1))->skip(static::CONCORENT_CONFIG_ENTRIES);
		foreach ($oldEntries as $entry) {
			$entry->collection(Billrun_Factory::db()->configCollection());
			$entry->remove();
		}
	}
}