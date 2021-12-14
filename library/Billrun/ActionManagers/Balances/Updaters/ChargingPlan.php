<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using charging plans.
 *
 */
class Billrun_ActionManagers_Balances_Updaters_ChargingPlan extends Billrun_ActionManagers_Balances_Updaters_Updater {

	protected $type = 'ChargingPlan';

	/**
	 * Get the 'Source' value to put in the record of the lines collection.
	 * @return object The value to set.
	 */
	protected function getSourceForLineRecord($chargingPlanRecord) {
		$chargingPlansCollection = Billrun_Factory::db()->plansCollection();
		return $chargingPlansCollection->createRefByEntity($chargingPlanRecord);
	}

	/**
	 * Get the period for a current wallet, if doesn't have a specific period
	 * take from the general charging plan.
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param array $recordToSet
	 * @return Mongodloid_Date
	 */
	protected function getExpirationTime($wallet, $recordToSet) {
		if ($wallet->getPPID() != 1 && isset($recordToSet['to'])) {
			$wallet->setPeriod($recordToSet['to']);
			return $recordToSet['to'];
		}

		$walletPeriod = $wallet->getPeriod();
		return Billrun_Utils_Mongo::getDateFromPeriod($walletPeriod);
	}

	protected function getChargingPlanQuery($query) {
		$charging_plan_query = $query;
		if ($this->updateOperation->isRecurring()) {
			$charging_plan_query['recurring'] = 1;
		} else {
			$charging_plan_query['$or'] = array(
				array('recurring' => 0),
				array('recurring' => array('$exists' => 0)),
			);
		}

		return $charging_plan_query;
	}

	/**
	 * Get the charging plan record and apply values on the queries.
	 * @param array $query Query to get the balances.
	 * @param array $updateQuery Query to update the balances.
	 * @return Charging plan record or false if error.
	 */
	protected function handleChargingPlan(&$query, &$updateQuery) {
		// TODO: This function is free similar to the one in ID, should refactor code to be more generic.
		$chargingPlansCollection = Billrun_Factory::db()->plansCollection()->setReadPreference('RP_PRIMARY', array());
		$charging_plan_query = $this->getChargingPlanQuery($query);

		$chargingPlanRecord = $this->getRecord($charging_plan_query, $chargingPlansCollection, $this->getTranslateFields());
		if (!$chargingPlanRecord || $chargingPlanRecord->isEmpty()) {
			$errorCode = 0;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$this->setPlanToQuery($query, $chargingPlansCollection, $chargingPlanRecord);

		return $chargingPlanRecord;
	}

	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 * @return The updated record, false if failed.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId);

		// Subscriber was not found.
		if ($subscriber === false) {
			return false;
		}

		if (!isset($query['service_provider'])) {
			$query['service_provider'] = $subscriber['service_provider'];
		} else if ($query['service_provider'] != $subscriber['service_provider']) {
			$errorCode = 13;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$updateQuery = array(
			'aid' => $subscriber['aid'],
			'sid' => $subscriber['sid'],
		);

		$chargingPlanRecord = $this->handleChargingPlan($query, $updateQuery);
		if ($chargingPlanRecord === false) {
			return false;
		}

		if (!$this->validateServiceProviders($subscriber, $chargingPlanRecord)) {
			return false;
		}

		// Handle the operation.
		$this->updateOperation = $this->updateOperation->reconfigure($chargingPlanRecord);
		if (!$this->updateOperation) {
			// [Balances Error 1229]
			$errorCode = 29;
			$this->reportError($errorCode);
			return false;
		}

		$balancesArray = array();
		if (isset($chargingPlanRecord['include'])) {
			$balancesArray = $chargingPlanRecord['include'];
		}

		// Check if we have core balance.
		$coreBalances = $this->getUnlimitedBalances($balancesArray, $chargingPlanRecord);
		foreach ($coreBalances as $balance) {
			if (!$this->handleUnlimitedBalance($subscriber['plan'], $balance, $updateQuery)) {
				return false;
			}
		}

		$balancesToReturn = array('updated' => false);

		// Go through all charging possibilities. 
		foreach ($balancesArray as $chargingBy => $chargingByValue) {
			// TODO: Shouldn't we check using Billrun_Util::isMultidimentionalArray 
			// instead of Billrun_Util::isAssoc? (The last checks if it is an associated array,
			// the second checks if it is an array of arrays).
			if (Billrun_Util::isAssoc($chargingByValue)) {
				$chargingByValue = array($chargingByValue);
			}

			// There is more than one value pair in the wallet.
			foreach ($chargingByValue as $chargingByValueValue) {
				$currRecordToSet = $recordToSet;
				$this->handleExpirationDate($currRecordToSet, $chargingByValueValue);
				$returnPair = $this->getReturnPair($chargingByValueValue, $chargingBy, $subscriber, $chargingPlanRecord, $currRecordToSet, $updateQuery);
				if ($returnPair === false) {
					return false;
				}

				if (isset($returnPair['updated']) && $returnPair['updated']) {
					$balancesToReturn['updated'] = true;
				}

				$balancesToReturn[] = $returnPair;
			}
		}

		// Set the charging plan record
		$balancesToReturn['charging_plan'] = $chargingPlanRecord;
		return $balancesToReturn;
	}

