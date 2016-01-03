<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 * @author Tom Feigin
 */
class Billrun_ActionManagers_Subscribers_Create extends Billrun_ActionManagers_Subscribers_Action{
	
	use Billrun_FieldValidator_CustomerPlan, Billrun_FieldValidator_ServiceProvider;
	
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
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base");
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		try {
			// Create the subscriber only if it doesn't already exists.
			if($this->validateCustomerPlan($this->query['plan']) !== true) {
				$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 6;
				$this->reportError($errorCode, Zend_Log::ALERT, array($this->query['plan']));
			} elseif(!$this->validateServiceProvider($this->query['service_provider'])) {
				$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 5;
				$this->reportError($errorCode, Zend_Log::ALERT, array($this->query['service_provider']));
			} elseif(!$this->subscriberExists()) { 
				$entity = new Mongodloid_Entity($this->query);

				$this->collection->save($entity, 1);
			}	
		} catch (\Exception $e) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 1;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Billrun_Log::WARN);
		}

		$outputResult = array(
			'status'        => $this->errorCode == 0 ? 1 : 0,
			'desc'          => $this->error,
			'error_code'    => $this->errorCode,
		);
		
		if (isset($entity)) {
			$outputResult['details'] = $entity->getRawData();
		}
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
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		$invalidFields = $this->setQueryFields($jsonData);
		
		// If there were errors.
		if(!empty($invalidFields)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 3;
			$error = "Subscribers create received invalid query values in fields: " . implode(',', $invalidFields);
			$this->reportError($errorCode, Zend_Log::NOTICE);
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
