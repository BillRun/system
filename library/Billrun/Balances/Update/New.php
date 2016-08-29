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
		$valueToUseInQuery = $wallet->getValue();
		$valueFieldName = $wallet->getFieldName();
		$contents = $query['$set'];
		Billrun_Util::setDotArrayToArray($contents, $valueFieldName, $valueToUseInQuery);
		return array('$set' => $contents);
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
}
