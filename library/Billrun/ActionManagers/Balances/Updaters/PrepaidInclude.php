<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using the prepaid include.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Updaters_PrepaidInclude extends Billrun_ActionManagers_Balances_Updaters_Updater {
	
	/**
	 * Get the query to run on the prepaidincludes collection in mongo.
	 * @param type $query Input query to proccess.
	 * @return type Query to run on prepaidincludes collection.
	 */
	protected function getPrepaidIncludeQuery($query) {
		// Single the type to be charging.
		$prepaidRecord = array('to' => array('$gt', new MongoDate()));
		
		$translationTable =
			array('pp_includes_name'        => 'name',
				  'pp_includes_external_id' => 'external_id');
		
		// Fix the update record field names.
		return array_megrge($this->translateFieldNames($query, $translationTable), $prepaidRecord);
	}
	
	/**
	 * Get the prepaid record according to the input query.
	 * @param type $query
	 * @param type $prepaidCollection
	 * @return type
	 */
	protected function getPrepaidIncludeRecord($query, $prepaidCollection) {
		$prepaidQuery = $this->getPrepaidIncludeQuery($query);
		
		// TODO: Use the prepaid DB/API proxy.
		$prepaidRecord = $prepaidCollection->query($prepaidQuery)->cursor()->current();
		if(!$prepaidRecord || $prepaidRecord->isEmpty()) {
			// TODO: Report error.
			return null;
		}
		
		return $prepaidRecord;
	}
	
	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 * @return The updated record, false if failed.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		// If updating by prepaid include the user must specify an expiration date.
		if(!$recordToSet['to']) {
			Billrun_Factory::log("Update balance by prepaid include must receive expiration date", Zend_Log::ERR);
			return false;
		}
		
		// No value is set.
		if(!isset($recordToSet['value'])) {
			Billrun_Factory::log("Update balance by prepaid include must receive value to update", Zend_Log::ERR);
			return false;
		}
		
		// TODO: This function is free similar to the one in ID, should refactor code to be more generic.
		$prepaidIncludes = Billrun_Factory::db()->prepaidIncludesCollection();
		$prepaidRecord = $this->getPrepaidIncludeRecord($query, $prepaidIncludes);
		if(!$prepaidRecord) {
			Billrun_Factory::log("Failed to get prepaid include record", Zend_Log::ERR);
			return false;
		}
		
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId);	
		
		// Subscriber was not found.
		if(!$subscriber) {
			Billrun_Factory::log("Updating by prepaid include failed to get subscriber id: " . $subscriberId, Zend_Log::ERR);
			return false;
		}
		
		// Set subscriber to query.
		$query['sid'] = $subscriber['sid'];
		$query['aid'] = $subscriber['aid'];
		
		
		// Create a default balance record.
		$defaultBalance = $this->getDefaultBalance($subscriber, $prepaidRecord);
		
		$chargingPlan = $this->getPlanObject($prepaidRecord, $recordToSet);
		
		return $this->updateBalance($chargingPlan, 
									$query, 
									$defaultBalance, 
									$recordToSet['to']);
	}
	
	/**
	 * Get the plan object built from the record values.
	 * @param array $prepaidRecord - Prepaid record.
	 * @param array $recordToSet - Record with values to be set.
	 * @return \Billrun_DataTypes_ChargingPlan Plan object built with values.
	 */
	protected function getPlanObject($prepaidRecord, $recordToSet) {
		$chargingBy = $prepaidRecord['charging_by'];
		$chargingByUsaget = $prepaidRecord['charging_by_usaget'];
		if($chargingBy == $chargingByUsaget) {
			$chargingByUsaget = $recordToSet['value'];
		}else{
			$chargingByUsaget = array($chargingByUsaget => $recordToSet['value']);
		}
		
		return new Billrun_DataTypes_ChargingPlan($chargingBy, 
											      $chargingByUsaget);
	}
	
	/**
	 * Get the update balance query. 
	 * @param Mongoldoid_Collection $balancesColl
	 * @param array $query - Query for getting tha balance.
	 * @param Billrun_DataTypes_ChargingPlan $chargingPlan
	 * @param MongoDate $toTime - Expiration date.
	 * @param array $defaultBalance - Default balance to set.
	 * @return array Query for set updating the balance.
	 */
	protected function getUpdateBalanceQuery($balancesColl, 
											 $query, 
											 $chargingPlan,
											 $toTime,
										     $defaultBalance) {
		$update = array();
		// If the balance doesn't exist take the setOnInsert query, 
		// if it exists take the set query.
		if(!$balancesColl->exists($query)) {
			$update = $this->getSetOnInsert($chargingPlan, $defaultBalance);
		} else {
			$this->handleZeroing($query, $balancesColl, $chargingPlan->getFieldName());
			$update = $this->getSetQuery($chargingPlan->getValue(), $chargingPlan->getFieldName(), $toTime);
		}
		
		return $update;
	}
	
	/**
	 * Return the part of the query for setOnInsert
	 * @param Billrun_DataTypes_ChargingPlan $chargingPlan
	 * @param array $defaultBalance
	 * @return array
	 */
	protected function getSetOnInsert($chargingPlan, 
									  $defaultBalance) {
		$defaultBalance['charging_by'] = $chargingPlan->getChargingBy();
		$defaultBalance['charging_by_usegt'] = $chargingPlan->getChargingByUsaget();
		$defaultBalance[$chargingPlan->getFieldName()] = $chargingPlan->getValue();
		return array(
			'$setOnInsert' => $defaultBalance,
		);
	}
	
	/**
	 * Update a single balance.
	 * @param Billrun_DataTypes_ChargingPlan $chargingPlan
	 * @param array $query
	 * @param array $defaultBalance
	 * @param MongoDate $toTime
	 * @return Updated record.
	 */
	protected function updateBalance($chargingPlan, $query, $defaultBalance, $toTime) {
		$balancesColl = Billrun_Factory::db()->balancesCollection();

		// Get the balance with the current value field.
		$query[$chargingPlan->getFieldName()]['$exists'] = 1;
		
		$update = $this->getUpdateBalanceQuery($balancesColl, 
											   $query, 
											   $chargingPlan,
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
	 * Handle zeroing the record if the charging value is positive.
	 * @param type $query
	 * @param type $balancesColl
	 * @todo - This is suplicated in chargingPlan updater, should make more generic.
	 */
	protected function handleZeroing($query, $balancesColl, $valueFieldName) {
		// User requested incrementing, check if to reset the record.
		if(!$this->ignoreOveruse || !$this->isIncrement) {
			return;
		}
		
		$zeroingQuery = $query;
		$zeriongUpdate = array();
		$zeroingQuery[$valueFieldName] = array('$gt' => 0);
		$zeriongUpdate['$set'][$valueFieldName] = 0;
		$originalBeforeZeroing= $balancesColl->findAndModify($zeroingQuery, $zeriongUpdate);
		Billrun_Factory::log("Before zeroing: " . print_r($originalBeforeZeroing, 1), Zend_Log::INFO);
	}
	
	/**
	 * Get a default balance record, without charging by.
	 * @param type $subscriber
	 * @param type $prepaidRecord
	 * @param type $recordToSet
	 */
	protected function getDefaultBalance($subscriber, $prepaidRecord) {
		$defaultBalance = array();
		$defaultBalance['from'] = new MongoDate();
		
		$defaultBalance['to']    = $prepaidRecord['to'];
		$defaultBalance['sid']   = $subscriber['sid'];
		$defaultBalance['aid']   = $subscriber['aid'];
		$defaultBalance['current_plan'] = $this->getPlanRefForSubscriber($subscriber);
		$defaultBalance['charging_type'] = $subscriber['charging_type'];
		$defaultBalance['charging_by'] = $prepaidRecord['charging_by'];
		$defaultBalance['charging_by_usaget'] = $prepaidRecord['charging_by_usaget'];
		// TODO: This is not the correct way, priority needs to be calculated.
		$defaultBalance['priority'] = 1;
		
		return $defaultBalance;
	}
}
