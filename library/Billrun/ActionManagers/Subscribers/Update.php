<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
		$this->collection->setReadPreference(MongoClient::RP_PRIMARY,array());
	}
	
	/**
	 * Close all the open balances for a subscriber.
	 * 
	 * @param array $update array for update (collection update convention)
	 * 
	 * @return mixed false if failed else array of mongo update results
	 */
	protected function handleBalances(array $update = array()) {
		// Find all balances.
		$balancesQuery = array();
		if (isset($this->query['sid'])) {
			$balancesQuery['sid'] = $this->query['sid'];
		}
		if (isset($this->query['aid'])) {
			$balancesQuery['aid'] = $this->query['aid'];
		}
		
		if (empty($balancesQuery)) {
			return false;
		}

		$options = array(
			'upsert' => false,
			'new' => false,
			'multiple' => true,
		);
		// TODO: Use balances DB/API proxy class.
		
		$autoRenewColl = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
		$autoRenewColl->update($balancesQuery, $update, $options);
		
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		if( empty(array_intersect_key($update['$set'], array('sid'=>1))) ) {
			$balancesColl->update($balancesQuery, $update, $options);
		} else {
			$epoch = !isset($update['$set']['to']) ? new MongoDate() : $update['$set']['to'];
			$cleanupdate = array();
			$cleanupdate['$set']['to'] = $epoch;

			$balances = iterator_to_array($balancesColl->query($balancesQuery));
			$balancesColl->update($balancesQuery, $cleanupdate, $options);
			unset($update['$set']['to']);
			
			foreach($balances as $balance) {
				$newBalance = $balance->getRawData();
				unset($newBalance['_id']);
				foreach($update['$set'] as $key => $val) {
					$newBalance[$key] = $val;
				}
				$newBalance['from'] = $epoch;
				if($newBalance['to']->sec > $epoch->sec ) {
					$balancesColl->save(new Mongodloid_Entity($newBalance));
				}
			}
		}
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
			'multiple' =>1,
		);
		$linesColl = Billrun_Factory::db()->linesCollection();
		return $linesColl->update($keepLinesQuery, array('$set' => $keepLinesUpdate),$options);
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
			$track_time = time();
			if(isset($this->recordToSet['sid'])) {
				$queryArray = array('sid' => $record['sid']);
				$updateArray = array('$set' => array('new_sid' => $this->recordToSet['sid'], 'to'=> new MongoDate($track_time)));
				$updateOptionsArray = array('multiple' => 1);
				$this->collection->update($queryArray, $updateArray, $updateOptionsArray);

				$record['sid'] = $this->recordToSet['sid'];
			}
