<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using charging plans.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Updaters_ChargingPlan extends Billrun_ActionManagers_Balances_Updaters_Updater{

	/**
	 * TODO: This kind of translator might exist, but if it does we need a more generic way. Best if not needed at all.
	 * Update the field names to fit what is in the mongo.
	 * @param type $query - Record to be update in the db.
	 */
	protected function translateFieldNames($query){
		$fieldNamesTranslate =
			array('charging_plan'			  => 'name',
				  'charging_plan_external_id' => '_id');
		$translatedQuery = array();
		foreach ($fieldNamesTranslate as $oldName => $newName) {
			if(isset($query[$oldName])){
				$translatedQuery[$newName] = $query[$oldName];
			}
		}
		
		return $translatedQuery;
	}
	
	/**
	 * Get a subscriber query to get the subscriber.
	 * @param type $subscriberId - The ID of the subscriber.
	 * @param type $planRecord - Record that holds to and from fields.
	 * @return type Query to run.
	 */
	protected function getSubscriberQuery($subscriberId, $planRecord) {
		// Get subscriber query.
		$subscriberQuery = array('sid' => $subscriberId);
		
		// Add time to query.
		$subscriberQuery['from'] = $planRecord['from'];
		$subscriberQuery['to'] = $planRecord['to'];
		
		return $subscriberQuery;
	}
	
	/**
	 * Get billrun subscriber instance.
	 * @param type $subscriberId If of the subscriber to load.
	 * @param type $dateRecord Array that has to and from fields for the query.
	 */
	protected function getSubscriber($subscriberId, $dateRecord) {
		// Get subscriber query.
		$subscriberQuery = $this->getSubscriberQuery($subscriberId, $dateRecord);
		
		// Get the subscriber.
		return Billrun_Factory::subscriber()->load($subscriberQuery);
	}
	
	/**
	 * Validate the service provider fields.
	 * @param type $subscriber
	 * @param type $planRecord
	 * @return boolean
	 */
	protected function validateServiceProviders($subscriber, $planRecord) {
		// Get the service provider to check that it fits the subscriber's.
		$subscriberServiceProvider = $subscriber->{'service_provider'};
		
		// Check if mismatching serivce providers.
		if($planRecord['service_provider'] != $subscriberServiceProvider) {
			$planServiceProvider = $planRecord['service_provider'];
			Billrun_Factory::log("Failed updating balance! mismatching service prociders: subscriber: $subscriberServiceProvider plan: $planServiceProvider");
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get the query to run on the plans collection in mongo.
	 * @param type $query Input query to proccess.
	 * @return type Query to run on plans collection.
	 */
	protected function getPlanQuery($query) {
		// Single the type to be charging.
		$planQuery = array('type' => 'charging');
		
		// Fix the update record field names.
		return array_megrge($this->translateFieldNames($query), $planQuery);
	}
	
	/**
	 * Handle logic around setting the expiration date.
	 * @param type $recordToSet
	 * @param type $planRecord
	 */
	protected function handleExpirationDate($recordToSet, $planRecord) {
		if(!$recordToSet['to']) {
			$recordToSet['to'] = $this->getDateFromChargingPlan($planRecord);
		}
	}
	
	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		$planQuery = $this->getPlanQuery($query);
		$plansCollection = Billrun_Factory::db()->plansCollection();
		
		// TODO: Use the plans DB/API proxy.
		$planRecord = $plansCollection->query($planQuery)->cursor()->current();
		if(!$planRecord || $planRecord->isEmpty()) {
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
	 * Get a mongo date object based on charging plan record.
	 * @param type $chargingPlan
	 * @return \MongoDate
	 */
	protected function getDateFromChargingPlan($chargingPlan) {
		$period = $chargingPlan['period'];
		$unit = $period['units'];
		$duration = $period['duration'];
		return new MongoDate(strtotime("+ " . $duration . " " . $unit));
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
