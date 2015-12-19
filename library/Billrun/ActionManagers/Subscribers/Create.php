<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 * @author tom
 */
class Billrun_ActionManagers_Subscribers_Create extends Billrun_ActionManagers_Subscribers_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();

	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success creating subscriber"));
	}
	
	/**
	 * Get the query to run to get a subscriber from the db.
	 * @return array Query to run in the mongo.
	 */
	protected function getSubscriberQuery() {
		$subscriberQueryKeys = 
			Billrun_Factory::config()->getConfigValue('subscribers.create_query_fields');
		foreach ($subscriberQueryKeys as $key) {
			$subscriberQuery[$key] = $this->query[$key];
		}
		
		// Get only active subscribers.
		$subscriberQuery['to'] = array('$gte' => new MongoDate());
		return $subscriberQuery;
	}
	
	/**
	 * Check if the subscriber to create already exists.
	 * @return boolean - true if the subscriber exists.
	 */
	protected function subscriberExists() {
		// Check if the subscriber already exists.
		$subscriberQuery = $this->getSubscriberQuery();
		
		$subscribers = $this->collection->query($subscriberQuery);
		
		// TODO: Use the subscriber class.
		if($subscribers->count() > 0){
			$error='Subscriber already exists! [' . print_r($subscriberQuery, true) . ']';
			$errorCode =Billrun_Factory::config()->getConfigValue("subscriber_error_base");
			$this->reportError($error, $errorCode, Zend_Log::ALERT);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Validate the input plan for the subscriber
	 * @return boolean True if valid.
	 */
	protected function validatePlan() {
		$subscriberQuery = $this->getSubscriberQuery();
		
		$planName = $subscriberQuery['plan'];
		$planQuery = Billrun_Util::getDateBoundQuery();
		$planQuery['type'] = 'customer';
		$planQuery['name'] = $planName;
		$planCollection = Billrun_Factory::db()->plansCollection();
		$currentPlan = $planCollection->query($planQuery)->cursor()->current();
		
		// TODO: Use the subscriber class.
		if(!$currentPlan){
			$error='Invalid plan for the subscriber! [' . print_r($planName, true) . ']';
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}		
		
		return true;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		try {
			// Create the subscriber only if it doesn't already exists.
			if($this->validatePlan() &&
			   !$this->subscriberExists()) {
				$entity = new Mongodloid_Entity($this->query);

				$this->collection->save($entity, 1);
			}	
		} catch (\Exception $e) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 1;
			$error = 'Failed storing in DB got error : ' . $e->getCode() . ' : ' . $e->getMessage();
			$this->reportError($error, $errorCode, Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->query, 1), Zend_Log::ALERT);
		}

		$outputResult = 
			array('status'  => $this->errorCode,
				  'desc'    => $this->error,
				  'details' => $entity);
		return $outputResult;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		if(!$this->setQueryRecord($input)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Set the values for the query record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setQueryRecord($input) {
		$jsonData = null;
		$query = $input->get('subscriber');
		if(empty($query) || (!($jsonData = json_decode($query, true)))) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 2;
			$error = "Failed decoding JSON data";
			$this->reportError($error, $errorCode, Zend_Log::ALERT);
			return false;
		}
		
		$invalidFields = $this->setQueryFields($jsonData);
		
		// If there were errors.
		if(!empty($invalidFields)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 3;
			$error="Subscribers create received invalid query values in fields: " . implode(',', $invalidFields);
			$this->reportError($error, $errorCode, Zend_Log::ALERT);
			return false;
		}
		
		// Set the from and to values.
		$this->query['from']= new MongoDate();
		$this->query['to']= new MongoDate(strtotime('+100 years'));
		
		return true;
	}
	
	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return array - Array of strings of invalid field name. Empty if all is valid.
	 */
	protected function setQueryFields($queryData) {
		$queryFields = $this->getQueryFields();
		
		// Arrary of errors to report if any occurs.
		$invalidFields = array();
		
		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($queryFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if(isset($queryData[$field]) && !empty($queryData[$field])) {
				$this->query[$field] = $queryData[$field];
			} else {
				$invalidFields[] = $field;
			}
		}
		
		return $invalidFields;
	}
	
	/**
	 * Get the array of fields to be set in the query record from the user input.
	 * @return array - Array of fields to set.
	 */
	protected function getQueryFields() {
		return Billrun_Factory::config()->getConfigValue("subscribers.create_fields");
	}
}
