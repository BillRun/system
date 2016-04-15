<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the balances action.
 *
 */
class Billrun_ActionManagers_Balances_Query extends Billrun_ActionManagers_Balances_Action {

	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $balancesQuery = array();

	/**
	 * Query for projecting the balance.
	 * @var type 
	 */
	protected $balancesProjection = array();

	/**
	 * Array of all available balances
	 * @var array
	 */
	protected $availableBalances = array();

	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => "Success querying balances"));
	}

	/**
	 * Query the balances collection to receive data in a range.
	 */
	protected function queryRangeBalances() {
		try {
			$cursor = $this->collection->query($this->balancesQuery)->cursor();
			$existingBalances = array();

			// Going through the lines
			foreach ($cursor as $line) {
				$rawItem = $line->getRawData();
				$externalID = $rawItem['pp_includes_external_id'];
				unset($this->availableBalances[$externalID]);
				$existingBalances[] = $rawItem;
			}

			$returnData = array_merge($existingBalances, array_values($this->availableBalances));
			foreach ($returnData as &$row) {
				$row = Billrun_Util::convertRecordMongoDatetimeFields($row);
			}
		} catch (\Exception $e) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 30;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return null;
		}

		return $returnData;
	}

	/**
	 * Get the plan object built from the record values.
	 * @param array $prepaidRecord - Prepaid record.
	 * @return \Billrun_DataTypes_Wallet Plan object built with values.
	 */
	protected function getPlanObject($prepaidRecord) {
		$chargingBy = $prepaidRecord['charging_by'];
		$chargingByUsaget = $prepaidRecord['charging_by_usaget'];
		if ($chargingBy == $chargingByUsaget) {
			$chargingByValue = 0;
		} else {
			$chargingByValue = array($chargingBy => 0);
		}

		$ppPair['priority'] = $prepaidRecord['priority'];
		$ppPair['pp_includes_name'] = $prepaidRecord['name'];
		$ppPair['pp_includes_external_id'] = $prepaidRecord['external_id'];

		return new Billrun_DataTypes_Wallet($chargingByUsaget, $chargingByValue, $ppPair);
	}

	/**
	 * Translate a prepaid record to a balance.
	 * @param type $ppinclude
	 */
	protected function constructBalance($ppinclude, $subscriber) {
		$wallet = $this->getPlanObject($ppinclude);
		$balance = $wallet->getPartialBalance();
		$balance['aid'] = $subscriber['aid'];
		$balance['sid'] = $subscriber['sid'];
		$balance['from'] = new MongoDate();
		$balance['to'] = $balance['from'];
		$balance['charging_type'] = $subscriber['charging_type'];

		if (isset($subscriber['charging_type'])) {
			$balance['charging_type'] = $subscriber['charging_type'];
		} else {
			$balance['charging_type'] = Billrun_Factory::config()->getConfigValue("subscriber.charging_type_default", "prepaid");
		}

		return $balance;
	}

	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$returnData = $this->queryRangeBalances();

		// Check if the return data is invalid.
		if (!$returnData) {
			$returnData = array();
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 34;
			$this->reportError($errorCode);
		}

		foreach ($returnData as &$doc) {
			unset($doc['tx'], $doc['_id'], $doc['notifications_sent']);
		}

		$outputResult = array(
			'status' => $this->errorCode == 0 ? 1 : 0,
			'desc' => $this->error,
			'error_code' => $this->errorCode,
			'details' => $returnData
		);
		return $outputResult;
	}

	/**
	 * Parse the to and from parameters if exists. If not execute handling logic.
	 * @param type $input - The received input.
	 */
	protected function parseDateParameters($input) {
		// Check if there is a to field.
		$to = $input->get('to');
		$from = $input->get('from');
		if ($to && $from) {
			$dateParameters = array('to' => array('$lte' => $to), 'from' => array('$gte' => $from));
			$this->setDateParameters($dateParameters, $this->balancesQuery);
		} else {
			$timeNow = new MongoDate();
			$dateParameters = array('to' => array('$gte' => $timeNow), 'from' => array('$lte' => $timeNow));
			// Get all active balances.
			$this->setDateParameters($dateParameters, $this->balancesQuery);
		}
	}

	/**
	 * Set date parameters to a query.
	 * are not null.
	 * @param array $dateParameters - Array of date parameters 
	 * including to and from to set to the query.
	 * @param type $query - Query to set the date in.
	 * @todo this function should move to a more generic location.
	 */
	protected function setDateParameters($dateParameters, &$query) {
		// Go through the date parameters.
		foreach ($dateParameters as $fieldName => $fieldValue) {
			list($condition, $value) = each($fieldValue);
			$query[$fieldName] = array($condition => $value);
		}
	}

	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		$sid = (int) $input->get('sid');
		if (empty($sid)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 31;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$this->balancesQuery = array('sid' => $sid);

		$this->parseDateParameters($input);

		// Set the prepaid filter data.
		if (!$this->createFieldFilterQuery($input)) {
			return false;
		}

		// TODO: Use the subscriber getter
		$this->availableBalances = $this->getAvailableBalances($sid);
		if ($this->availableBalances === false) {
			return false;
		}

		return true;
	}

	/**
	 * Get a subscriber query to get the subscriber.
	 * @param type $subscriberId - The ID of the subscriber.
	 * @return type Query to run.
	 */
	protected function getSubscriberQuery($subscriberId) {
		$query = Billrun_Util::getDateBoundQuery(time(), true); // enable upsert of future subscribers balances
		$query['sid'] = $subscriberId;

		return $query;
	}

	/**
	 * Get billrun subscriber instance.
	 * @param type $subscriberId If of the subscriber to load.
	 */
	protected function getSubscriber($subscriberId) {
		// Get subscriber query.
		$subscriberQuery = $this->getSubscriberQuery($subscriberId);

		$coll = Billrun_Factory::db()->subscribersCollection();
		$results = $coll->query($subscriberQuery)->cursor()->sort(array('from' => 1))->limit(1)->current();
		if ($results->isEmpty()) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 35;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($subscriberId));
			return false;
		}
		return $results->getRawData();
	}

	protected function getAvailableBalances($sid) {
		$subscriber = $this->getSubscriber($sid);
		if ($subscriber === false) {
			return false;
		}

		$planQuery = array("name" => $subscriber['plan']);
		$planColl = Billrun_Factory::db()->plansCollection();
		$plan = $planColl->query($planQuery)->cursor()->current();
		if ($plan->isEmpty()) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 36;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($subscriber['plan']));
			return false;
		}

		$availableBalances = array();

		$thresholds = $plan->getRawData()['pp_threshold'];
		foreach ($thresholds as $id => $value) {
			if ($value == 0) {
				continue;
			}

			// Get the prepaid include record.
			$constructed = $this->constructByPrepaid($subscriber, $id);
			if ($constructed === false) {
				continue;
			}

			$availableBalances[$id] = $constructed;
		}

		return $availableBalances;
	}

	protected function constructByPrepaid($subscriber, $id) {
		// Get the prepaid include record.
		$ppQuery = array("external_id" => $id);
		$ppColl = Billrun_Factory::db()->prepaidincludesCollection();
		$ppinclude = $ppColl->query($ppQuery)->cursor()->current();
		if ($ppinclude->isEmpty()) {
			Billrun_Factory::log("Faild to retrieve pp includes. Query: " . $ppQuery, Zend_Log::NOTICE);
			return false;
		}

		return $this->constructBalance($ppinclude, $subscriber);
	}

	/**
	 * Create the query to filter only the required fields from the record.
	 * @param type $input
	 */
	protected function createFieldFilterQuery($input) {
		$prepaidQuery = $this->getPrepaidQuery($input);

		// Check if received both external_id and name.
		if (count($prepaidQuery) > 1) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 32;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}
		// If empty it means that there is no filtering to be done.
		else if (empty($prepaidQuery)) {
			return true;
		}

		// Set to and from if exists.
		if (isset($this->balancesQuery['to']) && isset($this->balancesQuery['from'])) {
			$this->setDateParameters(array('to' => $this->balancesQuery['to'], 'from' => $this->balancesQuery['from']), $prepaidQuery);
		}

		if (!$this->setPrepaidDataToQuery($prepaidQuery)) {
			return false;
		}

		return true;
	}

	/**
	 * Get the mongo query to run on the prepaid collection.
	 * @param type $input
	 * @return type
	 */
	protected function getPrepaidQuery($input) {
		$prepaidQuery = array();

		$accountName = $input->get('pp_includes_name');
		if (!empty($accountName)) {
			$prepaidQuery['name'] = $accountName;
		}
		$accountExtrenalId = $input->get('pp_includes_external_id');
		if (!empty($accountExtrenalId)) {
			$prepaidQuery['external_id '] = $accountExtrenalId;
		}

		return $prepaidQuery;
	}

	protected function setPrepaidDataToQuery($prepaidQuery) {
		// Get the prepaid record.
		$prepaidCollection = Billrun_Factory::db()->prepaidincludesCollection();

		// TODO: Use the prepaid DB/API proxy.
		$prepaidRecord = $prepaidCollection->query($prepaidQuery)->cursor()->current();
		if (!$prepaidRecord || $prepaidRecord->isEmpty()) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 33;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// TODO: Check if they are set? Better to have a prepaid record object with this functionallity.
		$chargingBy = $prepaidRecord['charging_by'];
		$chargingByUsegt = $prepaidRecord['charging_by_usaget'];

		$this->balancesQuery['charging_by'] = $chargingBy;
		$this->balancesQuery['charging_by_usaget'] = $chargingByUsegt;

		return true;
	}

}
