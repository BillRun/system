<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the subscribers action.
 *
 */
class Billrun_ActionManagers_Subscribersautorenew_Update extends Billrun_ActionManagers_Subscribersautorenew_Action {

	// TODO: Create a generic update action class. 
	// TODO: This class shares some logic with the cards and balances update action. 
	// TODO: The setUpdateRecord function is shared. 
	// TODO: This is to be implemented using 'trait'
	use Billrun_Traits_Api_AdditionalInput;

	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $recordToSet = array();
	protected $updateQuery = null;
	protected $query = array();

	/**
	 */
	public function __construct() {
		$this->collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
	}

	/**
	 * Handle the update results.
	 * @param type $count
	 * @param type $found
	 * @return boolean
	 */
	protected function handleResult($count, $found) {
		if ($count || $found) {
			return true;
		}

		$errorCode = 14;
		$this->reportError($errorCode);
		return false;
	}

	/**
	 * Get the update options array
	 * @return array
	 */
	protected function getUpdateOptions() {
		return array(
			'upsert' => true,
			'new' => true,
		);
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$options = $this->getUpdateOptions();
		$count = 0;
		$success = true;
		$updateResult = null;
		try {
			$updateResult = $this->collection->update($this->query, $this->updateQuery, $options);
		} catch (\MongoException $e) {
			$success = false;
			$errorCode = 10;
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}

		if (!$updateResult) {
			$errorCode = 11;
			$this->reportError($errorCode);
		}

		$outputResult = array(
			'status' => 1,
			'desc' => "Success upserting auto renew",
			'details' => ($updateResult) ? $updateResult : 'No results',
		);
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
		if (empty($update) || (!($jsonUpdateData = json_decode($update, true)))) {
			$errorCode = 12;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		if (!isset($jsonUpdateData['to'])) {
			$errorCode = 13;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		if (!$this->fillWithSubscriberValues()) {
			return false;
		}

		if (!$this->fillWithChargingPlanValues()) {
			return false;
		}

		if (!$this->populateUpdateQuery($jsonUpdateData)) {
			return false;
		}

		return true;
	}

	/**
	 * Get the interval value from the json data
	 * @param type $jsonUpdateData
	 * @return Interval or false if error.
	 */
	protected function getInterval($jsonUpdateData) {
		if (!isset($jsonUpdateData['interval'])) {
			return 'month';
		}

		return $this->normalizeInterval($jsonUpdateData['interval']);
	}

	/**
	 * Populate the operation clause
	 * @param type $jsonUpdateData - Input json data.
	 * @param type $set - Query to set operation to.
	 */
	protected function populateOperation($jsonUpdateData, &$set) {
		if (isset($jsonUpdateData['operation'])) {
			$set['operation'] = $jsonUpdateData['operation'];
		}
	}

	/**
	 * Populate the update query
	 * @param type $jsonUpdateData
	 */
	protected function populateUpdateQuery($jsonUpdateData) {
		$interval = $this->getInterval($jsonUpdateData);
		if ($interval === false) {
			$errorCode = 41;
			$this->reportError($errorCode, Zend_Log::ALERT, array($interval));
			return false;
		}

		$set = array(
			'interval' => $interval);

		if (isset($jsonUpdateData['to']['sec'])) {
			$to = new Mongodloid_Date($jsonUpdateData['to']['sec']);
		} else if (is_string($jsonUpdateData['to'])) {
			$to = $set['to'] = new Mongodloid_Date(strtotime($jsonUpdateData['to']));
		} else {
			$to = $jsonUpdateData['to'];
		}

		$toExtended = strtotime("23:59:59", $to->sec);
		
		$jsonUpdateData['to'] = $set['to'] = new Mongodloid_Date($toExtended);
		
		$this->populateOperation($jsonUpdateData, $set);

		$set['done'] = 0;

		// Check if we are at the end of the month.
		if (date('d') == date('t')) {
			$set['eom'] = 1;
		} else {
			$set['eom'] = 0;
		}

		$set['creation_time'] = new Mongodloid_Date();

		// TODO: Is it possible that we will receive a date here with hours minutes and seconds?
		// if so we will have to strip them somehow.
		if (isset($this->query['from']['sec'])) {
			$this->query['from'] = $set['from'] = new Mongodloid_Date($this->query['from']['sec']);
		} else if (is_string($this->query['from'])) {
			$this->query['from'] = $set['from'] = new Mongodloid_Date(strtotime($this->query['from']));
		} else {
			$this->query['from'] = $set['from'] = $set['creation_time'];
		}

		if (isset($this->query['from']->sec)) {
			$from = $this->query['from']->sec;
		} else {
			$from = $this->query['from']['sec'];
		}

		$set['last_renew_date'] = 0;

		// Check if the from is in the past.
		if ($from >= strtotime("tomorrow midnight")) {
			$set['next_renew_date'] = new Mongodloid_Date(strtotime("00:00:00", $from));
			$jsonUpdateData['migrated'] = false;
		} else {
			// TODO: Move the migrated logic to some "migrated handler"
			$set['last_renew_date'] = -1;
			$jsonUpdateData['migrated'] = true;
		}

		$set['remain'] = Billrun_Utils_Autorenew::countMonths($from, $toExtended);

		if (isset($jsonUpdateData['migrated']) && $jsonUpdateData['migrated']) {
			$this->handleMigrated($jsonUpdateData, $set, $from, $toExtended);
		}

		// Set the additional.
		if (!empty($this->additional)) {
			$set['additional'] = $this->additional;
		}

		$this->updateQuery['$set'] = array_merge($this->updateQuery['$set'], $set);

		return true;
	}

	protected function getBaseTime($to, $from) {
		$baseTime = time();
		if ($baseTime < $from) {
			$baseTime = $from;
		}

		if ($baseTime > $to) {
			$baseTime = $to;
		}

		return $baseTime;
	}

	/**
	 * Handle a migrated auto renew record.
	 * @param type $jsonUpdateData
	 * @param type $set
	 * @param type $from
	 * @param type $to
	 */
	protected function handleMigrated(&$jsonUpdateData, &$set, $from, $to) {
		$months = $set['remain'];
		$baseTime = $this->getBaseTime($to, $from);

		$doneMonths = Billrun_Utils_Autorenew::countMonths($from, $baseTime);
		if ($from > time()) {
			$doneMonths -= 1;
		}

		$remainingMonths = $months - $doneMonths;

		$set['remain'] = $remainingMonths;
		$set['done'] = $doneMonths;

		// Check if last day
		if (date('d', $from) === date('t', $from)) {
			$this->data['eom'] = $set['eom'] = 1; // @todo: check if there is need to this property
		}

		$set['next_renew_date'] = Billrun_Utils_Autorenew::getNextRenewDate($from);

		unset($jsonUpdateData['migrated']);
	}

	protected function fillWithSubscriberValues() {
		$this->updateQuery['$set']['sid'] = $this->query['sid'];
		$subCollection = Billrun_Factory::db()->subscribersCollection();
		$subQuery = Billrun_Utils_Mongo::getDateBoundQuery(time(), true);
		$subQuery['sid'] = $this->query['sid'];
		$subRecord = $subCollection->query($subQuery)->cursor()->current();

		if ($subRecord->isEmpty()) {
			$errorCode = 14;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($subQuery['sid']));
			return false;
		}

		$this->updateQuery['$set']['aid'] = $subRecord['aid'];
		$this->updateQuery['$set']['service_provider'] = $subRecord['service_provider'];

		return true;
	}

	protected function fillWithChargingPlanValues() {
		// Get the charging plan.
		$plansCollection = Billrun_Factory::db()->plansCollection();
		$chargingPlanQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$chargingPlanQuery['type'] = 'charging';
		$chargingPlanQuery['name'] = $this->query['charging_plan'];
		$chargingPlanQuery['service_provider'] = $this->updateQuery['$set']['service_provider'];
		$chargingPlanQuery['recurring'] = 1;

		$planRecord = $plansCollection->query($chargingPlanQuery)->cursor()->current();
		if ($planRecord->isEmpty()) {
			$errorCode = 15;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		$this->updateQuery['$set']['operation'] = $planRecord['operation'];
		$this->updateQuery['$set']['charging_plan_name'] = $planRecord['name'];
		$this->updateQuery['$set']['charging_plan_external_id'] = $planRecord['external_id'];
		$this->handlePlanInclude($planRecord);
//		
		return true;
	}

	protected function handlePlanInclude($planRecord) {
		if (!isset($planRecord['include'])) {
			return;
		}

		$include = $planRecord['include'];
		foreach ($include as $key => &$val) {
			if (isset($val['usagev'])) {
				$val['unit_type'] = Billrun_Util::getUsagetUnit($key);
			} else if (isset($val['cost'])) {
				$val['unit_type'] = 'NIS';
			} else if ($key == 'cost') {
				if (Billrun_Util::isAssoc($val)) {
					$val['unit_type'] = 'NIS';
				} else {
					foreach ($val as &$v) {
						$v['unit_type'] = 'NIS';
					}
				}
			}
		}
		$this->updateQuery['$set']['include'] = $include;
	}

	/**
	 * Set all the query fields in the record with values.
	 * @param array $queryData - Data received.
	 * @return boolean true if success to set fields
	 */
	protected function setQueryFields($queryData) {
		$queryFields = Billrun_Factory::config()->getConfigValue('autorenew.query_fields');

		// Get only the values to be set in the update record.
		// TODO: If no update fields are specified the record's to and from values will still be updated!
		foreach ($queryFields as $field) {
			// ATTENTION: This check will not allow updating to empty values which might be legitimate.
			if (!isset($queryData[$field]) || empty($queryData[$field])) {
				$errorCode = 16;
				$this->reportError($errorCode, Zend_Log::NOTICE, array($field));
				return false;
			}

			$this->query[$field] = $queryData[$field];
		}

		return true;
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
			$errorCode = 17;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// If there were errors.
		if ($this->setQueryFields($jsonQueryData) === FALSE) {
			$errorCode = 18;
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
		$this->handleAdditional($input);

		if (!$this->setQueryRecord($input)) {
			return false;
		}

		if (!$this->setUpdateRecord($input)) {
			return false;
		}

		if (!$this->handleDuplicates()) {
			return false;
		}

		return true;
	}

	protected function handleDuplicates() {
		$updatedQuery = array_merge($this->query, $this->updateQuery['$set']);
		if (!$this->collection->query($updatedQuery)->cursor()->limit(1)->current()->isEmpty()) {
			$errorCode = 40;
			$this->reportError($errorCode, Zend_Log::NOTICE);

			// TODO: Pelephone does not want this to return a failure indication.
			// return false;
		}
		return true;
	}

}