	/**
	 * Get a list of unlimited balances.
	 * @param type $balancesArray
	 * @param type $chargingPlanRecord
	 * @return \Billrun_DataTypes_Wallet
	 */
	protected function getUnlimitedBalances($balancesArray, $chargingPlanRecord) {
		foreach ($balancesArray as $chargeKey => $chargeValue) {
			if (Billrun_Util::isAssoc($chargeValue)) {
				$chargeValue = array($chargeValue);
			}

			$unlimitedBalances = array();
			foreach ($chargeValue as $chargingByValue) {
				if (!empty($chargingByValue['unlimited'])) {
					$ppName = $chargingPlanRecord['pp_includes_name'];
					$ppID = $chargingPlanRecord['pp_includes_external_id'];
					$ppPair = $this->populatePPValues($chargingByValue, $ppName, $ppID);
					$ppPair['unlimited'] = true;
					$wallet = new Billrun_DataTypes_Wallet($chargeKey, $chargingByValue, $ppPair);
					$unlimitedBalances[] = $wallet;
				}
			}
		}

		return $unlimitedBalances;
	}

	/**
	 * method to check if wallet get to max value on update
	 * 
	 * @param string $planName
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param type $query
	 * 
	 * @return boolean true if get to max value, else false
	 */
	protected function handleUnlimitedBalance($planName, $wallet, $query) {
		$query[$wallet->getFieldName()]['$exists'] = 1;
		$query['pp_includes_external_id'] = $wallet->getPPID();
		return parent::handleUnlimitedBalance($planName, $wallet, $query);
	}

	/**
	 * Go through the balance include fields and return the "wallet" pair.
	 */
	protected function getReturnPair($chargingByValue, $chargingBy, $subscriber, $chargingPlanRecord, $recordToSet, $updateQuery) {
		// Create a default balance record.
		// TODO: Why are there values passed that are not used?
		$defaultBalance = $this->getDefaultBalance($subscriber, $chargingByValue, $recordToSet);
		if ($defaultBalance === false) {
			return false;
		}

		// TODO: This is for backward compatability
		$ppName = $chargingPlanRecord['pp_includes_name'];
		$ppID = $chargingPlanRecord['pp_includes_external_id'];

		$source = $this->getSourceForLineRecord($chargingPlanRecord);

		$ppPair = $this->populatePPValues($chargingByValue, $ppName, $ppID);

		// Get the unlimited indicator from the charging plan record.
		$ppPair['unlimited'] = !empty($chargingPlanRecord['unlimited']);
		$params = array(
			'chargingBy' => $chargingBy,
			'chargingByValue' => $chargingByValue,
			'recordToSet' => $recordToSet,
			'updateQuery' => $updateQuery,
			'defaultBalance' => $defaultBalance,
			'ppPair' => $ppPair,
			'source' => $source,
			'subscriber' => $subscriber
		);

		$returnPair = $this->goThroughBalanceWallets($params);

		$wallet = $returnPair['wallet'];
		$normalizeResult = $this->normalizeBalance($returnPair['query'], $subscriber['plan'], $wallet);
		if ($normalizeResult === false) {
			return false;
		}

		// Report on changes
		if ($normalizeResult['nModified'] > 0) {
			$valueName = $wallet->getFieldName();
			$beforeNormalizing = $returnPair['balance'][$valueName];
			$returnPair['balance'][$valueName] = $normalizeResult['max'];
			$returnPair['normalized']['before'] = $beforeNormalizing - $wallet->getValue();
			$returnPair['normalized']['after'] = $beforeNormalizing;
			$returnPair['normalized']['normalized'] = $normalizeResult['max'];
			$returnPair['updated'] = (isset($normalizeResult['min'])) || ($normalizeResult['max'] > $beforeNormalizing - $wallet->getValue());
		}

		unset($returnPair['query']);
		return $returnPair;
	}

	/**
	 * Go throuh the balance wallet and return the wallet pair
	 * @param array $params
	 * @return array wallet pair.
	 */
	protected function goThroughBalanceWallets($params) {
		$returnPair = $this->updateBalanceByWallet(
				$params['chargingBy'], $params['chargingByValue'], $params['recordToSet'], $params['updateQuery'], $params['defaultBalance'], $params['ppPair']);
		$returnPair['source'] = $params['source'];
		$returnPair['subscriber'] = $params['subscriber'];
		return $returnPair;
	}

