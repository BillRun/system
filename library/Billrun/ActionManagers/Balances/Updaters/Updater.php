<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of Updater
 *
 */
abstract class Billrun_ActionManagers_Balances_Updaters_Updater {

	use Billrun_FieldValidator_ServiceProvider;
	use Billrun_ActionManagers_ErrorReporter;

	const UNLIMITED_DATE = "30 December 2099";

	/**
	 * Object responsible for handling the update operation
	 * @var Billrun_Balances_Update_Operation
	 */
	protected $updateOperation;

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
		$this->updateOperation = $options['operation'];
		
		// TODO: This will change, it's only here while this logic is executed
		// in the backend instead of the front end.
		$this->baseCode = 1200;
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
			$errorCode =  11;
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
			$errorCode =  23;
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
			'multiple' => true
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

		$coll = Billrun_Factory::db()->subscribersCollection()->setReadPreference('RP_PRIMARY', array());
		
		$results = $coll->query($subscriberQuery)->cursor()->sort(array('from' => 1))->limit(1)->current();
		if ($results->isEmpty()) {
			$errorCode =  12;
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
		$query = Billrun_Utils_Mongo::getDateBoundQuery(time(), true); // enable upsert of future subscribers balances
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
		return Billrun_Utils_Mongo::getDateFromPeriod($period);
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
			$errorCode =  20;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// Get the service provider to check that it fits the subscriber's.
		$subscriberServiceProvider = $subscriber['service_provider'];

		// Check if mismatching serivce providers.
		if ($planServiceProvider != $subscriberServiceProvider) {
			$errorCode =  13;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		return true;
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
		$this->updateOperation->setToForUpdate($update, $to, $balanceRecord);
	}

	/**
	 * method to check if wallet get to max value on update
	 * 
	 * @param string $planName
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param type $query
	 * 
	 * @return array ["onError"=>errorCode] if error occured, or ["block"=>boolean]
	 * indicating if should be blocked.
	 */
	protected function handleUnlimitedBalance($planName, $wallet, $query) {
		$max = $this->getBalanceMaxValue($planName, $wallet->getPPID());
		
		$handleResult = $this->updateOperation->handleUnlimitedBalance($max, $wallet, $query);
		if(isset($handleResult['onError'])) {
			$this->reportError($handleResult['onError']);
			return false;
		}
		
		if(isset($handleResult['block']) && $handleResult['block']) {
			// [Balances Error 1225]
			$errorCode =  25;
			$this->reportError($errorCode);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Store the balance value before updating.
	 * @param array $query - Query to get the balance before update.
	 * @param Mongodloig_Collection $balancesColl - The balances collection.
	 * @return Mongodloid_Entity - Balance stored before the update.
	 */
	protected function storeBalanceBeforeUpdate($query, $balancesColl) {
		$balance = $balancesColl->query($query)->cursor()->current();
		$this->balanceBefore[$query['pp_includes_external_id']] = $balance;
		return $balance;
	}
	
	/**
	 * Update a single balance.
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param array $query
	 * @param array $defaultBalance
	 * @param MongoDate $toTime
	 * @return Array with the wallet as the key and the Updated record as the value.
	 */
	protected function updateBalance($wallet, $query, $defaultBalance, $toTime) {
		$balancesColl = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY', array());

		$balanceQuery = array_merge($query, Billrun_Utils_Mongo::getDateBoundQuery());
		$balance = $this->storeBalanceBeforeUpdate($balanceQuery, $balancesColl);

		$isExisting = $balance && (!$balance->isEmpty());
		$update = $this->updateOperation->getUpdateBalanceQuery($balanceQuery, $wallet, $defaultBalance, $isExisting);

		$this->setToForUpdate($update, $toTime, $wallet);
		
		$options = array(
			'upsert' => true,
			'new' => true,
		);

		return $this->updateOperation->update($balancesColl, $balanceQuery, $update, $options);
	}
}
