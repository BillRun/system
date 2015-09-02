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
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 * @return The updated record, false if failed.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		// TODO: This function is free similar to the one in ID, should refactor code to be more generic.
		$chargingPlansCollection = Billrun_Factory::db()->plansCollection();
		$chargingPlanRecord = $this->getPlanRecord($query, $chargingPlansCollection);
		if (!$chargingPlanRecord) {
			Billrun_Factory::log("Failed to get plan record to update balance query: " . print_r($query, 1), Zend_Log::ERR);
			return false;
		}

		$this->setPlanToQuery($query, $chargingPlansCollection, $chargingPlanRecord);
		
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId);

		// Subscriber was not found.
		if (!$subscriber) {
			Billrun_Factory::log("Updating by charging plan failed to get subscriber id: " . $subscriberId, Zend_Log::ERR);
			return false;
		}

		// Set subscriber to query.
		$query['sid'] = $subscriber['sid'];
		$query['aid'] = $subscriber['aid'];
		
		if (!$this->validateServiceProviders($subscriberId, $recordToSet)) {
			return false;
		}

		// Create a default balance record.
		$defaultBalance = 
			$this->getDefaultBalance($subscriber,
									 $chargingPlanRecord, 
									 $recordToSet, 
									 $chargingPlansCollection);

		$this->handleExpirationDate($recordToSet, $subscriberId);

		$balancesColl = Billrun_Factory::db()->balancesCollection();

		// TODO: What if empty?
		$balancesArray = $chargingPlanRecord['include'];

		$balancesToReturn = array();
		// Go through all charging possibilities. 
		foreach ($balancesArray as $chargingBy => $chargingByValue) {
			$to = $recordToSet['to'];
			$balancesToReturn[] = 
				$this->updateBalance($chargingBy,
									 $chargingByValue, 
									 $query, 
									 $balancesColl, 
									 $defaultBalance, 
									 $to);
		}

		return $balancesToReturn;
	}

	/**
	 * Set the plan data to the query to get tha balance record to update.
	 * @param array $query - The input query.
	 * @param Mongoldoid_Collection $plansCollection - The collection for the charging plans.
	 * @param Mongoldoid_Entity $planRecord - The associated plan record.
	 */
	protected function setPlanToQuery($query, $plansCollection, $planRecord) {
		// Set the plan reference.
		$query['current_plan']=
			$plansCollection->createRefByEntity($planRecord);
		unset($query['charging_plan_name']);
	}
	
	/**
	 * Handle zeroing the record if the charging value is positive.
	 * @param type $query
	 * @param type $balancesColl
	 * @param string $valueFieldName
	 */
	protected function handleZeroing($query, $balancesColl, $valueFieldName) {
		// User requested incrementing, check if to reset the record.
		if (!$this->ignoreOveruse || !$this->isIncrement) {
			return;
		}

		$zeroingQuery = $query;
		$zeriongUpdate = array();
		$zeroingQuery[$valueFieldName] = array('$gt' => 0);
		$zeriongUpdate['$set'][$valueFieldName] = 0;
		$originalBeforeZeroing = $balancesColl->findAndModify($zeroingQuery, $zeriongUpdate);

		Billrun_Factory::log("Before zeroing: " . print_r($originalBeforeZeroing, 1), Zend_Log::INFO);
	}

	/**
	 * 
	 * @param Mongoldoid_Collection $balancesColl
	 * @param array $query - Query for getting tha balance.
	 * @param string $chargingBy
	 * @param string $chargingByUsegt
	 * @param string $valueFieldName
	 * @param string $valueToUseInQuery 
	 * @param MongoDate $toTime - Expiration date.
	 * @return array Query for set updating the balance.
	 */
	protected function getUpdateBalanceQuery($balancesColl, 
											 $query, 
											 $chargingBy,
											 $chargingByUsegt, 
											 $valueFieldName,
										     $valueToUseInQuery,
											 $toTime,
										     $defaultBalance) {
		$update = array();
		// If the balance doesn't exist take the setOnInsert query, 
		// if it exists take the set query.
		if(!$balancesColl->exists($query)) {
			$update = $this->getSetOnInsert($chargingBy, 
											$chargingByUsegt,
											$valueFieldName,
											$valueToUseInQuery, 
											$defaultBalance);
		} else {
			$this->handleZeroing($query, $balancesColl, $valueFieldName);
			$update = $this->getSetQuery($valueToUseInQuery, $valueFieldName, $toTime);
		}
		
		return $update;
	}
	
	/**
	 * Update a single balance.
	 * @param string $chargingBy
	 * @param string $chargingByValue
	 * @param array $query
	 * @param Mongoldoid_Collection $balancesColl
	 * @return Mongoldoid_Entity
	 */
	protected function updateBalance($chargingBy, $chargingByValue, $query, $balancesColl, $defaultBalance, $toTime) {
		$valueFieldName = array();
		$valueToUseInQuery = null;
		$chargingByUsegt = $chargingBy;

		if (!is_array($chargingByValue)) {
			$valueFieldName = 'balance.' . $chargingBy;
			$valueToUseInQuery = $chargingByValue;
		} else {
			list($chargingByUsegt, $valueToUseInQuery) = each($chargingByValue);
			$valueFieldName = 'balance.totals.' . $chargingBy . '.' . $chargingByUsegt;
		}

		// Get the balance with the current value field.
		$query[$valueFieldName]['$exists'] = 1;
		
		$update = $this->getUpdateBalanceQuery($balancesColl, 
											   $query, 
											   $chargingBy,
											   $chargingByUsegt,
											   $valueFieldName, 
									           $valueToUseInQuery, 
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
	 * Return the part of the query for setOnInsert
	 * @param type $chargingBy
	 * @param array $defaultBalance
	 * @param string $valueToUseInQuery - The value name of the balance.
	 * @param string $valueFieldName - The name of the field to be set.
	 * @return type
	 */
	protected function getSetOnInsert($chargingBy,
									  $chargingByUsegt,
									  $valueFieldName, 
									  $valueToUseInQuery, 
									  $defaultBalance) {
		$defaultBalance['charging_by'] = $chargingBy;
		$defaultBalance['charging_by_usegt'] = $chargingByUsegt;
		$defaultBalance[$valueFieldName] = $valueToUseInQuery;
		return array(
			'$setOnInsert' => $defaultBalance,
		);
	}

	/**
	 * Get the set part of the query.
	 * @param string $valueToUseInQuery - The value name of the balance.
	 * @param string $valueFieldName - The name of the field to be set.
	 * @param MongoDate $toTime - Expiration date.
	 */
	protected function getSetQuery($valueToUseInQuery, $valueFieldName, $toTime) {
		$valueUpdateQuery = array();
		$queryType = $this->isIncrement ? '$inc' : '$set';
		$valueUpdateQuery[$queryType]
			[$valueFieldName] = $valueToUseInQuery;
		$valueUpdateQuery[$queryType]
			['to'] = $toTime;
		
		return $valueUpdateQuery;
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
