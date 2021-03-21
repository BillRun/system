<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the auto renew action.
 *
 * @todo This class is very similar to balances query, 
 * a generic query class should be created for both to implement.
 */
class Billrun_ActionManagers_Subscribersautorenew_Query extends Billrun_ActionManagers_Subscribersautorenew_Action {

	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();

	/**
	 */
	public function __construct() {
		$this->collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
	}

	/**
	 * Get a plan record according to the subscribers auto renew record.
	 * @param Mongodloid_Entity $record
	 * @return plan record.
	 */
	protected function getPlanRecord($record) {
		$planCollection = Billrun_Factory::db()->plansCollection();
		$planQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$planQuery["name"] = $record['charging_plan_name'];
		$planQuery["type"] = 'charging';
		return $planCollection->query($planQuery)->cursor()->current();
	}

	/**
	 * Populate the plan values.
	 * @param Mongodloid_Entity $record - Record to populate with plan values.
	 */
	protected function populatePlanValues(&$record) {
		if (!isset($record['charging_plan_name'])) {
			$errorCode = 4;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$planRecord = $this->getPlanRecord($record);
		if ($planRecord->isEmpty()) {
			$errorCode = 5;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$record['includes'] = $this->getIncludesArray($planRecord);

		return true;
	}

	protected function setIncludeValuesToAdd(&$includesToReturn, $root, $values) {
		if (empty($includesToReturn)) {
			return;
		}
		$toAdd = array();

		// Set the record values.
		$toAdd['unit_type'] = Billrun_Util::getUsagetUnit($root);

		if (isset($values['usagev'])) {
			$toAdd['amount'] = $values['usagev'];
		} else if (isset($values['cost'])) {
			$toAdd['amount'] = $values['cost'];
		} else if (isset($values[0]['value'])) {
			$toAdd['amount'] = $values[0]['value'];
		}
		$includesToReturn [$values['pp_includes_name']] = $toAdd;
	}

	/**
	 * Get the 'includes' array to return for the plan record.
	 * @param Mongodloid_Entity $planRecord - Record for the current used plan
	 * @return array of include values to return.
	 * @todo Create an object representing the 'include' structure? (charging_name: {type:X, ammount:Y}) ?
	 */
	protected function getIncludesArray($planRecord) {
		if (!isset($planRecord['include'])) {
			// TODO: Is this an error?
			return array();
		}

		$includeList = $planRecord['include'];
		$includesToReturn = array();

		// TODO: Is this filtered by priority?
		// TODO: Should this include the total_cost??
		foreach ($includeList as $includeRoot => $includeValues) {
			if ($includeRoot == 'cost') {
				if (is_numeric($includeValues)) {
					$includeValues = array('usagev' => $includeValues);
					$includeValues['pp_includes_name'] = $planRecord['pp_includes_name'];
				} else {
					foreach ($includeValues as $value) {
						if (!is_array($value)) {
							continue;
						}

						if (isset($value['value'])) {
							$value['usagev'] = $value['value'];
						}
						$this->setIncludeValuesToAdd($includesToReturn, $includeRoot, $value);
					}
				}
			} else if (!isset($includeValues['pp_includes_name'])) {
				continue;
			}

			$this->setIncludeValuesToAdd($includesToReturn, $includeRoot, $includeValues);
		}

		return $includesToReturn;
	}

	/**
	 * Query the subscribers collection to receive data in a range.
	 */
	protected function queryRange() {
		try {
			$cursor = $this->collection->query($this->query)->cursor();
			$returnData = array();
			// TODO: Move this to config file.
			$date_fields = array('from', 'to', 'last_renew_date', 'next_renew_date', 'creation_time');
			// Going through the lines
			foreach ($cursor as $line) {
				$rawItem = $line->getRawData();

				if (!$this->populatePlanValues($rawItem)) {
					// TODO: What error is reported?
					return false;
				}
				$returnData[] = Billrun_Utils_Mongo::convertRecordMongoDatetimeFields($rawItem, $date_fields);
			}
		} catch (\MongoException $e) {
			$errorCode = 0;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return null;
		}

		return $returnData;
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$returnData = $this->queryRange();

		// Check if the return data is invalid.
		if (!$returnData) {
			// If no internal error occured, report on empty data.
			$errorCode = 1;
			$this->reportError($errorCode, Zend_Log::NOTICE);
		}

		$outputResult = array(
			'status' => 1,
			'desc' => "Success querying auto renew",
			'details' => $returnData
		);
		return $outputResult;
	}

	/**
	 * Parse the to and from parameters if exists. If not execute handling logic.
	 */
	protected function parseDateParameters() {
		if (isset($this->query['from']) && $this->query['from'] != '*') {
			$this->query['from'] = array('$lte' => new MongoDate(strtotime($this->query['from'])));
		} else if (!isset($this->query['from'])) {
			$this->query['from']['$lte'] = new MongoDate();
		} else {
			unset($this->query['from']);
		}
		if (isset($this->query['to']) && $this->query['to'] != '*') {
			$this->query['to'] = array('$gte' => new MongoDate(strtotime($this->query['to'])));
		} else if (!isset($this->query['to'])) {
			$this->query['to']['$gte'] = new MongoDate();
		} else {
			unset($this->query['to']);
		}
//		print_R($this->query);die;
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		if (!$this->setQueryRecord($input)) {
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
		$query = $input->get('query');
		if (empty($query) || (!($jsonData = json_decode($query, true)))) {
			$errorCode = 2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		if (!isset($jsonData['sid'])) {
			$errorCode = 3;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$this->query = $jsonData;
		$this->parseDateParameters();

		return true;
	}

}
