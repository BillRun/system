<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the balances action.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Update extends Billrun_ActionManagers_Balances_Action{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @array type Array
	 */
	protected $recordToSet = array();
	
	/**
	 * Query to be used to find records to update.
	 * @var array
	 */
	protected $query = array();
	
	/**
	 * Holds the subscriber ID to update the balance for.
	 * @var integer
	 */
	protected $subscriberId = null;

	/**
	 * Array to initialize the updater with.
	 * @var array 
	 */
	protected $updaterOptions = array();
	
	/**
	 */
	public function __construct() {
		parent::__construct();
	}
	
	/**
	 * Get the correct action to use for this request.
	 * @param data $request - The input request for the API.
	 * @return Billrun_ActionManagers_Action
	 */
	protected function getAction() {
		$filterName=key($this->query);
		$updaterManagerInput = 
			array('input'       => $this->updaterOptions,
				  'filter_name' => $filterName);
		
		$manager = new Billrun_ActionManagers_Balances_Updaters_Manager($updaterManagerInput);
		
		// This is the method which is going to be executed.
		return $manager->getAction();
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = true;

		// Get the updater for the filter.
		$updater = $this->getAction();
		
		$outputDocuments = 
			$updater->update($this->query, $this->recordToSet, $this->subscriberId);

		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => ($success) ? ('success') : ('Failed') . ' updating balance',
				  'details' => ($outputDocuments) ? json_encode($outputDocuments) : 'null');
		return $outputResult;
	}

	/**
	 * Get the array of fields to be set in the update record from the user input.
	 * @return array - Array of fields to set.
	 */
	protected function getUpdateFields() {
		return array('value', 'recurring');
	}
	
	/**
	 * Set the values for the update record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setUpdateRecord($input) {
		$jsonUpdateData = null;
		$update = $input->get('upsert');
		if(empty($update) || (!($jsonUpdateData = json_decode($update, true)))) {
			Billrun_Factory::log("Update action does not have an update field!", Zend_Log::ALERT);
			return false;
		}
		
		$operation = "inc";
		if(isset($jsonUpdateData['operation'])) {
			// TODO: What if this is not INC and not SET? Should we check and raise error?
			$operation = $jsonUpdateData['operation'];
		}
		$this->recordToSet['operation'] = $operation;
			
		// TODO: If to is not set, but received opration set, it's an error, report?
		$to = isset($jsonUpdateData['expiration_date']) ? ($jsonUpdateData['expiration_date']) : 0;
		$this->recordToSet['to'] = new MongoDate(strtotime($to));
		$updateFields = $this->getUpdateFields();
		
		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($updateFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if(isset($jsonUpdateData[$field]) && !empty($jsonUpdateData[$field])) {
				$this->recordToSet[$field] = $jsonUpdateData[$field];
			}
		}
		
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
	 * Set the values for the query record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setQueryRecord($input) {
		$jsonQueryData = null;
		$query = $input->get('query');
		if(empty($query) || (!($jsonQueryData = json_decode($query, true)))) {
			Billrun_Factory::log("Update action does not have a query field!", Zend_Log::ALERT);
			return false;
		}
		
		$this->query = $this->getUpdateFilter($jsonQueryData);
		// This is a critical error!
		if($this->query===null){
			Billrun_Factory::log("Balances Update: Received more than one filter field!", Zend_Log::ERR);
		}
		// No filter found.
		else if(empty($this->query)) {
			Billrun_Factory::log("Balances Update: Did not receive a filter field!", Zend_Log::ERR);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$this->subscriberId = $input->get('sid');
		if(empty($this->subscriberId)) {
			Billrun_Factory::log("Update action did not receive subscriber ID!", Zend_Log::ALERT);
			return false;
		}
		
		if(!$this->setQueryRecord($input)) {
			return false;
		}
		
		if(!$this->setUpdateRecord($input)) {
			return false;
		}
		
		$this->updaterOptions['increment'] = ($this->recordToSet == "inc");
		
		// TODO: For now this is hard-coded, untill the API will define this as a parameter.
		$this->updaterOptions['zero'] = true;
		
		return true;
	}
	
	/**
	 * Get the query to use to update mongo.
	 * 
	 * @param type $jsonQueryData - The update JSON input.
	 * @return type Query to run to update mongo
	 */
	protected function getUpdateFilter($jsonQueryData) {
		$filter = array();
		
		// TODO: Take this from the conf
		$filterFields = 
			array('id',
				  '_id',
				  'charging_plan_name', 
				  'charging_plan_external_id', 
				  'pp_includes_name', 
				  'pp_includes_external_id', 
				  'reccuring', 
				  'secret');
		
		// Check which field is set.
		foreach ($filterFields as $fieldName) {
			// Check if the field is set.
			if(!isset($jsonQueryData[$fieldName])) {
				continue;
			}
			
			// Check if filter is already set.
			// If it is, this is an error. We do not want that someone will try
			// to update by secret code, but somehow manages to send a query with 
			// charging_plan, so that we will update by charging plan and not code.
			// To be sure, when receiving more than one filter field, return error!
			if(!empty($filter)) {
				return NULL;
			}

			$filter = array($fieldName => $jsonQueryData[$fieldName]);
		}
		
		return $filter;
	}
}
