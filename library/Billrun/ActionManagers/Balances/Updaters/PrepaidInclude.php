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
		$subscriber = $this->getSubscriber($subscriberId, $prepaidRecord);	
		
		// Subscriber was not found.
		if($subscriber->isEmpty()) {
			Billrun_Factory::log("Updating by prepaid include failed to get subscriber id: " . $subscriberId, Zend_Log::ERR);
			return false;
		}
		
		// Create a default balance record.
		$defaultBalance = $this->getDefaultBalance($subscriber, $prepaidRecord);
		
		return $this->updateBalance($prepaidRecord['charging_by_usegt'], 
								    $prepaidRecord['charging_by'], 
									$query, 
									$defaultBalance, 
									$recordToSet['to'],
									$recordToSet['value']);
	}
	
	/**
	 * Update a single balance.
	 * @param type $chargingBy
	 * @param type $chargingByValue
	 * @param type $query
	 * @param type $balancesColl
	 * @return type
	 */
	protected function updateBalance($chargingBy, $chargingByValue, $query, $defaultBalance, $toTime, $value) {
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$valueFieldName = array();
		
		// TODO: What if total cost?
		$valueFieldName= 'balance.totals.' . $chargingBy . '.' . $chargingByValue;

		$this->handleZeroing($query, $balancesColl);
		
		$valueUpdateQuery = array();
		$queryType = $this->isIncrement ? '$inc' : '$set';
		$valueUpdateQuery[$queryType]
				   [$valueFieldName] = $value;
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
	 * Handle zeroing the record if the charging value is positive.
	 * @param type $query
	 * @param type $balancesColl
	 * @todo - This is suplicated in chargingPlan updater, should make more generic.
	 */
	protected function handleZeroing($query, $balancesColl) {
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
		$defaultBalance['sid']   = $subscriber->{'sid'};
		$defaultBalance['aid']   = $subscriber->{'aid'};
		$defaultBalance['current_plan'] = $this->getPlanRefForSubscriber($subscriber);
		$defaultBalance['charging_type'] = $subscriber->{'charging_type'};
		$defaultBalance['charging_by'] = $prepaidRecord->{'charging_by'};
		$defaultBalance['charging_by_usaget'] = $prepaidRecord->{'charging_by_usaget'};
		// TODO: This is not the correct way, priority needs to be calculated.
		$defaultBalance['priority'] = 1;
	}
}
