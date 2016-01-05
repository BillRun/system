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
class Billrun_ActionManagers_Subscribers_Update extends Billrun_ActionManagers_Subscribers_Action{
	
	use Billrun_FieldValidator_CustomerPlan, Billrun_FieldValidator_ServiceProvider;
	
	// TODO: Create a generic update action class. This class shares some logic with the cards and balances update action. The setUpdateRecord function is shared.
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $recordToSet = array();
	
	protected $query = array();
	protected $trackHistory = true;
	protected $keepLines = true;
	protected $keepBalances = true;
	
	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success updating subscriber"));
	}
	
	/**
	 * Close all the open balances for a subscriber.
	 * 
	 * @param string $sid - The sid of the user to close the balance for.
	 * @param string $aid - The aid of the user to close the balance for.
	 */
	protected function closeBalances($sid, $aid) {
		// Find all balances.
		$balancesUpdate = array('$set' => array('to', new MongoDate()));
		$balancesQuery = 
			array('sid' => $sid, 
				  'aid' => $aid);
		$options = array(
			'upsert' => false,
			'new' => false,
			'w' => 1,
		);
		// TODO: Use balances DB/API proxy class.
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$balancesColl->findAndModify($balancesQuery, $balancesUpdate, array(), $options, true);
	}
	
	/**
	 * Keeps history before the records are modified.
	 * @param type $record - Record to be modified.
	 */
	protected function handleKeepHistory($record, $track_time = null) {
		if (is_null($track_time)) {
			$track_time = time();
		}
		// Cloning the record.
		$oldRecord = clone $record;
		$oldRecord['to'] = new MongoDate($track_time);
		// This throws an exception if fails.
		$this->collection->save($oldRecord);
	}
	
	/**
	 * If user requested to keep the lines, all records in the lines collection are
	 * updated according to the user request.
	 */
	protected function handleKeepLines() {
		$keepLinesFieldsArray = Billrun_Factory::config()->getConfigValue('subscribers.keep_lines');
		$keepLinesUpdate = array();
		$keepLinesQuery = array();
		// Check if there are updated values for 'keep_lines'
		foreach ($this->recordToSet as $key=>$value) {
			if(isset($this->query[$key]) && in_array($key, $keepLinesFieldsArray)) {
				$keepLinesUpdate[$key] = $value;
				$keepLinesQuery[$key] = $this->query[$key];	
			}
		}
		
		// No need to apply keep lines logic
		if(empty($keepLinesQuery)) {
			return true;
		}
		
		$options = array(
			'upsert' => false,
			'new' => false,
			'w' => 1,
		);
		$linesColl = Billrun_Factory::db()->linesCollection();
		return $linesColl->findAndModify($keepLinesQuery, array('$set' => $keepLinesUpdate), array(), $options, true);
	}
	
	/**
	 * Update a single subscriber record.
	 * @param Mongodloid_Entity $record - Subscriber record to update.
	 * @return boolean true if successful.
	 * @throws WriteConcernException
	 */
	protected function updateSubscriberRecord($record) {

			// Check if the user requested to keep history.
		if($this->trackHistory) {
			$record['sid'] = $this->recordToSet['sid'];
//				$record['msisdn'] = $this->recordToSet['msisdn'];
			$track_time = time();
			// This throws an exception if fails.
			$this->handleKeepHistory($record, $track_time);
			unset($record['_id']);
			$this->recordToSet['from'] = new MongoDate($track_time+1);
		}

		$record->collection($this->collection);
		foreach ($this->recordToSet as $key => $value) {
			if(!$record->set($key, $value)) {
				$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 30;
				$error = "Failed to set values to entity";
				$this->reportError($errorCode, Zend_Log::NOTICE);
				return false;
			}
		}

			// This throws an exception if fails.
		$this->collection->save($record);
				
		return true;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = true;

		$updatedDocument = null;
		try {
			if($this->keepLines) {
				$this->handleKeepLines();
			}
			
			$cursor = $this->collection->query($this->query)->cursor();
			foreach ($cursor as $record) {
				$updatedDocument[] = $record->getRawData();
				if(!$this->updateSubscriberRecord($record)) {
					$success = false;
					break;
				}
				$updatedDocument[] = Billrun_Util::convertRecordMongoDatetimeFields($record->getRawData());
			}
			
			if($this->keepBalances === FALSE) {
				// Close balances.
				$this->closeBalances($this->recordToSet['sid'], $this->recordToSet['aid']);
			}
			
		} catch (\Exception $e) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 31;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			$success = false;
		}

		if(!$updatedDocument) {
			$success = false;
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 37;
			$this->reportError($errorCode);
		}
		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => $this->error,
				  'details' => ($updatedDocument) ? $updatedDocument : 'No results');
		return $outputResult;
	}

	/**
	 * Get the array of fields to be set in the update record from the user input.
	 * @return array - Array of fields to set.
	 */
	protected function getUpdateFields() {
		return Billrun_Factory::config()->getConfigValue('subscribers.update_fields');
	}
	
	/**
	 * Set the values for the update record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setUpdateRecord($input) {
		$jsonUpdateData = null;
		$update = $input->get('update');
		if(empty($update) || (!($jsonUpdateData = json_decode($update, true)))) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 32;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		if(!$this->validateSubscriberUpdateValues($jsonUpdateData)) {
			return false;
		}
		
		$updateFields = $this->getUpdateFields();
		
		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($updateFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if(isset($jsonUpdateData[$field]) && !empty($jsonUpdateData[$field])) {
				$this->recordToSet[$field] = $jsonUpdateData[$field];
			}
		}
		
		// THE 'from' FIELD IS SET AFTERWARDS WITH THE DATA FROM THE EXISTING RECORD IN MONGO.
		$this->recordToSet['to'] = new MongoDate(strtotime('+100 years'));
		
		return true;
	}
	
	/**
	 * Check if the identification values to be updated for a subscriber 
	 * already exist for another subscriber.
	 * @param type $jsonUpdateData
	 * @return boolean
	 */
	protected function validateSubscriberUpdateValues($jsonUpdateData) {
		$subscriberFields = Billrun_Factory::config()->getConfigValue('subscribers.query_fields');
		$subscriberValidationQuery = Billrun_Util::getDateBoundQuery();
		foreach ($subscriberFields as $subField) {
			if(isset($jsonUpdateData[$subField])) {
				$subscriberValidationQuery['$or'][] = 
					array($subField => $jsonUpdateData[$subField]);
			}
		}
		
		if(!empty($subscriberValidationQuery)) {
			$subCol = Billrun_Factory::db()->subscribersCollection();
			if($subCol->exists($subscriberValidationQuery)) {
				$this->reportError(Billrun_Factory::config()->getConfigValue("subscriber_error_base"), Zend_Log::NOTICE);
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return boolean true if success to set fields
	 */
	protected function setQueryFields($queryData) {
		// Initialize the query with date bounds
		$this->query = Billrun_Util::getDateBoundQuery();
		
		$queryFields = $this->getQueryFields();
		
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
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 33;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		// If there were errors.
		if($this->setQueryFields($jsonQueryData) === FALSE) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 34;
			$this->reportError($errorCode, Zend_Log::NOTICE);
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
			
		$validateOutput = $this->validateCustomerPlan($this->recordToSet['plan']);
		if($validateOutput !== true) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 36;
			$this->reportError($errorCode, Zend_Log::ALERT, array($this->recordToSet['plan']));
			return false;
		}
		
		if(!$this->validateServiceProvider($this->recordToSet['service_provider'])) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 35;
			$this->reportError($errorCode, Zend_Log::ALERT, array($this->recordToSet['service_provider']));
			return false;
		}
		
		// If keep_history is set take it.
		$this->trackHistory = $input->get('track_history', $this->trackHistory);
		
		// If keep_balances is set take it.
		$this->keepBalances = $input->get('keep_balances', $this->keepBalances);
		
		return true;
	}

}
