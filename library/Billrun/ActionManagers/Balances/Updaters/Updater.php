<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of Updater
 *
 * @author Tom Feigin
 */
abstract class Billrun_ActionManagers_Balances_Updaters_Updater{

	const UNLIMITED_DATE = "30 December 2099";
	
	/**
	 * If true then the values in mongo are updated by incrementation,
	 * if false then the values in the mongo are forceablly set.
	 * @var boolean. 
	 */
	protected $isIncrement = true;

	/**
	 * Any request for balance incrementation when "$ignoreOveruse" value is true and the current account balance queried
	 * exceeds the maximum allowance (balance is above zero), will reset the balance (to zero) and only then increment it.
	 * This means that if the user had a positive balance e.g 5 and then was loaded with 100 units, the blance will be -100 and not -95.
	 * @var boolean 
	 */
	protected $ignoreOveruse = true;

	protected $error = "";
	
	/**
	 * Create a new instance of the updater class.
	 * @param array $options - Holding:
	 * 						   increment - If true then the values in mongo are updated by incrementation,
	 * 									   if false then the values in the mongo are forceablly set.
	 * 						   zero - If requested to update by incrementing but the existing 
	 * 								  value is larger than zero than zeroise the value.
	 */
	public function __construct($options) {
		// If it is not set, the default is used.
		if (isset($options['increment'])) {
			$this->isIncrement = $options['increment'];
		}

		// If it is not set, the default is used.
		if (isset($options['zero'])) {
			$this->ignoreOveruse = $options['zero'];
		}
	}
	
	/**
	 * Report a log error and store the message reported.
	 * @param string $error
	 * @param Zend_Log_Filter_Priority $errorLevel
	 */
	protected function reportError($error, $errorLevel) {
		$this->error = $error;
		Billrun_Factory::log($error, $errorLevel);
	}
	
	/**
	 * Get the current error of this updater
	 * @return string current error.
	 */
	public function getError() {
		return $this->error;
	}
	
	/**
	 * TODO: This kind of translator might exist, but if it does we need a more generic way. Best if not needed at all.
	 * Update the field names to fit what is in the mongo.
	 * @param type $query - Record to be update in the db.
	 * @param type $translationTable - Table to use to translate the values.
	 */
	protected function translateFieldNames($query, $translationTable) {
		$translatedQuery = array();
		foreach ($translationTable as $oldName => $newName) {
			if (isset($query[$oldName])) {
				$translatedQuery[$newName] = $query[$oldName];
			}
		}

		return $translatedQuery;
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
//		Billrun_Factory::log("Before zeroing: " . print_r($originalBeforeZeroing, 1), Zend_Log::INFO);
	}
	
	/**
	 * Get the query to run on the collection in mongo.
	 * @param type $query Input query to proccess.
	 * @param $fieldNamesTranslate - Array to translate the names from input format to mongo format.
	 * @return type Query to run on the collection.
	 */
	protected function buildQuery($query, $fieldNamesTranslate) {
		// Single the type to be charging.
		$planQuery = array(
			'to' => array(
				'$gt' => new MongoDate()
			),
			'from' => array(
				'$lt' => new MongoDate()
			)
		);

		// Fix the update record field names.
		return array_merge($this->translateFieldNames($query, $fieldNamesTranslate), $planQuery);
	}

	/**
	 * Get the record according to the input query.
	 * @param type $query
	 * @param type $collection - Mongo collection
	 * @param array $fieldNamesTranslate - Array to translate the names from input format to mongo format.
	 * @return type
	 */
	protected function getRecord($query, $collection, $fieldNamesTranslate=null) {
		$queryToUse = $this->buildQuery($query, $fieldNamesTranslate);

		// TODO: Use the plans DB/API proxy.
		$record = $collection->query($queryToUse)->cursor()->current();
		if (!$record || $record->isEmpty()) {
			$error = "Could not find record. Query:[" . print_r($queryToUse, 1) . "]";
			$this->reportError($error, Zend_Log::ALERT);
			return null;
		}

		return $record;
	}
	
	/**
	 * Get the ref to the monfo plan for the subscriber.
	 * @param type $subscriber
	 * @return type
	 */
	protected function getPlanRefForSubscriber($subscriber) {
		// TODO: This function should be more generic. Or move the implementation into subscriber.
		// Get the ref to the subscriber's plan.
		$planName = $subscriber['plan'];
		$plansCollection = Billrun_Factory::db()->plansCollection();

		// TODO: Is this right here to use the now time or should i use the times from the charging plan?
		$nowTime = new MongoDate();
		$plansQuery = array("name" => $planName,
			"to" => array('$gt', $nowTime),
			"from" => array('$lt', $nowTime));
		$planRecord = $plansCollection->query($plansQuery)->cursor()->current();

		return $plansCollection->createRefByEntity($planRecord);
	}

	/**
	 * Update the balances.
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 * @return The updated record, false if failed.
	 */
	public abstract function update($query, $recordToSet, $subscriberId);

