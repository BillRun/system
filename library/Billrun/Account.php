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
	
	protected static $allowedQueryKeys = ['id', 'time'];
	
	public function __construct($options = array()) {
		parent::__construct($options);
		if (isset($options['availableFields'])) {
			$this->availableFields = $options['availableFields'];
		}
		if (isset($options['extra_data'])) {
			$this->customerExtraData = $options['extra_data'];
		}
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
	 * Retrive billable accounts and the're  subscribers revisions for a given cycle period
	 * @param $cycle the cycle period
	 * @param $page the page to retrive
	 * @param $size the size of the  page to return
	 * @param $aids aids array  to only return contained aids
	 */
	public abstract function getBillable(\Billrun_DataTypes_MongoCycleTime $cycle, $page, $size, $aids = []);

	/**
	 * get account revision by params
	 * @return mongodloid entity
	 */
	protected abstract function getAccountDetails($queries, $globalLimit = FALSE, $globalDate = FALSE);
	
	/**
	 * get accounts revisions by params
	 * @return array of mongodloid entities
	 */
	protected abstract function getAccountsDetails($query, $globalLimit = FALSE, $globalDate = FALSE);
	
	/**
	 * Method to Save as 'Close And New' item
	 * @param Array $set_values Key value array with values to set
	 * @param Array $remove_values Array with keys to unset
	 */
	public abstract function closeAndNew($set_values, $remove_values = array());

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
	
	protected function load($queries) {
		$accounts = $this->getAccountDetails($queries);
		return $accounts;
	}
	
	/**
	 * @param $query array of params to load by
	 * @return mongodloid entity - a single account that match that query
	 */
	public function loadAccountForQuery($query) {
		$limit = 1;
		$accountQuery = $this->buildQuery($query, $limit);
		if ($accountQuery === false) {
			Billrun_Factory::log('Cannot identify account. Current parameters: ' . print_R($query, 1), Zend_Log::NOTICE);
			return false;
		}

		$result = $this->load([$accountQuery]);
		if(empty($result)) {
			Billrun_Factory::log('Failed to load account data for params: ' . print_r($query, 1), Zend_Log::DEBUG);
			return $result;
		}
		$this->data = $result[0]->getRawData();
		return $result[0];
	}
	
	/**
	 * @param array $params load by those params 
	 * @return array of account instances
	 */
	public function loadAccountsForQuery($params) {
		$accountsQuery = $this->buildQuery($params);
		if ($accountsQuery === false) {
			Billrun_Factory::log('Cannot identify account. Current parameters: ' . print_R($params, 1), Zend_Log::NOTICE);
			return false;
		}
		$result = $this->load([$accountsQuery]);
		if(empty($result)) {
			Billrun_Factory::log('Failed to load subscriber data for params: ' . print_r($accountsQuery, 1), Zend_Log::DEBUG);
			return $result;
		}
		return $result;
	}
	
	/**
	 * @param array $queries to load one subscriber per query
	 * @return array of account instances
	 */
	public function loadAccountForQueries($queries, $extraData = []) {
		$limit = 1;
		$query = [];
		
		// build a single big query, using the passed params for each subquery
		foreach($queries as $subQuery) {
			$query[] = $this->buildQuery($subQuery, $limit);
		}
		$data = $this->getAccountsDetails($query);
		if (!$data) {
			Billrun_Factory::log('Failed to load account data for params: ' . print_r($params, 1), Zend_Log::NOTICE);
			return false;
		}

		$this->data = $data;
		return true;
	}

	/**
	 * @param array $params - Input params to get an account by.
	 * @return array of query params.
	 */
	protected function buildQuery($params, $limit = false) {
		// validate that params are legal by configuration
		$customFields = array_map(function ($customField) {
			return $customField['field_name'];
		}, Billrun_Factory::config()->getConfigValue('subscribers.account.fields', array()));
		$fields = array_combine($customFields, $customFields);
		
		$query = [];
		if (!isset($params['time'])) {
			$query['time'] = date(Billrun_Base::base_datetimeformat);
		}
		
		foreach ($params as $key => $value) {
			if (!isset($fields[$key]) && !in_array($key, static::$allowedQueryKeys)) {
				return false;
			}
			$query[$key] = $value;
		}

		$query['limit'] = $limit;
		return $query;
	}
	
	public function getInCollection($aids = array()) {
		$results = array();
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


		$cursor = $this->loadAccountsForQuery($params);
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
				$params = array('aid' => $aid, 'time' => date('c'));
				if ($this->loadAccountForQuery($params)) {
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
				$params = array('aid' => $aid, 'time' => date('c'));
				if ($this->loadAccountForQuery($params)) {
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

	//============================ Static function =========================

	public static function getAccountAggregationLogic($params) {
		$subscribersType = strtolower(Billrun_Factory::config()->getConfigValue('subscribers.account.type','db'));
		switch($subscribersType) {
			case "external":
				return new Billrun_Cycle_Aggregation_CustomerRemote($params);
				break;
			case 'db' :
				return	new Billrun_Cycle_Aggregation_CustomerDb($params);
				break;
		}

		throw new Exception("No subscriber aggregation identified");
	}

}