//				$record['msisdn'] = $this->recordToSet['msisdn'];
			
			// This throws an exception if fails.
			$this->handleKeepHistory($record, $track_time);
			unset($record['_id']);
			$this->recordToSet['from'] = new MongoDate($track_time);
		}

		$record->collection($this->collection);
		$prevRecord = clone $record;
		foreach ($this->recordToSet as $key => $value) {
			if(!$record->set($key, $value)) {
				$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 30;
				$error = "Failed to set values to entity";
				$this->reportError($errorCode, Zend_Log::NOTICE);
				return false;
			}
		}

		Billrun_Factory::dispatcher()->trigger('beforeSubscriberSave', array(&$record, $prevRecord, $this));
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
		$errorCode = 0;
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
				$updateArray = array('$set' => array('to' => new MongoDate()));
				$this->handleBalances($updateArray);
			} else if (isset($this->recordToSet['sid']) || $this->recordToSet['aid']) {
				$updateArray = array('$set' => array());
				if (isset($this->recordToSet['sid'])) {
					$updateArray['$set']['sid'] = $this->recordToSet['sid'];
			}
				if (isset($this->recordToSet['aid'])) {
					$updateArray['$set']['aid'] = $this->recordToSet['aid'];
				}
				$this->handleBalances($updateArray);
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
				  'error_code'    => ($success) ? (0) : ($errorCode),
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
			if(isset($jsonUpdateData[$field])) {
				$this->recordToSet[$field] = $jsonUpdateData[$field];
			}
		}
		
		// THE 'from' FIELD IS SET AFTERWARDS WITH THE DATA FROM THE EXISTING RECORD IN MONGO.
		$this->recordToSet['to'] = new MongoDate(strtotime('+100 years'));
		
		return true;
	}
	
	/**
	 * Get the query used to check if the requested subscriber update is valid
	 * @param type $jsonUpdateData
	 * @return array Query to check if a subscriber exists with a 
	 * key value that is requested to be update for another subscriber.
	 */
	protected function getSubscriberUpdateValidationQuery($jsonUpdateData) {
		$subscriberFields = Billrun_Factory::config()->getConfigValue('subscribers.query_fields');
		
		$subscriberValidationQuery = array();
		$or = array();
		foreach ($subscriberFields as $subField) {
			if(!isset($jsonUpdateData[$subField])) {
				continue;
			}
			
			if (is_array($jsonUpdateData[$subField])) {
				$filtered_array = Billrun_Util::array_remove_compound_elements($jsonUpdateData[$subField]);
				$or[] = array(
					$subField => array(
						'$in' => $filtered_array,
					)
				);
			} else {
				$or[] = array(
					$subField => $jsonUpdateData[$subField]
				);
			}
			
		}
		
		
		if(!empty($or)) {
			$subscriberValidationQuery['$or'] = $or;

			// Exclude the actual user being updated.
			foreach ($this->query as $key => $value) {
				if(isset($value['$in'])) {
					$subscriberValidationQuery[$key]['$nin'] = $value['$in'];
				} else {
					$subscriberValidationQuery[$key]['$ne'] = $value;
				}
			}

		   $date = Billrun_Util::getDateBoundQuery();
		   $subscriberValidationQuery['from'] = $date['from'];
		   $subscriberValidationQuery['to'] = $date['to'];
		}
		
		return $subscriberValidationQuery;
	}
	
	/**
	 * Check if the identification values to be updated for a subscriber 
	 * already exist for another subscriber.
	 * @param type $jsonUpdateData
	 * @return boolean
	 */
	protected function validateSubscriberUpdateValues($jsonUpdateData) {
		$subscriberValidationQuery = $this->getSubscriberUpdateValidationQuery($jsonUpdateData);
		
		if(!empty($subscriberValidationQuery)) {
			
			if($this->collection->exists($subscriberValidationQuery)) {
				$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base");
				$cleaned = Billrun_Util::array_remove_compound_elements($this->query);
				$parameters = http_build_query($cleaned, '', ', ');
				$this->reportError($errorCode, Zend_Log::NOTICE, array($parameters));
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
		
		if (!isset($queryData['sid']) || empty($queryData['sid'])) {
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 38;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		$queryFields = $this->getQueryFields();		

		// Initialize the query with date bounds
		$this->query = Billrun_Util::getDateBoundQuery();
	
		// Array of errors to report if any occurs.
		$ret = false;
		
		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($queryFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if(isset($queryData[$field]) && !empty($queryData[$field])) {
				$queryDataValue = $queryData[$field];
				if(is_array($queryDataValue)){
					$queryDataValue = array('$in' => $queryDataValue);
				}
				$this->query[$field] = $queryDataValue;
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
		
		// If keep_history is set take it or   if we will update the SID  force  tracking history.
		$isSidUpdate = isset($this->recordToSet['sid']) && ($this->query['sid'] !== $this->recordToSet['sid']);
		$this->trackHistory =  $isSidUpdate || Billrun_Util::filter_var($input->get('track_history', $this->trackHistory), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		
		// If keep_balances is set take it.
		$this->keepBalances = Billrun_Util::filter_var($input->get('keep_balances', $this->keepBalances), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		
		// If keep_lines is set take it.
		$this->keepLines = Billrun_Util::filter_var($input->get('keep_lines', $this->keepLines), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		
		return true;
	}

}
