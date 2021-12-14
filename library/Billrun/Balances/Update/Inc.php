<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Representing an inc update operation
 *
 * @package  Balances
 * @since    4.5
 */
class Billrun_Balances_Update_Inc extends Billrun_Balances_Update_Operation {

	/**
	 * Get the mongo operation to execute.
	 * @param mixed $valueToSet - Value to set.
	 * @return string - $inc or $set
	 */
	protected function getMongoOperation($valueToSet) {
		if (is_array($valueToSet) || is_numeric($valueToSet) !== true) {
			return '$set';
		}

		return '$inc';
	}

	/**
	 * Is an increment operation.
	 * @return boolean true if is increment.
	 */
	public function isIncrement() {
		return true;
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
	public function handleUnlimitedBalance($max, $wallet, $query) {
		$newValue = $wallet->getValue();

		// Check if passing the max.
		$this->getExpectedValueForIncrement($query, $newValue);

		// we're using absolute for both cases - positive and negative values
		return array("block" => (abs($newValue) > abs($max)));
	}

	/**
	 * Get the expected balance value for an increment operation.
	 * This function is used to check if the update logic should be blocked conditioned
	 * on the defined max value.
	 * @param array $query - Query to use to get a balance record
	 * @param int $newValue - Reference parameter, updated inside with the value before.
	 */
	protected function getExpectedValueForIncrement($query, &$newValue) {
		$coll = Billrun_Factory::db()->balancesCollection()->setReadPreference('RP_PRIMARY', array());
		$balanceQuery = array_merge($query, Billrun_Utils_Mongo::getDateBoundQuery());
		$balanceBefore = $coll->query($balanceQuery)->cursor()->current();
		if (!$balanceBefore->isEmpty()) {
			$valueBefore = Billrun_Balances_Util::getBalanceValue($balanceBefore);
		}

		$newValue += $valueBefore;
	}

}
