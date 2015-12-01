<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using charging plans.
 *
 * @author tom
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
		if($walletPeriod) {
			return $this->getDateFromPeriod($walletPeriod);
		}
		
		return $recordToSet['to'];
	}
	
	/**
	 * Handle the subscriber data.
	 * @param integer $subscriberId The ID of the subscriber.
	 * @return Array of the subscriber and the update query for the input ID or false if error.
	 */
	protected function handleSubscriber($subscriberId) {
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId);

		// Subscriber was not found.
		if (!$subscriber) {
			$error = "Updating by charging plan failed to get subscriber id: " . $subscriberId;
			$this->reportError($error, Zend_Log::ERR);
			return false;
		}

		// Set subscriber to query.
		$updateQuery['sid'] = $subscriber['sid'];
		$updateQuery['aid'] = $subscriber['aid'];
		
		return array ($subscriber, $updateQuery);
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
		if (!$chargingPlanRecord) {
			$error = "Failed to get plan record to update balance query: " . print_r($query, 1);
			$this->reportError($error, Zend_Log::ERR);
			return false;
		}

		$this->setPlanToQuery($query, $chargingPlansCollection, $chargingPlanRecord);
		
		// Get the priority from the plan.
		if(isset($chargingPlanRecord['priority'])) {
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
		list($subscriber, $updateQuery) = 
			$this->handleSubscriber($subscriberId);

		// Subscriber was not found.
		if ($subscriber === false) {
			return false;
		}
		
		$chargingPlanRecord = 
			$this->handleChargingPlan($query, $updateQuery);
		if ($chargingPlanRecord === false) {
			return false;
		}
		
		if (!$this->validateServiceProviders($subscriberId, $recordToSet)) {
			return false;
		}

		// Create a default balance record.
		$defaultBalance = 
			$this->getDefaultBalance($subscriber,
									 $chargingPlanRecord, 
									 $recordToSet);

		$this->handleExpirationDate($recordToSet, $subscriberId);

		// TODO: What if empty?
		$balancesArray = $chargingPlanRecord['include'];
		
		$source = $this->getSourceForLineRecord($chargingPlanRecord);
		$balancesToReturn = array();
		// Go through all charging possibilities. 
		foreach ($balancesArray as $chargingBy => $chargingByValue) {
			$returnPair = 
				$this->goThroughBalanceWallets($chargingBy, 
											   $chargingByValue, 
											   $recordToSet, 
											   $updateQuery, 
											   $defaultBalance);
			
			$returnPair['source'] = $source;
			$returnPair['subscriber'] = $subscriber;
			
			$balancesToReturn[] = $returnPair;
		}

		return $balancesToReturn;
	}

	/**
	 * Go through the balance wallets and update accordingly
	 * @param string $chargingBy Name of type charged by.
	 * @param string $chargingByValue Value of charging typed (KB, MINUTES etc)
	 * @param array $recordToSet - Record to be set in the mongo.
	 * @param array $updateQuery - Query to use for updating the mongo.
	 * @param array $defaultBalance - The default balance value to use if need to upsert.
	 * @return array Array of balance and wallet.
	 * @todo Create a query object that holds the array and the collection that it will be run on.
	 */
	protected function goThroughBalanceWallets($chargingBy, 
											   $chargingByValue, 
											   $recordToSet, 
		                                       $updateQuery,
											   $defaultBalance) {
		$wallet = 
				new Billrun_DataTypes_Wallet($chargingBy,
											 $chargingByValue);
			
		$to = 
			$this->getExpirationTime($wallet, $recordToSet);

		$currentBalance = 
			$this->updateBalance($wallet,
								 $updateQuery,
								 $defaultBalance, 
								 $to);

		return array('balance' => $currentBalance, 
					 'wallet' => $wallet);
	}
	
	/**
	 * Get the array of strings to translate the names of the input fields to the names used in the db.
	 * @return array.
	 */
	protected function getTranslateFields() {
		// TODO: Should this be in conf?
		return array('charging_plan_name' => 'name',
					 'charging_plan_external_id' => 'external_id');
	}
	
	/**
	 * Set the plan data to the query to get tha balance record to update.
	 * @param array $query - The input query.
	 * @param Mongoldoid_Collection $plansCollection - The collection for the charging plans.
	 * @param Mongoldoid_Entity $planRecord - The associated plan record.
	 */
	protected function setPlanToQuery(&$query, $plansCollection, $planRecord) {
		// Set the plan reference.
		$query['current_plan']=
			$plansCollection->createRefByEntity($planRecord);
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
	protected function getUpdateBalanceQuery($balancesColl, 
											 $query, 
											 $wallet,
											 $toTime,
										     $defaultBalance) {
		$update = array();
		// If the balance doesn't exist take the setOnInsert query, 
		// if it exists take the set query.
		if(!$balancesColl->exists($query)) {
			$update = $this->getSetOnInsert($wallet, 
											$defaultBalance);
		} else {
			$this->handleZeroing($query, $balancesColl, $wallet->getFieldName());
			$update = 
				$this->getSetQuery($wallet->getValue(), $wallet->getFieldName(), $toTime);
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
		// Get the balance with the current value field.
		$query[$wallet->getFieldName()]['$exists'] = 1;
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$update = $this->getUpdateBalanceQuery($balancesColl, 
											   $query, 
											   $wallet,
											   $toTime,
											   $defaultBalance);
			
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

		$to = $recordToSet['to'];
		if (!$to) {
			$to = $this->getDateFromDataRecord($chargingPlanRecord);
		}

		$defaultBalance['to'] = $to;
		$defaultBalance['sid'] = $subscriber['sid'];
		$defaultBalance['aid'] = $subscriber['aid'];

		// Get the ref to the subscriber's plan.
		$planName = $subscriber['plan'];
		$plansCollection = Billrun_Factory::db()->plansCollection();

		// TODO: Is this right here to use the now time or should i use the times from the charging plan?
		$plansQuery = array("name" => $planName,
			"to" => array('$gt', $nowTime),
			"from" => array('$lt', $nowTime));
		$planRecord = $plansCollection->query($plansQuery)->cursor()->current();
		$defaultBalance['current_plan'] = $plansCollection->createRefByEntity($planRecord);
		$defaultBalance['charging_type'] = $subscriber['charging_type'];
		// This is being set outside of this function!!!
		//$defaultBalance['charging_by_usaget'] = 
		// TODO: This is not the correct way, priority needs to be calculated.
		$defaultBalance['priority'] = 1;

		return $defaultBalance;
	}

}
