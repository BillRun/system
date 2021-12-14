<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Representing an set update operation
 *
 * @package  Balances
 * @since    4.5
 */
class Billrun_Balances_Update_New extends Billrun_Balances_Update_Set {

	/**
	 * Update the database.
	 * @param type $coll
	 * @param type $query
	 * @param type $update
	 * @param type $options
	 * @return type
	 */
	public function update($coll, $query, $update, $options) {
		$contents = $update['$set'];
		$contents['sid'] = $query['sid'];
		$contents['aid'] = $query['aid'];
		$entity = new Mongodloid_Entity($contents);
		// TODO: Should we validate the insert operation? What do we do if it failed?
		$coll->insert($entity);
		return $entity;
	}

	/**
	 * Get the set part of the query.
	 * @param Billrun_DataTypes_Wallet $wallet - The wallet in use.
	 */
	protected function getSetQuery($wallet) {
		$query = parent::getSetQuery($wallet);
		$valueFieldName = $wallet->getFieldName();
		$contents = $query['$set'];
		unset($contents[$valueFieldName]);
		if (!isset($contents['from'])) {
			$contents['from'] = new Mongodloid_Date();
		}
		$merged = array_merge($contents, $wallet->getPartialBalance());
		return array('$set' => $merged);
	}

	/**
	 * Return the part of the query for setOnInsert
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param array $defaultBalance
	 * @return type
	 */
	protected function getSetOnInsert($wallet, $defaultBalance) {
		$result = parent::getSetOnInsert($wallet, $defaultBalance);
		$contents = $result['$setOnInsert'];
		$result['$set'] = $contents;
		unset($result['$setOnInsert']);
		return $result;
	}

	/**
	 * Set the 'To' field to the update query
	 * @param array $update - The update query to set the to for
	 * @param type $to - Time value.
	 * @param type $balanceRecord - Current balance to update.
	 */
	public function setToForUpdate(&$update, $to, $balanceRecord) {
		$update['$set']['to'] = $to;
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
		// [Balances Error 1240]
		$errorCode = 40;
		return array("onError" => $errorCode);
	}

}