	/**
	 * Get billrun subscriber instance.
	 * @param type $subscriberId If of the subscriber to load.
	 */
	protected function getSubscriber($subscriberId) {
		// Get subscriber query.
		$subscriberQuery = $this->getSubscriberQuery($subscriberId);
		
		$coll = Billrun_Factory::db()->subscribersCollection();
		$results = $coll->query($subscriberQuery)->cursor()->limit(1)->current();
		if ($results->isEmpty()) {
			$error = "Subscriber not found for balance";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		return $results->getRawData();
	}

	/**
	 * Get a subscriber query to get the subscriber.
	 * @param type $subscriberId - The ID of the subscriber.
	 * @return type Query to run.
	 */
	protected function getSubscriberQuery($subscriberId) {
		$query = Billrun_Util::getDateBoundQuery();
		$query['sid'] = $subscriberId;
		
		return $query;
	}

	/**
	 * Handle logic around setting the expiration date.
	 * @param type $recordToSet
	 * @param type $dataRecord
	 */
	protected function handleExpirationDate(&$recordToSet, $dataRecord) {
		if (!$recordToSet['to']) {
			$recordToSet['to'] = $this->getDateFromDataRecord($dataRecord);
		}
	}

	/**
	 * Get a mongo date object based on charging plan record.
	 * @param type $chargingPlan
	 * @return \MongoDate
	 */
	protected function getDateFromDataRecord($chargingPlan) {
		if (!isset($chargingPlan['period'])) {
			return;
		}
		$period = $chargingPlan['period'];
		return $this->getDateFromPeriod($period);
	}
	
	/**
	 * Get a mongo date object based on a period object.
	 * @param period $period
	 * @return \MongoDate
	 * @todo Create a period object.
	 */
	protected function getDateFromPeriod($period) {
		if ($period instanceof MongoDate) {
			return $period;
		}
		$duration = $period['duration'];
		// If this plan is unlimited.
		// TODO: Move this logic to a more generic location
		if($duration == "UNLIMITED") {
			return new MongoDate(strtotime(self::UNLIMITED_DATE));
		}
		if (isset($period['units'])) {
			$unit = $period['units'];
		} else if (isset($period['unit'])) {
			$unit = $period['unit'];
		} else {
			$unit = 'months';
		}
		return new MongoDate(strtotime("tomorrow", strtotime("+ " . $duration . " " . $unit)));
	}

	/**
	 * Check with the mongo that the service provider is trusted.
	 * @param string $serviceProvider - Service provider to test.
	 * @return boolean true if trusted.
	 * @todo Move this logic to a more generic location.
	 */
	protected function isServiceProvider($serviceProvider) {
		$collection = Billrun_Factory::db()->serviceprovidersCollection();
		$query = array('name' => $serviceProvider);
		return $collection->exists($query);
	}
	
	/**
	 * Validate the service provider fields.
	 * @param type $subscriber
	 * @param type $planRecord
	 * @return boolean
	 */
	protected function validateServiceProviders($subscriber, $planRecord) {
		$planServiceProvider = $planRecord['service_provider'];
		
		// Check that the service provider is trusted.
		if(!$this->isServiceProvider($planServiceProvider)) {
			$error = "Received unknown service provider: $planServiceProvider";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		// Get the service provider to check that it fits the subscriber's.
		$subscriberServiceProvider = $subscriber['service_provider'];

		// Check if mismatching serivce providers.
		if ($planServiceProvider != $subscriberServiceProvider) {
			$error = "Failed updating balance! mismatching service providers: subscriber: $subscriberServiceProvider plan: $planServiceProvider";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}

		return true;
	}

	/**
	 * Return the part of the query for setOnInsert
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param array $defaultBalance
	 * @return type
	 */
	protected function getSetOnInsert($wallet, $defaultBalance) {
		if (!isset($defaultBalance['to'])) {
			$defaultBalance['to'] = $this->getDateFromPeriod($wallet->getPeriod());
		}
		$defaultBalance['charging_by'] = $wallet->getChargingBy();
		$defaultBalance['charging_by_usaget'] = $wallet->getChargingByUsaget();
		$defaultBalance['charging_by_usaget_unit'] = $wallet->getChargingByUsagetUnit();
		$defaultBalance['pp_includes_name'] = $wallet->getPPName();
		$defaultBalance['pp_includes_external_id'] = $wallet->getPPID();
		$defaultBalance[$wallet->getFieldName()] = $wallet->getValue();
		return array(
			'$setOnInsert' => $defaultBalance,
		);
	}

	/**
	 * Get the set part of the query.
	 * @param string $chargingPlan - The wallet in use.
	 * @param MongoDate $toTime - Expiration date.
	 */
	protected function getSetQuery($chargingPlan, $toTime) {
		$valueUpdateQuery = array();
		$valueToUseInQuery = $chargingPlan->getValue();
		$queryType = (!is_string($valueToUseInQuery) && $this->isIncrement) ? '$inc' : '$set';
		$valueUpdateQuery[$queryType]
			[$chargingPlan->getFieldName()] = $valueToUseInQuery;
		
		// The TO time is always set.
		$valueUpdateQuery['$set']['to'] = $toTime;
		$valueUpdateQuery['$set']['pp_includes_name'] = $chargingPlan->getPPName();
		$valueUpdateQuery['$set']['pp_includes_external_id'] = $chargingPlan->getPPID();
			
		return $valueUpdateQuery;
	}
	
}
