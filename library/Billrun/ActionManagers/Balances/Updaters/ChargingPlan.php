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
		$plansCollection = Billrun_Factory::db()->plansCollection();
		$planRecord = $this->getPlanRecord($query, $plansCollection);
		if(!$planRecord) {
			// TODO: Report error.
			return false;
		}
		
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId, $planRecord);	
		
		// Subscriber was not found.
		if($subscriber->isEmpty()) {
			// TODO: Report error
			return false;
		}
		
		if(!$this->validateServiceProviders($subscriberId, $recordToSet)) {
			return false;
		}
		
		// Create a default balance record.
		$defaultBalance = $this->getDefaultBalance($subscriber, $planRecord, $recordToSet, $plansCollection);
		
		$this->handleExpirationDate($recordToSet, $subscriberId);
		
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		
		// TODO: What if empty?
		$balancesArray = $planRecord['include'];
		
		$balancesToReturn = array();
		// Go through all charging possibilities. 
		foreach ($balancesArray as $chargingBy => $chargingByValue) {
			$balancesToReturn[] = 
				$this->updateBalance($chargingBy, $chargingByValue, $query, $planRecord, $balancesColl, $defaultBalance, $recordToSet['to']);
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
		
		if(!is_array($chargingByValue)){
			$valueFieldName= 'balance.' . $chargingBy;
			$valueToUseInQuery = $chargingByValue;
		}else{
			list($chargingByValueName, $value)= each($chargingByValue);
			$valueFieldName= 'balance.totals.' . $chargingBy . '.' . $chargingByValueName;
			$valueToUseInQuery = $value;
			$chargingBy=$chargingByValueName;
		}

		$this->handleZeroing($query, $balancesColl);
		
		$valueUpdateQuery = array();
		$queryType = $this->isIncrement ? '$inc' : '$set';
		$valueUpdateQuery[$queryType]
				   [$valueFieldName] = $valueToUseInQuery;
		$valueUpdateQuery[$queryType]
				   ['to'] = $toTime;
				
		$update = array_merge($this->getSetOnInsert($chargingBy, $defaultBalance), $valueUpdateQuery);

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
	protected function getSetOnInsert($chargingBy, $defaultBalance) {
		$defaultBalance['charging_by'] = $chargingBy;
		return array(
			'$setOnInsert' => $defaultBalance,
		);
	}
	
	/**
	 * Get a default balance record, without charging by.
	 * @param type $subscriber
	 * @param type $planRecord
	 * @param type $recordToSet
	 */
	protected function getDefaultBalance($subscriber, $planRecord, $recordToSet, $plansCollection) {
		$defaultBalance = array();
		$defaultBalance['from'] = new MongoDate();
		
		$to = $recordToSet['to'];
		if(!$to) {
			$to = $this->getDateFromChargingPlan($planRecord);
		}
		
		$defaultBalance['to']    = $to;
		$defaultBalance['sid']   = $subscriber->{'sid'};
		$defaultBalance['aid']   = $subscriber->{'aid'};
		$defaultBalance['current_plan'] = $planRecord->createRef($plansCollection);
		$defaultBalance['charging_type'] = $subscriber->{'charging_type'};
		$defaultBalance['charging_by_usaget'] = 
		// TODO: This is not the correct way, priority needs to be calculated.
		$defaultBalance['priority'] = 1;
	}
}
