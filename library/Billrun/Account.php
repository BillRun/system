<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract account class
 *
 * @package  Billing
 * @since    5.0
 */
abstract class Billrun_Account extends Billrun_Base {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'account';

	/**
	 * Data container for account details
	 * 
	 * @var array
	 */
	protected $data = array();

	/**
	 * the fields that are accessible to public
	 * 
	 * @var array
	 */
	protected $availableFields = array();

	/**
	 * extra fields for billrun
	 * @var array
	 */
	protected $billrunExtraFields = array();

	/**
	 * extra fields for the customer
	 * @var array
	 */
	protected $customerExtraData = array();
	
	/**
	 * The instance of the Account collection.
	 */
	protected $collection;
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['availableFields'])) {
			$this->availableFields = $options['availableFields'];
		}
		if (isset($options['extra_data'])) {
			$this->customerExtraData = $options['extra_data'];
		}
		$this->collection = Billrun_Factory::db()->subscribersCollection();
	}

	/**
	 * method to load subsbscriber details
	 */
	public function __set($name, $value) {
		if (array_key_exists($name, $this->availableFields) && array_key_exists($name, $this->data)) {
			$this->data[$name] = $value;
		}
		return null;
	}

	/**
	 * method to receive public properties of the subscriber
	 * 
	 * @return array the available fields for the subscriber
	 */
	public function getAvailableFields() {
		return $this->availableFields;
	}

	/**
	 * method to get public field from the data container
	 * 
	 * @param string $name name of the field
	 * @return mixed if data field  accessible return data field, else null
	 */
	public function __get($name) {
		if ((array_key_exists($name, $this->availableFields) || in_array($name, $this->billrunExtraFields)) && array_key_exists($name, $this->data)) {
			return $this->data[$name];
		} else if (array_key_exists($name, $this->customerExtraData) && isset($this->data['extra_data'][$name])) {
			return $this->data['extra_data'][$name];
		}
		return null;
	}

	/**
	 * Return true if the subscriber has no data.
	 */
	public function isEmpty() {
		return empty($this->data);
	}

	/**
	 * get the (paged) current account(s) plans by time
	 */
	abstract public function getList($page, $size, $time, $acc_id = null);
	
	/**
	 * get account revisions by params
	 * @return array of mongodloid entities
	 */
	public abstract function getAccountsByQuery($query);
	
	//	abstract public function markCollectionStepsCompleted($aids = array());

	/**
	 * Returns field names to be saved when creating billrun
	 * @return array
	 */
	public function getExtraFieldsForBillrun() {
		return $this->billrunExtraFields;
	}

	/**
	 * Returns extra fields for the customer
	 * @return array
	 */
	public function getCustomerExtraData() {
		return $this->customerExtraData;
	}
	
	public function getCustomerData() {
		return $this->data;
	}
	
	/**
	 * method to load account details
	 * 
	 * @param array $params load by those params 
	 * @return true if successful.
	 */
	public function load($params) {
		$query = $this->buildQuery($params);
		$data = $this->getAccountDetails($query);
		if (!$data) {
			Billrun_Factory::log('Failed to load account data for params: ' . print_r($params, 1), Zend_Log::NOTICE);
			return false;
		}

		$this->data = $data;
		return true;
	}

	/**
	 * Get the account from the db.
	 * @param array $params - Input params to get a subscriber by.
	 * @return array Raw data of mongo raw. False if none found.
	 */
	protected function buildQuery($params) {
		$query = array('type' => 'account');
		$queryExcludeParams = array('time', 'type', 'to', 'from');
		
		if (isset($params['time'])) {
			$query['to']['$gt'] = new MongoDate(strtotime($params['time']));
			$query['from']['$lte'] = new MongoDate(strtotime($params['time']));
		} else {
			$query = array_merge($query, Billrun_Utils_Mongo::getDateBoundQuery());
		}

		foreach ($params as $key => $value) {
			if (in_array($key, $queryExcludeParams)) {
				continue;
			}
			$query[$key] = $value;
		}

		return $query;
	}
	
	/**
	 * 
	 * Method to Save as 'Close And New' item
	 * @param Array $set_values Key value array with values to set
	 * @param Array $remove_values Array with keys to unset
	 */
	public function closeAndNew($set_values, $remove_values = array()) {

		// Updare old item
		$update = array('to' => new MongoDate());
		try {
			$this->collection->update(array('_id' => $this->data['_id']), array('$set' => $update));
		} catch (Exception $exc) {
			Billrun_Factory::log("Unable to update (closeAndNew) subscriber AID: " . $this->data['aid'], Zend_Log::INFO);
			return FALSE;
		}

		// Save new item
		if (!isset($set_values['from'])) {
			$set_values['from'] = new MongoDate();
		}
		if (!isset($set_values['to'])) {
			$set_values['to'] = new MongoDate(strtotime('+100 years'));
		}
		$newEntityData = array_merge($this->data, $set_values);
		foreach ($remove_values as $remove_filed_name) {
			unset($newEntityData[$remove_filed_name]);
		}
		unset($newEntityData['_id']);
		$newEntity = new Mongodloid_Entity($newEntityData);
		try {
			$ret = $this->collection->insert($newEntity);
			return !empty($ret['ok']);
		} catch (Exception $exc) {
			Billrun_Factory::log("Unable to insert (closeAndNew) subscriber AID: " . $this->data['aid'], Zend_Log::INFO);
			return FALSE;
		}
	}
	
	public function getInCollection($aids = array()) {
		$results = array();
		$params = Billrun_Utils_Mongo::getDateBoundQuery();
		$exempted = $this->getExcludedFromCollection($aids);
		$subject_to = $this->getIncludedInCollection($aids);
		$params['in_collection'] = true;
		// white list exists but aids not included
		if (!is_null($subject_to) && empty($subject_to)) {
			return $results;
		}
		// white list exists and aids included
		if (!is_null($subject_to) && !empty($subject_to)) {
			$params['aid']['$in'] = $subject_to;
		}
		// black list exist and include aids
		if (!empty($exempted)) {
			$params['aid']['$nin'] = $exempted;
		}
		$query = $this->buildQuery($params);
		$cursor = $this->collection->query($query)->cursor();
		foreach ($cursor as $row) {
			$results[$row->get('aid')] = $row->getRawData();
		}
		return $results;
	}
	

	/**
	 * method to update account collection status
	 */
	public function updateCrmInCollection($updateCollectionStateChanged) {
		$collectionSteps = Billrun_Factory::collectionSteps();
		$result = array('in_collection' => array(), 'out_of_collection' => array());

		if (!empty($updateCollectionStateChanged['in_collection'])) {
			foreach ($updateCollectionStateChanged['in_collection'] as $aid => $item) {
				$params = array('aid' => $aid, 'time' => date('c'), 'type' => 'account');
				if ($this->load($params)) {
					$new_values = array('in_collection' => true, 'in_collection_from' => new MongoDate());
					$collectionSteps->createCollectionSteps($aid);
					if ($this->closeAndNew($new_values)) {
						$result['in_collection'][] = $aid;
					} else {
						$result['error'][] = $aid;
					}
				}
			}
		}

		if (!empty($updateCollectionStateChanged['out_of_collection'])) {
			foreach ($updateCollectionStateChanged['out_of_collection'] as $aid => $item) {
				$params = array('aid' => $aid, 'time' => date('c'), 'type' => 'account');
				if ($this->load($params)) {
					$remove_values = array('in_collection', 'in_collection_from');
					$collectionSteps->removeCollectionSteps($aid);
					if ($this->closeAndNew(array(), $remove_values)) {
						$result['out_of_collection'][] = $aid;
					} else {
						$result['error'][] = $aid;
					}
				}
			}
		}
		$collectionSteps->runCollectionStateChange($result['in_collection'], true);
		$collectionSteps->runCollectionStateChange($result['out_of_collection'], false);
		return $result;
	}
	
	public function getExcludedFromCollection($aids = array()) {
		$excludeIds = Billrun_Factory::config()->getConfigValue('collection.settings.customers.exempted_from_collection', []);
		if(empty($excludeIds)) {
			return [];
		}
		if (empty($aids)) {
			return $excludeIds;
		}
		return array_intersect($aids, $excludeIds);
	}
	
	
	public function getIncludedInCollection($aids = array()) {
		$includeIds = Billrun_Factory::config()->getConfigValue('collection.settings.customers.subject_to_collection', []);
		if (empty($includeIds)) {
			return empty($aids) ? null : $aids;
		}
		if (empty($aids)) {
			return $includeIds;
		}	
		return array_intersect($aids, $includeIds);
	}

}
