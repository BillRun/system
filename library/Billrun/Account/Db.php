<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing account class based on database
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_Account_Db extends Billrun_Account {
	
	/**
	 * The instance of the Subscriber collection.
	 */
	protected $collection;

	/**
	 * Construct a new account DB instance.
	 * @param array $options - Array of initialization parameters.
	 */
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->collection = Billrun_Factory::db()->subscribersCollection();
	}
	
	public function getList($page, $size, $time, $acc_id = null) {
		
	}
	
	/**
	 * Get the account from the db.
	 * @param array $params - Input params to get a subscriber by.
	 * @return array Raw data of mongo raw. False if none found.
	 */
	protected function runAccountQuery($params) {
		$results = $this->collection->query($params)->cursor()->limit(1)->current();
		if ($results->isEmpty()) {
			return false;
		}
		return $results->getRawData();
	}
	
	/**
	 * Get the account from the db.
	 * @param array $params - Input params to get a subscriber by.
	 * @return array Raw data of mongo raw. False if none found.
	 */
	protected function buildQuery($params) {
		$query = array('type' => 'account');
		$queryExcludeParams = array('time', 'type', 'to', 'from');
	
		if (!isset($params['time'])) {
			$query['to']['$gt'] = new MongoDate();
			$query['from']['$lt'] = new MongoDate();
		} else {
			$query['to']['$gt'] = new MongoDate(strtotime($params['time']));
			$query['from']['$lt'] = new MongoDate(strtotime($params['time']));
		}
		
		foreach ($params as $key => $value) {
			if(in_array($key, $queryExcludeParams)){
				continue;
			}
			$query[$key] = $value;
		}
		
		return $query;
	}
	
	/**
	 * method to load subsbscriber details
	 * 
	 * @param array $params load by those params 
	 * @return true if successful.
	 */
	public function load($params) {
		$query = $this->buildQuery($params);
		$data = $this->runAccountQuery($query);
		if (!$data) {
			Billrun_Factory::log('Failed to load account data for params: ' . print_r($params, 1), Zend_Log::NOTICE);
			return false;
		}

		$this->data = $data;
		return true;
	}
	
	/**
	 * method to update subsbscriber collection status
	 */
	public function updateCrmInCollection($updateCollectionStateChanged) {
		$collectionSteps = Billrun_Factory::collectionSteps();
		$result = array('in_collection' => array(), 'out_of_collection' => array());
	
		if(!empty($updateCollectionStateChanged['in_collection'])){
			foreach ($updateCollectionStateChanged['in_collection'] as $aid => $item) {
				$params = array('aid' => $aid, 'time' => date('c'), 'type' => 'account');
				if ($this->load($params)){
					$new_values = array('in_collection' => true, 'in_collection_from' => new MongoDate());
					$collectionSteps->createCollectionSteps($aid);
					if($this->close_and_new($new_values)){
						$result['in_collection'][] = $aid;
					} else {
						$result['error'][] = $aid;
					}
				}
			}
		}
		
		if(!empty($updateCollectionStateChanged['out_of_collection'])){
			foreach ($updateCollectionStateChanged['out_of_collection'] as $aid => $item) {
				$params = array('aid' => $aid, 'time' => date('c'), 'type' => 'account');
				if ($this->load($params)){
					$remove_values = array('in_collection', 'out_of_collection');
					$collectionSteps->removeCollectionSteps($aid);
					if($this->close_and_new(array(), $remove_values)){
						$result['out_of_collection'][] = $aid;
					} else {
						$result['error'][] = $aid;
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * Method to Save as 'Close And New' item
	 * @param Array $set_values Key value array with values to set
	 * @param Array $remove_values Array with keys to unset
	 */
	public function close_and_new($set_values, $remove_values = array()){
		
		// Updare old item
		$id = new MongoId($this->data['_id']->{'$id'});
		unset($this->data['_id']);
		$this->data['to'] = new MongoDate();
		try {
			$this->collection->update(array('_id' => $id), array('$set' => $this->data), array('upsert' => true));
		} catch (Exception $exc) {
			Billrun_Factory::log("Unable to update (close_and_new) subscriber AID: " . $this->data['aid'], Zend_Log::INFO);
			return FALSE;
		}
		
		// Save new item
		if(!isset($set_values['from'])){
			$set_values['from'] = new MongoDate();
		}
		if(!isset($set_values['to'])){
			$set_values['to'] =  new MongoDate(strtotime('+100 years'));
		}
		$newEntityData = array_merge($this->data, $set_values);
		foreach ($remove_values as $remove_filed_name) {
			unset($newEntityData[$remove_filed_name]);
		}
		$newEntity = new Mongodloid_Entity($newEntityData);
		try {
			$ret = $this->collection->insert($newEntity);
			return !empty($ret['ok']);
		} catch (Exception $exc) {
			Billrun_Factory::log("Unable to insert (close_and_new) subscriber AID: " . $this->data['aid'], Zend_Log::INFO);
			return FALSE;
		}
	}
	
	public function getExcludedFromCollection($aids = array()){
		return array();
	}
	
	public function getInCollection($aids = array()){
		$results = array();
		$params = array(
			'in_collection' => true
		);
		$query = $this->buildQuery($params);
		$cursor = $this->collection->query($query)->cursor();
		foreach ($cursor as $row) {
			$results[$row->get('aid')] = $row->getRawData();
		}
		return $results;
	}
	
}