	/**
	 * Populate the PP values if do not exist use the balance values.
	 * @param type $chargingByValue
	 * @param type $ppName
	 * @param type $ppID
	 * @return pair of pp includes values.
	 */
	protected function populatePPValues(&$chargingByValue, $ppName, $ppID) {
		$ppPair = null;

		// populate pp values
		foreach (array('pp_includes_name', 'pp_includes_external_id') as $ppField) {
			if (!isset($chargingByValue[$ppField])) {
				$ppPair[$ppField] = $ppField == 'pp_includes_name' ? $ppName : $ppID;
			} else {
				$ppPair[$ppField] = $chargingByValue[$ppField];
				unset($chargingByValue[$ppField]);
			}
		}

		return $ppPair;
	}

	/**
	 * Go through the balance wallets and update accordingly
	 * @param string $chargingBy Name of type charged by.
	 * @param string $chargingByValue Value of charging typed (KB, MINUTES etc)
	 * @param array $recordToSet - Record to be set in the mongo.
	 * @param array $updateQuery - Query to use for updating the mongo.
	 * @param array $defaultBalance - The default balance value to use if need to upsert.
	 * @param array $ppPair - Holds the PP values to set to the balance.
	 * @return array Array of balance and wallet.
	 * @todo Create a query object that holds the array and the collection that it will be run on.
	 */
	protected function updateBalanceByWallet($chargingBy, $chargingByValue, $recordToSet, $updateQuery, $defaultBalance, $ppPair) {
		$wallet = new Billrun_DataTypes_Wallet($chargingBy, $chargingByValue, $ppPair);

		$updateQuery['pp_includes_external_id'] = $wallet->getPPID();

		// Get the balance with the current value field.
		$updateQuery[$wallet->getFieldName()]['$exists'] = 1;

		$to = $this->getExpirationTime($wallet, $recordToSet);

		$currentBalance = $this->updateBalance($wallet, $updateQuery, $defaultBalance, $to);

		return array(
			'balance' => $currentBalance,
			'wallet' => $wallet,
			'query' => $updateQuery
		);
	}

	/**
	 * Get the array of strings to translate the names of the input fields to the names used in the db.
	 * @return array.
	 */
	protected function getTranslateFields() {
		// TODO: Should this be in conf?
		return array(
			'charging_plan_name' => 'name',
			'charging_plan_external_id' => 'external_id',
			'service_provider' => 'service_provider',
			'$or' => '$or',
			'recurring' => 'recurring',
		);
	}

	/**
	 * Set the plan data to the query to get tha balance record to update.
	 * @param array $query - The input query.
	 * @param Mongoldoid_Collection $plansCollection - The collection for the charging plans.
	 * @param Mongoldoid_Entity $planRecord - The associated plan record.
	 */
	protected function setPlanToQuery(&$query, $plansCollection, $planRecord) {
		// Set the plan reference.
		$query['current_plan'] = $plansCollection->createRefByEntity($planRecord);
		unset($query['charging_plan_name']);
	}

	/**
	 * Get a default balance record, without charging by.
	 * @param type $subscriber
	 */
	protected function getDefaultBalance($subscriber, $chargingByValue, $recordToSet) {
		$defaultBalance = array();
		$nowTime = new Mongodloid_Date();
		$defaultBalance['from'] = $nowTime;

//		$to = $recordToSet['to'];
//		if (!$to) {
//			$to = $this->getDateFromDataRecord($chargingPlanRecord);
//			$defaultBalance['to'] = $to;
//		}

		$defaultBalance['aid'] = $subscriber['aid'];
		// If the charging plan record is shared, then set the sid to 0.
		if (!empty($chargingByValue['shared'])) {
			$defaultBalance['sid'] = 0;
		} else {
			$defaultBalance['sid'] = $subscriber['sid'];
		}
		$defaultBalance['charging_type'] = $subscriber['charging_type'];
//		$defaultBalance['charging_plan_name'] = $chargingPlanRecord['name'];
		// Get the ref to the subscriber's plan.
//		$planName = $subscriber['plan'];
//		$plansCollection = Billrun_Factory::db()->plansCollection();
		// TODO: Is this right here to use the now time or should i use the times from the charging plan?
//		$plansQuery = array(
//			"name" => $planName,
//			"to" => array('$gt', $nowTime),
//			"from" => array('$lt', $nowTime)
//		);
		// TODO: Ofer - What are we suppose to do with the plan? we didn't check 
		// if it exists before.
//		$planRecord = $plansCollection->query($plansQuery)->cursor()->current();
//		if($planRecord->isEmpty()) {
//			$this->reportError("Inactive plan for t", $errorLevel);
//			// TODO: What error should be reported here?
//			return false;
//		}
//		$defaultBalance['current_plan'] = $plansCollection->createRefByEntity($planRecord);
		if (isset($chargingPlanRecord['connection_type'])) {
			$defaultBalance['connection_type'] = $chargingPlanRecord['connection_type'];
		} else {
			// Default is postpaid!
			$defaultBalance['connection_type'] = Billrun_Factory::config()->getConfigValue("subscriber.connection_type_default", "postpaid");
		}

		return $defaultBalance;
	}

}
