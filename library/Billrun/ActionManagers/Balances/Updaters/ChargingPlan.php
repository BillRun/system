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
class Billrun_ActionManagers_Balances_Updaters_ChargingPlan extends Billrun_ActionManagers_Balances_Updaters_Updater{
	
	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		// TODO: This function is free similar to the one in ID, should refactor code to be more generic.
		$chargingPlansCollection = Billrun_Factory::db()->chargingPlansCollection();
		$chargingPlanRecord = $this->getPlanRecord($query, $chargingPlansCollection);
		if(!$chargingPlanRecord) {
			Billrun_Factory::log("Failed to get plan record to update balance query: " . $query, Zend_Log::ERR);
			return false;
		}
		
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId, $chargingPlanRecord);	
		
		// Subscriber was not found.
		if($subscriber->isEmpty()) {
			Billrun_Factory::log("Updating by charging plan failed to get subscriber id: " . $subscriberId, Zend_Log::ERR);
			return false;
		}
		
		if(!$this->validateServiceProviders($subscriberId, $recordToSet)) {
			return false;
		}
		
		// Create a default balance record.
		$defaultBalance = $this->getDefaultBalance($subscriber, $chargingPlanRecord, $recordToSet, $chargingPlansCollection);
		
		$this->handleExpirationDate($recordToSet, $subscriberId);
		
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		
		// TODO: What if empty?
		$balancesArray = $chargingPlanRecord['include'];
		
		$balancesToReturn = array();
		// Go through all charging possibilities. 
		foreach ($balancesArray as $chargingBy => $chargingByValue) {
			$balancesToReturn[] = 
				$this->updateBalance($chargingBy, $chargingByValue, $query, $chargingPlanRecord, $balancesColl, $defaultBalance, $recordToSet['to']);
		}
		
		return $balancesToReturn;
	}
	
	/**
	 * Handle zeroing the record if the charging value is positive.
	 * @param type $query
	 * @param type $balancesColl
	 */
	protected function handleZeroing($query, $balancesColl) {
		// User requested incrementing, check if to reset the record.
		if(!$this->isIncrement) {
			return;
		}
		
		$zeroingQuery = $query;
		$zeriongUpdate = array();
		$zeroingQuery[$valueFieldName] = array('$gt' => 0);
		$zeriongUpdate['$set'][$valueFieldName] = 0;
		$originalBeforeZeroing= $balancesColl->findAndModify($zeroingQuery, $zeriongUpdate);
		// TODO: Save the original balance in log somewhere.
	}
	
	/**
	 * Update a single balance.
	 * @param type $chargingBy
	 * @param type $chargingByValue
	 * @param type $query
	 * @param type $balancesColl
	 * @return type
	 */
	protected function updateBalance($chargingBy, $chargingByValue, $query, $balancesColl, $defaultBalance, $toTime) {
		$valueFieldName = array();
		$valueToUseInQuery = null;
		$chargingByUsegt = $chargingBy;
		
		if(!is_array($chargingByValue)){
			$valueFieldName= 'balance.' . $chargingBy;
			$valueToUseInQuery = $chargingByValue;
		}else{
			list($chargingByUsegt, $valueToUseInQuery)= each($chargingByValue);
			$valueFieldName= 'balance.totals.' . $chargingBy . '.' . $chargingByUsegt;
		}
		
		$this->handleZeroing($query, $balancesColl);
		
		$valueUpdateQuery = array();
		$queryType = $this->isIncrement ? '$inc' : '$set';
		$valueUpdateQuery[$queryType]
				   [$valueFieldName] = $valueToUseInQuery;
		$valueUpdateQuery[$queryType]
				   ['to'] = $toTime;
				
		$update = array_merge($this->getSetOnInsert($chargingBy, $chargingByUsegt, $defaultBalance), $valueUpdateQuery);

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
	 * @return type
	 */
	protected function getSetOnInsert($chargingBy, $chargingByUsegt, $defaultBalance) {
		$defaultBalance['charging_by'] = $chargingBy;
		$defaultBalance['charging_by_usegt'] = $chargingByUsegt;
		return array(
			'$setOnInsert' => $defaultBalance,
		);
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
		if(!$to) {
			$to = $this->getDateFromDataRecord($chargingPlanRecord);
		}
		
		$defaultBalance['to']    = $to;
		$defaultBalance['sid']   = $subscriber->{'sid'};
		$defaultBalance['aid']   = $subscriber->{'aid'};
		
		// Get the ref to the subscriber's plan.
		$planName = $subscriber->{'plan'};
		$plansCollection = Billrun_Factory::db()->plansCollection();
		
		// TODO: Is this right here to use the now time or should i use the times from the charging plan?
		$plansQuery = array("name" => $planName,
							"to"   => array('$gt', $nowTime),
							"from" => array('$lt', $nowTime));
		$planRecord = $plansCollection->query($plansQuery)->cursor()->current();
		$defaultBalance['current_plan'] = $planRecord->createRef($plansCollection);
		$defaultBalance['charging_type'] = $subscriber->{'charging_type'};
		// This is being set outside of this function!!!
		//$defaultBalance['charging_by_usaget'] = 
		// TODO: This is not the correct way, priority needs to be calculated.
		$defaultBalance['priority'] = 1;
	}
}
