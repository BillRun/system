<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Representing an update operation
 *
 * @package  Balances
 * @since    4.5
 */
abstract class Billrun_Balances_Update_Operation {
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
	 * Return an indication for is the 
	 * @return type
	 */
	public function isRecurring() {
		return $this->recurring;
	}
	
	/**
	 * Create a new instance of the operation class.
	 * @param array $options - Holding:
	 * 						   zero - If requested to update by incrementing but the existing 
	 * 								  value is larger than zero than zeroise the value.
	 *						   recurring - Indicating if this is a recurring update by true.
	 */
	public function __construct($options) {
		// If it is not set, the default is used.
		if (isset($options['zero'])) {
			$this->ignoreOveruse = $options['zero'];
		}

		// Check for recurring.
		if (isset($options['recurring'])) {
			$this->recurring = $options['recurring'];
		}
	}
	
	/**
	 * Is an increment operation.
	 * @return boolean true if is increment.
	 */
	public abstract function isIncrement();
	
	/**
	 * Get the mongo operation to execute.
	 * @param mixed $valueToSet - Value to set.
	 * @return string - $inc or $set
	 */
	protected abstract function getMongoOperation($valueToSet);
	
	/**
	 * Set the 'To' field to the update query
	 * @param array $update - The update query to set the to for
	 * @param type $to - Time value.
	 * @param type $balanceRecord - Current balance to update.
	 */
	public function setToForUpdate(&$update, $to, $balanceRecord) {
		// Check if the value before is 0 and if so take the input values to update.
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
	 * 
	 * @param array $query - Query for getting tha balance.
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param boolean $isExisting - If true, a balance exists before the update.
	 * @return array Query for set updating the balance.
	 */
	public function getUpdateBalanceQuery($query, $wallet, $defaultBalance, $isExisting) {
		$balancesColl = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY', array());

		// If the balance doesn't exist take the setOnInsert query, 
		// if it exists take the set query.
		if (!$isExisting) {
			return $this->getSetOnInsert($wallet, $defaultBalance);
		}
		
		$this->handleZeroing($query, $balancesColl, $wallet->getFieldName());
		return $this->getSetQuery($wallet);
	}
	
	/**
	 * Handle zeroing the record if the charging value is positive.
	 * @param type $query
	 * @param type $balancesColl
	 * @todo - This is suplicated in chargingPlan updater, should make more generic.
	 */
	protected function handleZeroing($query, $balancesColl, $valueFieldName) {
		// User requested incrementing, check if to reset the record.
		if (!$this->ignoreOveruse || !$this->isIncrement()) {
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
	 * Return the part of the query for setOnInsert
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param array $defaultBalance
	 * @return type
	 */
	protected function getSetOnInsert($wallet, $defaultBalance) {
		$partialBalance = $wallet->getPartialBalance();
		if (!isset($defaultBalance['to'])) {
			$partialBalance['to'] = Billrun_Utils_Mongo::getDateFromPeriod($wallet->getPeriod());
		}

		// Check if recurring.
		if ($this->recurring) {
			$defaultBalance['recurring'] = 1;
		}

		$balanceRecord = array_merge($defaultBalance, $partialBalance);
		// If the wallet is shared, set the sid
		if($wallet->isShared()) {
			$balanceRecord['sid'] = 0;
		}
		// If the wallet is unlimited, set the 'to' field to unlimited.
		if($wallet->getUnlimited()) {
			$balanceRecord['to'] = new MongoDate(strtotime(Billrun_Utils_Time::UNLIMITED_DATE));
		}
		
		return array(
			'$setOnInsert' => $balanceRecord,
		);
	}
	
	/**
	 * Get the set part of the query.
	 * @param Billrun_DataTypes_Wallet $wallet - The wallet in use.
	 */
	protected function getSetQuery($wallet) {
		$valueUpdateQuery = array();
		$valueToUseInQuery = $wallet->getValue();
		$queryType = $this->getMongoOperation($valueToUseInQuery);
		$valueUpdateQuery[$queryType]
			[$wallet->getFieldName()] = $valueToUseInQuery;

		// The TO time is always set.
		$valueUpdateQuery['$set']['pp_includes_name'] = $wallet->getPPName();
		$valueUpdateQuery['$set']['pp_includes_external_id'] = $wallet->getPPID();
		$valueUpdateQuery['$set']['priority'] = $wallet->getPriority();

		// If the wallet is shared, set the sid to 0
		if($wallet->isShared()) {
			$valueUpdateQuery['$set']['sid'] = 0;
		}
		
		// Check if recurring.
		if ($this->recurring) {
			$valueUpdateQuery['$set']['recurring'] = 1;
		}

		return $valueUpdateQuery;
	}
	
	/**
	 * Update the database.
	 * @param type $coll
	 * @param type $query
	 * @param type $update
	 * @param type $options
	 * @return type
	 */
	public function update($coll, $query, $update, $options) {
		return $coll->findAndModify($query, $update, array(), $options, true);
	}
	
	/**
	 * Reconfigure the updater operation with a record
	 * @param array $record - Record to use for reconfiguring the operation.
	 * @param boolean $verbose - If true, return both the instance and a boolean 
	 * indicator for change, array of "changed"=> boolean and "instance" => object.
	 * @return Billrun_Balances_Update_Operation | boolean - A reconfigured operation 
	 * instance or this if cannot reconfigure, false on error.
	 */
	public function reconfigure($record, $verbose=false) {
		if($verbose) {
			return array("changed" => false, "instance" => $this);
		}
		
		return $this;
	}
	
	/**
	 * Handle the core balance
	 * 
	 * @param int $max - Max value
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param type $query
	 * @return array ["onError"=>errorCode] if error occured, or ["block"=>boolean]
	 * indicating if should be blocked.
	 */
	public abstract function handleUnlimitedBalance($max, $wallet, $query);
}
