<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using charging plans.
 *
 */
class Billrun_ActionManagers_Balances_Updaters_Id extends Billrun_ActionManagers_Balances_Updaters_Updater {

	protected $type = 'MongoId';
	protected $balancesRecord = null;

	/**
	 * Get the subscriber by the update parameters.
	 * @param int $subscriberId - The subscriber's ID
	 * @param Mongo Record $recordToSet - The record to be set with the update function.
	 * @return false if error, subscriber element if successful
	 */
	protected function handleSubscriber($subscriberId, $recordToSet) {
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId);

		// Subscriber was not found.
		if (!$subscriber) {
			$errorCode =  3;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		if (!$this->validateServiceProviders($subscriberId, $recordToSet)) {
			return false;
		}

		return $subscriber;
	}

	/**
	 * Validate the service provider fields.
	 * @param type $subscriber
	 * @param type $planRecord
	 * @return boolean
	 */
	protected function validateServiceProviders($subscriber, $planRecord) {
		if (!isset($planRecord['service_provider'])) {
			// Return true if no service provider is supplied
			return true;
		}

		return parent::validateServiceProviders($subscriber, $planRecord);
	}

	/**
	 * Get the query to be used to find a record by ID.
	 * @param array $query - Query with the _id value
	 * @return \MongoId
	 */
	protected function getIDQuery($query) {
		// Convert the string ID to mongo ID.
		$id = $query['_id'];
		$mongoId = new MongoId($id);
		return array("_id" => $mongoId);
	}

	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 * @return The updated record, false if failed.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		$coll = Billrun_Factory::db()->balancesCollection();

		// Convert the string ID to mongo ID.
		$idQuery = $this->getIDQuery($query);

		$this->getBalanceRecord($coll, $idQuery);
		if (!$this->balancesRecord) {
			$errorCode =  2;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$subscriber = $this->handleSubscriber($subscriberId, $recordToSet);
		if ($subscriber === false) {
			return false;
		}

		$this->handleExpirationDate($recordToSet, $subscriberId);

		$updateResult = $this->updateBalance($idQuery, $coll, $recordToSet);
		$updatedBalance = $updateResult[0]['balance'];
		$updateResult[0]['source'] = $coll->createRefByEntity($updatedBalance);
		$updateResult[0]['subscriber'] = $subscriber;
		return $updateResult;
	}

	/**
	 * Get the record from the balance collection.
	 * @param type $balancesColl
	 * @param type $query
	 * @return type
	 */
	protected function getBalanceRecord($balancesColl, $query) {
		$cursor = $balancesColl->query($query)->cursor();

		// Find the record in the collection.
		$balanceRecord = $cursor->current();

		if (!$balanceRecord || $balanceRecord->isEmpty()) {
			$errorCode =  4;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return;
		}

		$this->balancesRecord = $balanceRecord;
	}

	/**
	 * Get the wallet for a data/sms type.
	 * @param array $ppPair - The prepaid include pair.
	 * @param type $valueToUseInQuery
	 * @return \Billrun_DataTypes_Wallet
	 */
	protected function getUsagetWallet($ppPair, $valueToUseInQuery) {
		@list($chargingBy, $chargingByValue) = each($this->balancesRecord['balance']['totals']);
		list($chargingByValueName, $value) = each($chargingByValue);

		if ($valueToUseInQuery !== false) {
			$chargingByValue[$chargingByValueName] = $valueToUseInQuery;
		} else {
			$valueToUseInQuery = $value;
		}

		return new Billrun_DataTypes_Wallet($chargingBy, $chargingByValue, $ppPair);
	}

	/**
	 * Get the wallet for the balance update
	 * @param array $recordToSet - Record to be set in the update.
	 * @return \Billrun_DataTypes_Wallet
	 */
	protected function getWallet($recordToSet) {
		@list($chargingBy, $chargingByValue) = each($this->balancesRecord['balance']);

		$valueToUseInQuery = false;
		if (isset($recordToSet['value'])) {
			$valueToUseInQuery = $recordToSet['value'];
		}

		$ppPair['pp_includes_external_id'] = $this->balancesRecord['pp_includes_external_id'];
		$ppPair['pp_includes_name'] = $this->balancesRecord['pp_includes_name'];
		$ppPair['unlimited'] = !empty($this->balancesRecord['unlimited']);

		if (is_array($chargingByValue)) {
			return $this->getUsagetWallet($ppPair, $valueToUseInQuery);
		}

		if ($valueToUseInQuery !== false) {
			$chargingByValue = $valueToUseInQuery;
		} else {
			$valueToUseInQuery = $chargingByValue;
		}
		return new Billrun_DataTypes_Wallet($chargingBy, $chargingByValue, $ppPair);
	}

	/**
	 * Update a single balance.
	 * @param type $query
	 * @param type $balancesColl
	 * @return Array with the wallet as the key and the Updated record as the value
	 */
	protected function updateBalance($query, $balancesColl, $recordToSet) {
		// Find the record in the collection.
		if (!$this->balancesRecord) {
			$errorCode =  5;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$usedWallet = $this->getWallet($recordToSet);

		// Set the old record
		$this->balanceBefore[$usedWallet->getPPID()] = $this->balancesRecord;

		$queryType = $this->updateOperation ? '$set' : '$inc';
		$valueFieldName = $usedWallet->getFieldName();
		$valueUpdateQuery = array();
		$valueUpdateQuery[$queryType][$valueFieldName] = $usedWallet->getValue();
		$to = $recordToSet['to'];
		if (is_array($to) && isset($to['sec'])) {
			$to = new Mongodloid_Date($to['sec']);
		} else if (is_object($to) && isset($to->sec)) {
			$to = new Mongodloid_Date($to->sec);
		}
		$valueUpdateQuery['$set']['to'] = $to;

		$options = array(
			// We do not want to upsert if trying to update by ID.
			'upsert' => false,
			'new' => true,
		);

		$query['sid'] = $this->balancesRecord['sid']; // used for sharded cluster
		$balance = $balancesColl->findAndModify($query, $valueUpdateQuery, array(), $options, true);
		// Return the new document.
		return array(array('wallet' => $usedWallet, 'balance' => $balance));
	}

}
