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
class Billrun_ActionManagers_SubscribersAutoRenew_Update extends Billrun_ActionManagers_APIAction{
	// TODO: Create a generic update action class. This class shares some logic with the cards and balances update action. The setUpdateRecord function is shared.
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $recordToSet = array();
	protected $to = null;
	protected $query = array();
	protected $isIncrement = true;
	
	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success upserting auto renew"));
		$this->collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
	}
		
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = true;

		$updatedDocument = null;
		try {
			$cursor = $this->collection->query($this->query)->cursor();
			foreach ($cursor as $record) {
				$updatedDocument[] = $record->getRawData();
				if(!$this->updateSubscriberRecord($record)) {
					$success = false;
					break;
				}
				$updatedDocument[] = $record->getRawData();
			}
			
			if($this->keepBalances === FALSE) {
				// Close balances.
				$this->closeBalances($this->recordToSet['sid'], $this->recordToSet['aid']);
			}
			
		} catch (\Exception $e) {
			$error = 'failed storing in the DB got error : ' . $e->getCode() . ' : ' . $e->getMessage();
			$this->reportError($error, Zend_Log::ALERT);
			Billrun_Factory::log('failed saving request :' . print_r($this->recordToSet, 1), Zend_Log::ALERT);
			$success = false;
		}

		if(!$updatedDocument) {
			$success = false;
			$this->reportError("No subscribers found to update");
		}
		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => $this->error,
				  'details' => ($updatedDocument) ? $updatedDocument : 'No results');
		return $outputResult;
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
			$error = "Update action does not have an upsert field!";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		if(!isset($jsonUpdateData['to'])) {
			$error = "The 'to' field is mendatory";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		$this->to = $jsonUpdateData['to'];
		
		if(isset($jsonUpdateData['operation'])) {
			$this->isIncrement = ($jsonUpdateData['operation'] == 'inc');
		}
		
		// TODO INTERVAL IS ALWAYS MONTH
		
		return true;
	}
	
	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return boolean true if success to set fields
	 */
	protected function setQueryFields($queryData) {
		$queryFields =  Billrun_Factory::config()->getConfigValue('autorenew.query_fields');
		
		// Array of errors to report if any occurs.
		$ret = false;
		
		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($queryFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if(isset($queryData[$field]) && !empty($queryData[$field])) {
				$this->query[$field] = $queryData[$field];
				$ret = true;
			}
		}
		
		return $ret;
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
			$error = "Update action does not have a query field!";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		// If there were errors.
		if($this->setQueryFields($jsonQueryData) === FALSE) {
			$error = "Subscribers update received invalid query values in fields";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 * @todo Create a generic update class that implemnts this basic parse logic.
	 */
	public function parse($input) {
		if(!$this->setQueryRecord($input)) {
			return false;
		}
		
		if(!$this->setUpdateRecord($input)){
			return false;
		}
		
		return true;
	}

}
