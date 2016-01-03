<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using charging plans.
 *
 * @author Tom Feigin
 */
class Billrun_ActionManagers_Balances_Updaters_ChargingPlan extends Billrun_ActionManagers_Balances_Updaters_Updater {

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
	 * @return MongoDate
	 */
	protected function getExpirationTime($wallet, $recordToSet) {
		// Check if the wallet has a special period.
		$walletPeriod = $wallet->getPeriod();
		if ($walletPeriod) {
			return $this->getDateFromPeriod($walletPeriod);
		}
		$wallet->setPeriod($recordToSet['to']);
		return $recordToSet['to'];
	}

	/**
	 * Get the charging plan record and apply values on the queries.
	 * @param array $query Query to get the balances.
	 * @param array $updateQuery Query to update the balances.
	 * @return Charging plan record or false if error.
	 */
	protected function handleChargingPlan(&$query, &$updateQuery) {
		// TODO: This function is free similar to the one in ID, should refactor code to be more generic.
		$chargingPlansCollection = Billrun_Factory::db()->plansCollection();
		$chargingPlanRecord = $this->getRecord($query, $chargingPlansCollection, $this->getTranslateFields());
		if (!$chargingPlanRecord || $chargingPlanRecord->isEmpty()) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base");
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$this->setPlanToQuery($query, $chargingPlansCollection, $chargingPlanRecord);

		// Get the priority from the plan.
		if (isset($chargingPlanRecord['priority'])) {
			$updateQuery['priority'] = $chargingPlanRecord['priority'];
		}

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
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 13;
			$this->reportError($errorCode, Zend_Log::NOTICE);
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

		$this->handleExpirationDate($recordToSet, $chargingPlanRecord);

		// TODO: What if empty?
		$balancesArray = $chargingPlanRecord['include'];
		$balancesToReturn = array();
		
		// Go through all charging possibilities. 
		foreach ($balancesArray as $chargingBy => $chargingByValue) {
			if (Billrun_Util::isAssoc($chargingByValue)) {
				$returnPair = 
					$this->getReturnPair($chargingByValue, 
										 $chargingBy, 
										 $subscriber, 
								 		 $chargingPlanRecord, 
										 $recordToSet, 
										 $updateQuery);
				if($returnPair === false) {
					return false;
				}
				$balancesToReturn[] = $returnPair;
			} else {
				// There is more than one value pair in the wallet.
				foreach ($chargingByValue as $chargingByValueValue) {
					$returnPair = $this->getReturnPair($chargingByValueValue, 
											 		   $chargingBy, 
											 		   $subscriber, 
													   $chargingPlanRecord, 
													   $recordToSet,
													   $updateQuery);
					if($returnPair === false) {
						return false;
					}
					$balancesToReturn[] = $returnPair;
				}
			}
		}

		return $balancesToReturn;
	}

	/**
	 * Go through the balance include fields and return the "wallet" pair.
	 */
	protected function getReturnPair($chargingByValue, 
									 $chargingBy, 
									 $subscriber, 
									 $chargingPlanRecord, 
									 $recordToSet,
									 $updateQuery) {
		// Create a default balance record.
		$defaultBalance = $this->getDefaultBalance($subscriber, $chargingPlanRecord, $recordToSet);
		if($defaultBalance === false) {
			return false;
		}
		
		$ppName = $chargingPlanRecord['pp_includes_name'];
		$ppID = $chargingPlanRecord['pp_includes_external_id'];

		$source = $this->getSourceForLineRecord($chargingPlanRecord);
		
		$ppPair = $this->populatePPValues($chargingByValue, $ppName, $ppID);
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
		
		return $this->goThroughBalanceWallets($params);	
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
			if(!isset($chargingByValue[$ppField])) {
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

		$to = $this->getExpirationTime($wallet, $recordToSet);

		$currentBalance = $this->updateBalance($wallet, $updateQuery, $defaultBalance, $to);

		return array(
			'balance' => $currentBalance,
			'wallet' => $wallet
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
	 * 
	 * @param Mongoldoid_Collection $balancesColl
	 * @param array $query - Query for getting tha balance.
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param MongoDate $toTime - Expiration date.
	 * @return array Query for set updating the balance.
	 */
	protected function getUpdateBalanceQuery($balancesColl, $query, $wallet, $toTime, $defaultBalance) {
		$update = array();
		// If the balance doesn't exist take the setOnInsert query, 
		// if it exists take the set query.
		if (!$balancesColl->exists($query)) {
			$update = $this->getSetOnInsert($wallet, $defaultBalance);
		} else {
			$this->handleZeroing($query, $balancesColl, $wallet->getFieldName());
			$update = $this->getSetQuery($wallet, $toTime);
		}

		return $update;
	}

	/**
	 * Update a single balance.
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param array $query
	 * @return Mongoldoid_Entity
	 */
	protected function updateBalance($wallet, $query, $defaultBalance, $toTime) {
		// HOTFIX: remove priority and use pp_external_id instead
		unset($query['priority']);
		$query['pp_includes_external_id'] = $wallet->getPPID();
		
		// Get the balance with the current value field.
		$query[$wallet->getFieldName()]['$exists'] = 1;
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$update = $this->getUpdateBalanceQuery($balancesColl, $query, $wallet, $toTime, $defaultBalance);

		$options = array(
			'upsert' => true,
			'new' => true,
			'w' => 1,
		);
		
		// Return the new document.
		return $balancesColl->findAndModify($query, $update, array(), $options, true);
	}

	/**
	 * Get a default balance record, without charging by.
	 * @param type $subscriber
	 * @param type $chargingPlanRecord
	 * @param type $recordToSet
	 */
	protected function getDefaultBalance($subscriber, $chargingPlanRecord, $recordToSet) {
		$defaultBalance = array();
		$nowTime = new MongoDate();
		$defaultBalance['from'] = $nowTime;

//		$to = $recordToSet['to'];
//		if (!$to) {
//			$to = $this->getDateFromDataRecord($chargingPlanRecord);
//			$defaultBalance['to'] = $to;
//		}

		$defaultBalance['aid'] = $subscriber['aid'];
		$defaultBalance['sid'] = $subscriber['sid'];
		$defaultBalance['charging_type'] = $subscriber['charging_type'];

		// Get the ref to the subscriber's plan.
		$planName = $subscriber['plan'];
		$plansCollection = Billrun_Factory::db()->plansCollection();

		// TODO: Is this right here to use the now time or should i use the times from the charging plan?
		$plansQuery = array(
			"name" => $planName,
			"to" => array('$gt', $nowTime),
			"from" => array('$lt', $nowTime)
		);
		
		// TODO: Ofer - What are we suppose to do with the plan? we didn't check 
		// if it exists before.
		$planRecord = $plansCollection->query($plansQuery)->cursor()->current();
//		if($planRecord->isEmpty()) {
//			$this->reportError("Inactive plan for t", $errorLevel);
//			// TODO: What error should be reported here?
//			return false;
//		}
		
//		$defaultBalance['current_plan'] = $plansCollection->createRefByEntity($planRecord);
		if (isset($subscriber['charging_type'])) {
			$defaultBalance['charging_type'] = $subscriber['charging_type'];
		} else {
			$defaultBalance['charging_type'] = Billrun_Factory::config()->getConfigValue("subscriber.charging_type_default", "prepaid");
		}
		// This is being set outside of this function!!!
		//$defaultBalance['charging_by_usaget'] = 
		// TODO: This is not the correct way, priority needs to be calculated.
		$defaultBalance['priority'] = 1;

		return $defaultBalance;
	}

}
