<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of Updater
 *
 */
abstract class Billrun_ActionManagers_Balances_Updaters_Updater extends Billrun_ActionManagers_APIAction {

	use Billrun_FieldValidator_ServiceProvider;

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

	/**
	 * Indicator for updating a balance by periodic charge.
	 * @var boolean indication
	 */
	protected $recurring = false;

	/**
	 * The document before the balance update.
	 * @var type 
	 */
	protected $balanceBefore = null;

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

		// Get the balances errors.
		if (isset($options['errors'])) {
			$this->errors = $options['errors'];
		}

		// Check for recurring.
		if (isset($options['recurring'])) {
			$this->recurring = true;
		}
	}

	public function getType() {
		return $this->type;
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
		if (!$this->ignoreOveruse || !$this->isIncrement) {
			return;
		}

		$zeroingQuery = $query;
		$zeriongUpdate = array();
		$zeroingQuery[$valueFieldName] = array('$gt' => 0);
		$zeriongUpdate['$set'][$valueFieldName] = 0;
		$originalBeforeZeroing = $balancesColl->findAndModify($zeroingQuery, $zeriongUpdate);
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
				'$lte' => new MongoDate()
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
	protected function getRecord($query, $collection, $fieldNamesTranslate = null) {
		$queryToUse = $this->buildQuery($query, $fieldNamesTranslate);

		// TODO: Use the plans DB/API proxy.
		$record = $collection->query($queryToUse)->cursor()->current();
		if (!$record || $record->isEmpty()) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 11;
			$this->reportError($errorCode, Zend_Log::NOTICE);
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
			"from" => array('$lte', $nowTime));
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
	 * Get the max value for the balance
	 * @param string $plan - Plan name
	 * @param numeric $prepaidID - PP ID
	 * @return false if error, or the max value on success.
	 */
	protected function getBalanceMaxValue($plan, $prepaidID) {
		$plansColl = Billrun_Factory::db()->plansCollection();
		$maxQuery = array("type" => "customer", "name" => $plan);
		$maxRecord = $plansColl->query($maxQuery)->cursor()->current();

		if ($maxRecord->isEmpty()) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 23;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($plan));
			return false;
		}

		if (!isset($maxRecord['pp_threshold'][$prepaidID])) {
			return 0;
		}

		return $maxRecord['pp_threshold'][$prepaidID];
	}

	/**
	 * Normalize the balance ensuring the max charge
	 * @param array $query - Query to get the balance.
	 * @param string $plan - Plan name.
	 * @param Billrun_DataTypes_Wallet $wallet - Wallet of the current update
	 * @return true if successful.
	 */
	protected function normalizeBalance($query, $plan, $wallet) {
		$forceMaxValue = true;
		
		// Check if the value to set is negative, if so we force minimum value.
		if ($wallet->getValue() >= 0) {
			$forceMaxValue = false;
		}

		if(!$forceMaxValue) {
			$maxValue = 0;
		} else {
			$maxValue = $this->getBalanceMaxValue($plan, $wallet->getPPID());
			if ($maxValue === false) {
				return false;
			}
		}

		$query['priority'] = $wallet->getPriority();

		$options = array(
			'upsert' => false,
			'new' => false,
			'multiple' => 1
		);
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		$updateQueryValue = array($wallet->getFieldName() => $maxValue);
		
		if($forceMaxValue) {
			$updateQuery = array('$max' => $updateQueryValue);			
		} else {
			$updateQuery = array('$min' => $updateQueryValue);
		}
		$updateResult = $balancesColl->update($query, $updateQuery, $options);
		$updateResult['max'] = $maxValue;
		
		if($forceMaxValue) {
			$updateResult['min'] = 1;
		}
		return $updateResult;
	}

	/**
	 * Get billrun subscriber instance.
	 * @param type $subscriberId If of the subscriber to load.
	 */
	protected function getSubscriber($subscriberId) {
		// Get subscriber query.
		$subscriberQuery = $this->getSubscriberQuery($subscriberId);

		$coll = Billrun_Factory::db()->subscribersCollection()->setReadPreference(MongoClient::RP_PRIMARY, array());
		;
		$results = $coll->query($subscriberQuery)->cursor()->sort(array('from' => 1))->limit(1)->current();
		if ($results->isEmpty()) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 12;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($subscriberId));
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
		$query = Billrun_Util::getDateBoundQuery(time(), true); // enable upsert of future subscribers balances
		$query['sid'] = $subscriberId;

		return $query;
	}

	/**
	 * Handle logic around setting the expiration date.
	 * @param type $recordToSet
	 * @param type $dataRecord
	 */
	protected function handleExpirationDate(&$recordToSet, $dataRecord) {
		if (!isset($recordToSet['to'])) {
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
		if (isset($period['sec'])) {
			return new MongoDate($period['sec']);
		}

		$duration = $period['duration'];
		// If this plan is unlimited.
		// TODO: Move this logic to a more generic location
		if ($duration == "UNLIMITED") {
			return new MongoDate(strtotime(self::UNLIMITED_DATE));
		}
		if (isset($period['units'])) {
			$unit = $period['units'];
		} else if (isset($period['unit'])) {
			$unit = $period['unit'];
		} else {
			$unit = 'months';
		}
		return new MongoDate(strtotime("tomorrow", strtotime("+ " . $duration . " " . $unit)) - 1);
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
		if (!$this->validateServiceProvider($planServiceProvider)) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 20;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// Get the service provider to check that it fits the subscriber's.
		$subscriberServiceProvider = $subscriber['service_provider'];

		// Check if mismatching serivce providers.
		if ($planServiceProvider != $subscriberServiceProvider) {
			$errorCode = Billrun_Factory::config()->getConfigValue("balances_error_base") + 13;
			$this->reportError($errorCode, Zend_Log::NOTICE);
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
		$defaultBalance['priority'] = $wallet->getPriority();
		$defaultBalance[$wallet->getFieldName()] = $wallet->getValue();

		// Check if recurring.
		if ($this->recurring) {
			$defaultBalance['recurring'] = 1;
		}

		return array(
			'$setOnInsert' => $defaultBalance,
		);
	}

	/**
	 * Get the balance record before the update.
	 * @return type
	 */
	public function getBeforeUpdate() {
		return $this->balanceBefore;
	}
	
	/**
	 * Get the balance record that is being updated
	 * @param Billrun_DataTypes_Wallet $wallet - The wallet for the balance record currently being updated.
	 * @return Mongodloid_Entity
	 */
	protected function getRecordInProccess($wallet) {
		$id = $wallet->getPPID();
		if(!isset($this->balanceBefore[$id])) {
			return null;
		}
		return $this->balanceBefore[$id];
	}
	
	/**
	 * Set the 'To' field to the update query
	 * @param array $update - The update query to set the to for
	 * @param type $to - Time value.
	 * @param type $wallet - Wallet to handle.
	 */
	protected function setToForUpdate(&$update, $to, $wallet) {
		if(Billrun_Util::multiKeyExists($update, 'to')) {
			// to already set let's ignore
			return;
		}
		
		// Check if the value before is 0 and if so take the input values to update.
		$balanceRecord = $this->getRecordInProccess($wallet);
		$valueBefore = abs(Billrun_Balances_Util::getBalanceValue($balanceRecord));
		if($valueBefore > 0) {
			// TODO: Move the $max functionality to a trait
			$update['$max']['to'] = $to;
		} else {
			// TODO: Move the $max functionality to a trait
			$update['$set']['to'] = $to;
		}
	}


	/**
	 * Get the set part of the query.
	 * @param string $chargingPlan - The wallet in use.
	 */
	protected function getSetQuery($chargingPlan) {
		$valueUpdateQuery = array();
		$valueToUseInQuery = $chargingPlan->getValue();
		$queryType = (!is_string($valueToUseInQuery) && $this->isIncrement) ? '$inc' : '$set';
		$valueUpdateQuery[$queryType]
			[$chargingPlan->getFieldName()] = $valueToUseInQuery;

		// The TO time is always set.
		$valueUpdateQuery['$set']['pp_includes_name'] = $chargingPlan->getPPName();
		$valueUpdateQuery['$set']['pp_includes_external_id'] = $chargingPlan->getPPID();
		$valueUpdateQuery['$set']['priority'] = $chargingPlan->getPriority();

		// Check if recurring.
		if ($this->recurring) {
			$valueUpdateQuery['$set']['recurring'] = 1;
		}

		return $valueUpdateQuery;
	}

	/**
	 * method to check if wallet get to max value on update
	 * 
	 * @param string $planName
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param type $query
	 * 
	 * @return boolean true if get to max value, else false
	 */
	protected function blockMax($planName, $wallet, $query) {
		$max = $this->getBalanceMaxValue($planName, $wallet->getPPID());
		$newValue = $wallet->getValue();
		$valueBefore = 0;

		// Check if passing the max.
		if ($this->isIncrement) {
			$coll = Billrun_Factory::db()->balancesCollection()->setReadPreference(MongoClient::RP_PRIMARY, array());
			$balanceQuery = array_merge($query, Billrun_Util::getDateBoundQuery());
			$balanceBefore = $coll->query($balanceQuery)->cursor()->current();
			if (!$balanceBefore->isEmpty()) {
				$valueBefore = Billrun_Balances_Util::getBalanceValue($balanceBefore);
			}

			$newValue += $valueBefore;
		}

		if (abs($newValue) > abs($max)) { // we're using absolute for both cases - positive and negative values
			return true;
		}

		return false;
	}

}
