<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of Updater
 *
 * @author tom
 */
abstract class Billrun_ActionManagers_Balances_Updaters_Updater {
	
	protected $isIncrement = true;
	
	/**
	 * Create a new instance of the updater class.
	 */
	public function __construct($increment = true) {
		$this->isIncrement = $increment;
	}

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
	 * Get the record plan according to the input query.
	 * @param type $query
	 * @param type $plansCollection
	 * @return type
	 */
	protected function getPlanRecord($query, $plansCollection) {
		$planQuery = $this->getPlanQuery($query);
		
		// TODO: Use the plans DB/API proxy.
		$planRecord = $plansCollection->query($planQuery)->cursor()->current();
		if(!$planRecord || $planRecord->isEmpty()) {
			// TODO: Report error.
			return null;
		}
		
		return $planRecord;
	}
	
		
	/**
	 * Update the balances.
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 */
	public abstract function update($query, $recordToSet, $subscriberId);
	
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
}
