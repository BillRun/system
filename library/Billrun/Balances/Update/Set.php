<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Representing a set update operation
 *
 * @package  Balances
 * @since    4.5
 */
class Billrun_Balances_Update_Set extends Billrun_Balances_Update_Operation {

	/**
	 * Get the mongo operation to execute.
	 * @param mixed $valueToSet - Value to set.
	 * @return string - $inc or $set
	 */
	protected function getMongoOperation($valueToSet) {
		return '$set';
	}
	
	/**
	 * Is an increment operation.
	 * @return boolean true if is increment.
	 */
	public function isIncrement() {
		return false;
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
	public function handleCoreBalance($max, $wallet, $query) {
		$newValue = $wallet->getValue();

		// we're using absolute for both cases - positive and negative values
		return array("block"=>(abs($newValue) > abs($max)));
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
		if (!key_exists('_id', $query) && !key_exists('id', $query)) {
			$this->resetParallelBalances($coll, $query);
		}
		return parent::update($coll, $query, $update, $options);
	}
	
	protected function resetParallelBalances($coll, $query) {
		$balances = $coll->query($query);
		$updater = new Billrun_ActionManagers_Balances_Update();
		foreach ($balances as $balance) {
			$updaterInput = array(
				'sid' => $balance->get('sid'),
				'query' =>
					json_encode(array(
						'_id' => $balance->getId()->getMongoId(),
					)), 
				'upsert' => 
					json_encode(array(
						'operation' => 'set',
						'value' => 0,
						'expiration_date' => $balance->get('to'),
					)),
				'additional' => json_encode($this->additional),
			);
			$jsonObject = new Billrun_AnObj($updaterInput);
			if (!$updater->parse($jsonObject)) {
				return false;
			}
			if (!$updater->execute()) {
				return false;
			}
		}

		return true;
	}
}
