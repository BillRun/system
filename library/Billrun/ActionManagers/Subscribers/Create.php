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
		parent::__construct();
	}
	
	/**
	 * Get the key name for getting a subscriber from the db.
	 * @return string The name of the key to use to get the subscriber.
	 * @todo This should be made more generic, this logic will probably happen many 
	 * times in our code and is similar for getting a balance etc.
	 */
	protected function getSubscriberQueryKey() {
		return 'imsi';
	}
	
	/**
	 * Check if the subscriber to create already exists.
	 * @return boolean - true if the subscriber exists.
	 */
	protected function subscriberExists() {
		// Check if the subscriber already exists.
		$subscriberQueryKey = $this->getSubscriberQueryKey();
		$subscriberQuery[$subscriberQueryKey] = $this->query[$subscriberQueryKey];
		
		// TODO: Use the subscriber class.
		if($this->collection->query($subscriberQuery)->count() > 0){
			Billrun_Factory::log('Subscriber already exists! [' . print_r($subscriberQuery, true) . ']', Zend_Log::ALERT);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = false;
		try {
			// Create the subscriber only if it doesn't already exists.
			if(!$this->subscriberExists()) {
				$entity = new Mongodloid_Entity($this->query);

				$success = ($this->collection->save($entity, 1) !== false);
			}	
		} catch (\Exception $e) {
			Billrun_Factory::log('failed to store into DB got error : ' . $e->getCode() . ' : ' . $e->getMessage(), Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->query, 1), Zend_Log::ALERT);
			$success = false;
		}

		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => ($success) ? ('Success') : ('Failed') . ' creating subscriber',
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
			return false;
		}
		
		$invalidFields = $this->setQueryFields($jsonData);
		
		// If there were errors.
		if(!empty($invalidFields)) {
			Billrun_Factory::log("Subscribers create received invalid query values in fields: " . implode(',', $invalidFields), Zend_Log::ALERT);
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
