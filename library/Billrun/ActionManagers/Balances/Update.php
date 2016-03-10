<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the balances action.
 *
 * @author Tom Feigin
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
	 * Comment for updating a balance
	 * @var string
	 */
	protected $additional;
	
	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success updating balances"));
	}
	
	/**
	 * Get the correct action to use for this request.
	 * @param data $request - The input request for the API.
	 * @return Billrun_ActionManagers_Action
	 */
	protected function getAction() {
		$filterName=key($this->query);
		$this->updaterOptions['errors'] = $this->errors;
		$updaterManagerInput = array(
				'options'     => $this->updaterOptions,
				'filter_name' => $filterName,
		);
		
		$manager = new Billrun_ActionManagers_Balances_Updaters_Manager($updaterManagerInput);
		if(!$manager) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 14;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return null;
		}
		
		
		// This is the method which is going to be executed.
		return $manager->getAction();
	}
	
	/**
	 * Report the wallet to the lines table
	 * @param type $insertLine
	 * @param type $balance
	 * @param type $wallet
	 * @param type $beforeUpdate
	 */
	protected function reportInLinesHandleWallet(&$insertLine, $balance, $wallet, $beforeUpdate) {
		$insertLine["usaget"] = 'balance';
		$insertLine["charging_usaget"] = $wallet->getChargingByUsaget();
		$insertLine["usagev"] = $wallet->getValue();
		$insertLine["pp_includes_name"] = $wallet->getPPName();
		$insertLine["pp_includes_external_id"] = $wallet->getPPID();

		if(!isset($insertLine['normalized'])) {
			$insertLine['balance_after'] = $this->getBalanceValue($balance);
			if($beforeUpdate->isEmpty()) {
				$insertLine['balance_before'] = 0;	
			} else {
				$insertLine['balance_before'] = $this->getBalanceValue($beforeUpdate);
			}
		}
		$insertLine["usage_unit"] = $wallet->getChargingByUsagetUnit();
	}
	
	/**
	 * Report the balance update action to the lines collection
	 * @param array $outputDocuments The output result of the Update action.
	 * @param array $beforeUpdate the balance before the update action.
	 * @return array Array of filtered balance mongo records.
	 */
	protected function reportInLines($outputDocuments, $beforeUpdate) {
		$db = Billrun_Factory::db();
		$linesCollection = $db->linesCollection();
		$balanceLine = $this->additional;
		$balanceLine["sid"] = $this->subscriberId;
		$balancesRecords = array();
		$balanceLine['urt'] = new MongoDate();
		$balanceLine['process_time'] = Billrun_Util::generateCurrentTime();
		$balanceLine['source'] = 'api';
		$balanceLine['type'] = 'balance';
		
		// Handle charging plan values.
		if(isset($outputDocuments['charging_plan'])) {
			$chargingPlan = $outputDocuments['charging_plan'];
			$balanceLine['service_provider'] = $chargingPlan['service_provider'];
			$chargingType = array();
			if(isset($chargingPlan['charging_type'])) {
				$chargingType = $chargingPlan['charging_type'];
			}
			$balanceLine['charging_type'] = implode(",",$chargingType);
			unset($outputDocuments['charging_plan']);
		}
		
		unset($outputDocuments['updated']);
		
		foreach ($outputDocuments as $balancePair) {
			$balance = $balancePair['balance'];
			$subscriber = $balancePair['subscriber'];
			$insertLine = $balanceLine;
			$insertLine['aid'] = $subscriber['aid'];
			$insertLine['source_ref'] = $balancePair['source'];
			
			// TODO: Move this logic to a updater_balance class.
			if(isset($balancePair['normalized'])) {
				$insertLine["balance_before"] = $balancePair['normalized']['before'];
				$insertLine["balance_after"] = $balancePair['normalized']['normalized'];
				$reducted = $balancePair['normalized']['after'] - $balancePair['normalized']['normalized'];
				$insertLine['normalized'] = $reducted;
			}
			
			if (isset($balancePair['wallet'])) {
				$this->reportInLinesHandleWallet($insertLine, $balance, $balancePair['wallet'], $beforeUpdate);
			}
			
			$insertLine['balance_ref'] = $db->balancesCollection()->createRefByEntity($balance);
			$insertLine['stamp'] = Billrun_Util::generateArrayStamp($insertLine);
			$linesCollection->insert($insertLine);
			$balancesRecords[] = Billrun_Util::convertRecordMongoDatetimeFields($balance->getRawData());
		}
		
		return $balancesRecords;
	}
	
	protected function getBalanceValue($balance) {
		// TODO: The indicator was 'total_cost' but seems to have changed to 'cost',
		// to preserve legacy I will now accept both, but we should consider normalizing the logic.
		if (in_array($balance['charging_by_usaget'], array('cost', 'total_cost'))) {
			return $balance['balance']['cost'];
		}
		return $balance['balance']['totals'][$balance['charging_by_usaget']][$balance['charging_by']];
	}


	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$success = true;

		// Get the updater for the filter.
		$updater = $this->getAction();
		
		$outputDocuments = $updater->update($this->query, $this->recordToSet, $this->subscriberId);
	
		if($outputDocuments === false) {
			$success = false;
		} elseif (!$outputDocuments) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 21;
			$this->reportError($errorCode, Zend_Log::NOTICE);
		} else {
			// Write the action to the lines collection.
			$outputDocuments = $this->reportInLines($outputDocuments, $updater->getBeforeUpdate());
		}
		
		if($success) {
			$this->stripTx($outputDocuments);
		} else {
			$updaterError = $updater->getError();
			if($updaterError) {
				$this->error = $updaterError;
				$this->errorCode = $updater->getErrorCode();
			}
		}
		
		$outputResult = array(
			'status'      => $this->errorCode == 0 ? 1 : 0,
			'desc'        => $this->error,
			'error_code'  => $this->errorCode,
			'details'     => ($outputDocuments) ? $outputDocuments : 'Empty balance',
		);
		return $outputResult;
	}

	/**
	 * TODO: THIS IS A PATCH
	 * Strip the result from the tx values.
	 * @param type $outputDocuments - output result to strip.
	 */
	protected function stripTx(&$outputDocuments) {
		foreach ($outputDocuments as &$doc) {
			if (isset($doc['tx'])) {
				unset($doc['tx']);
			}
		}
	}
	
	/**
	 * Get the array of fields to be set in the update record from the user input.
	 * @return array - Array of fields to set.
	 */
	protected function getUpdateFields() {
		return Billrun_Factory::config()->getConfigValue('balances.update_fields');
	}
	
	/**
	 * Handle when the upsert record is not received
	 * @return boolean true on success
	 */
	protected function handleNoUpsert() {
		// Check if the update record is needed.
		$upsertNeeded = 
			Billrun_ActionManagers_Balances_Updaters_Manager::isUpsertRecordNeeded(key($this->query));

		if(!$upsertNeeded) {
			return true;
		}
		$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 15;
		$this->reportError($errorCode, Zend_Log::NOTICE);
		return false;
	}
	
	/**
	 * Set the values for the update record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setUpdateRecord($input) {
		$jsonUpdateData = null;
		$update = $input->get('upsert');
		
		$upsertNeeded = true;
		
		if(empty($update) || (!($jsonUpdateData = json_decode($update, true)))) {
			if(!$this->handleNoUpsert()) {
				return false;
			}
			
			$upsertNeeded = false;
		}
		
		$operation = "inc";
		if(isset($jsonUpdateData['operation'])) {
			// TODO: What if this is not INC and not SET? Should we check and raise error?
			$operation = $jsonUpdateData['operation'];
		}
		$this->recordToSet['operation'] = $operation;
			
		// TODO: If to is not set, but received opration set, it's an error, report?
		$to = isset($jsonUpdateData['expiration_date']) ? ($jsonUpdateData['expiration_date']) : 0;
		if ($to) {
			$this->recordToSet['to'] = new MongoDate(strtotime($to));
		}
		
		// Upsert is not needed so no need to go over the fields
		if(!$upsertNeeded) {
			return true;
		}
		
		$updateFields = $this->getUpdateFields();
		
		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($updateFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if(isset($jsonUpdateData[$field]) && (!empty($jsonUpdateData[$field])) || $jsonUpdateData[$field] === 0) {
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
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 16;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		$this->query = $this->getUpdateFilter($jsonQueryData);
		// This is a critical error!
		if($this->query===null){
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 17;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		// No filter found.
		else if(empty($this->query)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 18;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get the integer sid value from the input.
	 * @param json $input - Received input to parse.
	 * @return integer The input sid, false if error occured.
	 */
	protected function getSid($input) {
		$sid = (int) $input->get('sid');
		
		// Check that sid exists.
		if(!$sid) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 19;
			$error = "Update action did not receive a subscriber ID!";
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		
		return Billrun_Util::toNumber($sid);
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$sid = $this->getSid($input);
		if($sid === false){
			return false;
		}
		
		$this->subscriberId = $sid;
		if(!$this->setQueryRecord($input)) {
			return false;
		}
		
		if(!$this->setUpdateRecord($input)) {
			return false;
		}
		
		$this->additional = json_decode($input->get('additional'), true);
		if(!isset($this->additional)) {
			$this->additional = array();
		}
		
		$this->updaterOptions['increment'] = ($this->recordToSet['operation'] == "inc");
		
		// TODO: For now this is hard-coded, untill the API will define this as a parameter.
		$this->updaterOptions['zero'] = true;
		
		// Check for recurring.
		$recurring = $input->get('recurring');
		if($recurring) {
			$this->updaterOptions['recurring'] = 1;
		}
		
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
		$filterFields = Billrun_Factory::config()->getConfigValue('balances.filter_fields');
		
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
