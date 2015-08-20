<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using charging plans.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Updaters_ChargingPlan extends Billrun_ActionManagers_Balances_Updaters_Updater{
	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		$planQuery = $this->getPlanQuery($query);
		$plansCollection = Billrun_Factory::db()->plansCollection();
		
		// TODO: Use the plans DB/API proxy.
		$planRecord = $plansCollection->query($planQuery)->cursor()->current();
		if(!$planRecord || $planRecord->isEmpty()) {
			// TODO: Report error.
			return false;
		}
		
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId, $planRecord);	
		
		// Subscriber was not found.
		if($subscriber->isEmpty()) {
			// TODO: Report error
			return false;
		}
		
		if(!$this->validateServiceProviders($subscriberId, $recordToSet)) {
			return false;
		}
		
		$this->handleExpirationDate($recordToSet, $subscriberId);
		
		$balancesColl = Billrun_Factory::db()->balancesCollection();
		
		return $this->updateBalance($query, $balancesColl, $recordToSet);
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
		
		if(!$balanceRecord || $balanceRecord->isEmpty()) {
			// TODO: Report error.
			return null;
		}
		
		return $balanceRecord;
	}
	
	/**
	 * Update a single balance.
	 * @param type $query
	 * @param type $balancesColl
	 * @return type
	 */
	protected function updateBalance($query, $balancesColl, $recordToSet) {
		$valueFieldName = array();
		$valueToUseInQuery = null;
		
		// Find the record in the collection.
		$balanceRecord = $this->getBalanceRecord($balancesColl, $query);
		if(!$balanceRecord) {
			return false;
		}
		
		list($chargingBy, $chargingByValue) = each($balanceRecord['balance']);
		
		if(!is_array($chargingByValue)){
			$valueFieldName= 'balance.' . $chargingBy;
			$valueToUseInQuery = $chargingByValue;
		}else{
			list($chargingByValueName, $value)= each($chargingByValue);
			$valueFieldName= 'balance.totals.' . $chargingBy . '.' . $chargingByValueName;
			$valueToUseInQuery = $value;
			$chargingBy=$chargingByValueName;
		}

		$valueUpdateQuery = array();
		$queryType = $this->isIncrement ? '$inc' : '$set';
		$valueUpdateQuery[$queryType]
				   [$valueFieldName] = $valueToUseInQuery;
		$valueUpdateQuery[$queryType]
				   ['to'] = $recordToSet['to'];

		$options = array(
			// We do not want to upsert if trying to update by ID.
			'upsert' => false,
			'new' => true,
			'w' => 1,
		);

		// Return the new document.
		return array($balancesColl->findAndModify($query, $valueUpdateQuery, array(), $options, true));
	}
}