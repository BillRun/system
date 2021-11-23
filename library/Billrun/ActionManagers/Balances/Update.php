<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the balances action.
 *
 */
class Billrun_ActionManagers_Balances_Update extends Billrun_ActionManagers_Balances_Action {

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
	 * the updater class container
	 * 
	 * @var Billrun_ActionManagers_Balances_Updaters_Updater
	 */
	protected $updater;

	/**
	 */
	public function __construct() {
		parent::__construct(array());
		$this->collection->setReadPreference('RP_PRIMARY', array());
	}

	/**
	 * Get the correct action to use for this request.
	 * @param data $request - The input request for the API.
	 * @return Billrun_ActionManagers_Action
	 */
	protected function getAction() {
		$filterName = key($this->query);
		$this->updaterOptions['errors'] = $this->errors;
		$updaterManagerInput = array(
			'options' => $this->updaterOptions,
			'filter_name' => $filterName,
		);

		$manager = new Billrun_ActionManagers_Balances_Updaters_Manager($updaterManagerInput);
		if (!$manager) {
			$errorCode =  14;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return null;
		}


		// This is the method which is going to be executed.
		return $manager->getAction();
	}

	protected function setUpdateValue(&$line, $wallet) {
		$value = $line['balance_after'] - $line['balance_before'];
		$wallet_chargingby = $wallet->getChargingBy();
		if ($line["charging_usaget"] == 'cost' || $line["charging_usaget"] == 'total_cost' || $wallet_chargingby == 'cost' || $wallet_chargingby == 'total_cost' ) {
			$line["aprice"] = $value;
		} else {
			$line["usagev"] = $value;
		}
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
		$insertLine["pp_includes_name"] = $wallet->getPPName();
		$ppID = $insertLine["pp_includes_external_id"] = $wallet->getPPID();

		$beforeUpdateBalance = $beforeUpdate[$ppID];
		if ($beforeUpdateBalance->isEmpty()) {
			$insertLine['balance_before'] = 0;
		} else {
			$insertLine['balance_before'] = $this->getBalanceValue($beforeUpdateBalance);
		}
		$insertLine['balance_after'] = $this->getBalanceValue($balance);

		$this->setUpdateValue($insertLine, $wallet);
		$insertLine["usage_unit"] = $wallet->getChargingByUsagetUnit();
	}

	protected function reportInLinesHandleChargingPlan(&$balanceLine, $chargingPlan) {
		$balanceLine['service_provider'] = $chargingPlan['service_provider'];
		$chargingType = array();
		if (isset($chargingPlan['charging_type'])) {
			$chargingType = $chargingPlan['charging_type'];
		}
		// TODO: put the charging value in the conf?
		if (isset($chargingPlan['charging_value'])) {
			$balanceLine['charging_value'] = $chargingPlan['charging_value'];
		}
		$balanceLine['charging_plan_type'] = implode(",", $chargingType);
	}

	/**
	 * Process report in lines
	 * @param type $outputDocuments
	 * @return array, with records sub array and processed lines sub array.
	 */
	protected function reportInLinesProcess($outputDocuments, $beforeUpdate) {
		$processedLines = array();
		$balancesRecords = array();
		$balancesCol = Billrun_Factory::db()->balancesCollection();
		foreach ($outputDocuments as $balancePair) {
			$balance = $balancePair['balance'];
			$subscriber = $balancePair['subscriber'];
			$archiveLine = array();
			$archiveLine['aid'] = $subscriber['aid'];
			$archiveLine['service_provider'] = $subscriber['service_provider'];
			$archiveLine['plan'] = $subscriber['plan'];
			$archiveLine['source_ref'] = $balancePair['source'];

			// TODO: Move this logic to a updater_balance class.
			if (isset($balancePair['normalized'])) {
				$reducted = $balancePair['normalized']['after'] - $balancePair['normalized']['normalized'];
				$archiveLine['normalized'] = $reducted;
			}

			if (isset($balancePair['wallet'])) {
				$this->reportInLinesHandleWallet($archiveLine, $balance, $balancePair['wallet'], $beforeUpdate);
			}

			$archiveLine['balance_ref'] = $balancesCol->createRefByEntity($balance);
			$archiveLine['rand'] = rand(1, 1000000);
			$archiveLine['stamp'] = Billrun_Util::generateArrayStamp($archiveLine);
			$processedLines[] = $archiveLine;
			$balancesRecords[] = Billrun_Utils_Mongo::convertRecordMongodloidDatetimeFields($balance->getRawData());
		}

		return array("records" => $balancesRecords, "lines" => $processedLines);
	}

	/**
	 * Report the balance update action to the lines collection
	 * @param array $outputDocuments The output result of the Update action.
	 * @param array $beforeUpdate the balance before the update action.
	 * @return array Array of filtered balance mongo records.
	 */
	protected function reportInLines($outputDocuments, $beforeUpdate) {
		$balanceLine = $this->additional;
		$balanceLine["sid"] = $this->subscriberId;
		$balanceLine['urt'] = new Mongodloid_Date();
		$balanceLine['process_time'] = new Mongodloid_Date();;
		$balanceLine['source'] = 'api';
		$balanceLine['type'] = 'balance';
		$balanceLine['usaget'] = 'balance';

		// Handle charging plan values.
		if (isset($outputDocuments['charging_plan'])) {
			$this->reportInLinesHandleChargingPlan($balanceLine, $outputDocuments['charging_plan']);
			unset($outputDocuments['charging_plan']);
		}

		$balanceLine['charging_type'] = $this->updater->getType();

		unset($outputDocuments['updated']);

		$processResult = $this->reportInLinesProcess($outputDocuments, $beforeUpdate);
		$balancesRecords = $processResult['records'];
		$processedLines = $processResult['lines'];

		if (count($processedLines > 0)) {
			$balanceLine['aid'] = $processedLines[0]['aid'];
			$balanceLine['plan'] = $processedLines[0]['plan'];
			$balanceLine['service_provider'] = $processedLines[0]['service_provider'];
			$balanceLine['source_ref'] = $processedLines[0]['source_ref'];
		}

		// Report lines.
		$reportedLine = $balanceLine;
		$reportedLine['information'] = $processedLines;
		$reportedLine['lcount'] = count($processedLines);
		$reportedLine['rand'] = rand(1, 1000000);
		$reportedLine['stamp'] = Billrun_Util::generateArrayStamp($reportedLine);

		$linesCollection = Billrun_Factory::db()->linesCollection();
		$linesCollection->insert($reportedLine);

		$archiveCollection = Billrun_Factory::db()->archiveCollection();

		unset($balanceLine['aprice'], $balanceLine['charge'], $balanceLine['usagev']);
		// Report archive
		foreach ($processedLines as $line) {
			$archiveLine = array_merge($this->additional, $balanceLine, $line);
			$archiveLine['u_s'] = $reportedLine['stamp'];
			$archiveCollection->insert($archiveLine);
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
		// Get the updater for the filter.
		$this->updater = $this->getAction();

		$outputDocuments = $this->updater->update($this->query, $this->recordToSet, $this->subscriberId);

		if(!$outputDocuments) {
			$errorCode =  21;
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}
		
		$documents = $outputDocuments;
		// Write the action to the lines collection.
		$reportedDocuments = $this->reportInLines($outputDocuments, $this->updater->getBeforeUpdate());
		foreach ($documents as $document) {
			$subscriber = $document['subscriber'];
			$balance = $document['balance'];
			$source = $document['source'];
			Billrun_Factory::dispatcher()->trigger('afterBalanceLoad', array($balance, $subscriber, $source));
		}

		$this->stripTx($reportedDocuments);

		$outputResult = array(
			'status' => 1,
			'desc' => "Success updating balances",
			'details' => ($reportedDocuments) ? $reportedDocuments : 'Empty balance',
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
			unset($doc['tx'], $doc['_id'], $doc['notifications_sent']);
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
		$upsertNeeded = Billrun_ActionManagers_Balances_Updaters_Manager::isUpsertRecordNeeded(key($this->query));

		if (!$upsertNeeded) {
			return true;
		}
		$errorCode =  15;
		$this->reportError($errorCode, Zend_Log::NOTICE);
		return false;
	}

	/**
	 * Set the values for the update record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setUpdateRecord($input) {
		$update = $input->get('upsert');

		$upsertNeeded = true;

		if (empty($update)){
			if (!$this->handleNoUpsert()) {
				return false;
			}
			$upsertNeeded = false;
		}

		$jsonUpdateData = json_decode($update, true);
		// If the JSON is invalid
		if($upsertNeeded && ($jsonUpdateData==null)) {
			// [Balances error] 1227
			$errorCode =  27;
			$this->reportError($errorCode, Zend_Log::NOTICE, array(print_r($update,1)));
			return false;
		}
		
		if (isset($jsonUpdateData['operation'])) {
			// TODO: What if this is not INC and not SET? Should we check and raise error?
			$operation = $jsonUpdateData['operation'];
			$this->recordToSet['operation'] = $operation;
		}

		// TODO: If to is not set, but received opration set, it's an error, report?
		$to = isset($jsonUpdateData['expiration_date']) ? ($jsonUpdateData['expiration_date']) : 0;
		if ($to) {
			$this->recordToSet['to'] = (is_string($to)) ? new Mongodloid_Date(strtotime('tomorrow', strtotime($to)) - 1) : $to;
		}

		// Upsert is not needed so no need to go over the fields
		if (!$upsertNeeded) {
			return true;
		}

		$updateFields = $this->getUpdateFields();

		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($updateFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if (isset($jsonUpdateData[$field]) && ((!empty($jsonUpdateData[$field])) || ($jsonUpdateData[$field] === 0))) {
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
			if (isset($queryData[$field]) && !empty($queryData[$field])) {
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
		if (empty($query) || (!($jsonQueryData = json_decode($query, true)))) {
			$errorCode =  16;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$this->query = $this->getUpdateFilter($jsonQueryData);
		// This is a critical error!
		if ($this->query === null) {
			$errorCode =  17;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		// No filter found.
		else if (empty($this->query)) {
			$errorCode =  18;
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
		if (!$sid) {
			$errorCode =  19;
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
		if ($sid === false) {
			return false;
		}

		$this->subscriberId = $sid;
		if (!$this->setQueryRecord($input)) {
			return false;
		}

		if (!$this->setUpdateRecord($input)) {
			return false;
		}

		$this->additional = json_decode($input->get('additional'), true);
		if (!isset($this->additional)) {
			$this->additional = array();
		}

		// Check for recurring.
		$recurring = $input->get('recurring');
		$this->constructOperation($recurring);
		if(!$this->updaterOptions['operation']) {
			// [Balances Error 1228]
			$errorCode =  28;
			$this->reportError($errorCode, Zend_Log::WARN);
			return false;
		}

		return true;
	}

	/**
	 * Construct the operation object to be passed on to the balance updater.
	 * @param mixed $recurring - The recurring input received from the user.
	 */
	protected function constructOperation($recurring) {
		// TODO: For now this is hard-coded, untill the API will define this as a parameter.
		$options = array();
		$options['zero'] = true;

		// Check for recurring.
		if ($recurring) {
			$options['recurring'] = true;
		}
		
		/**
		 * @var Billrun_Balances_Update_Operation
		 */
		$operation = Billrun_Balances_Util::getOperation($this->recordToSet, $options);
		$this->updaterOptions['operation'] = $operation;
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
			if (!isset($jsonQueryData[$fieldName])) {
				continue;
			}

			// Check if filter is already set.
			// If it is, this is an error. We do not want that someone will try
			// to update by secret code, but somehow manages to send a query with 
			// charging_plan, so that we will update by charging plan and not code.
			// To be sure, when receiving more than one filter field, return error!
			if (!empty($filter)) {
				return NULL;
			}

			$filter = array($fieldName => $jsonQueryData[$fieldName]);
		}

		return $filter;
	}

}
