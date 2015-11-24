<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using the prepaid include.
 *
 * @author tom
 */
class Billrun_ActionManagers_Balances_Updaters_PrepaidInclude extends Billrun_ActionManagers_Balances_Updaters_Updater {
	
	/**
	 * Get the array of strings to translate the names of the input fields to the names used in the db.
	 * @return array.
	 */
	protected function getTranslateFields() {
		// TODO: Should this be in conf?
		return array('pp_includes_name'        => 'name',
				  'pp_includes_external_id' => 'external_id');
	}
	
	/**
	 * Update the balances, based on the plans table
	 * @param type $query - Query to find row to update.
	 * @param type $recordToSet - Values to update.
	 * @param type $subscriberId - Id for the subscriber to update.
	 * @return The updated record, false if failed.
	 */
	public function update($query, $recordToSet, $subscriberId) {
		// If updating by prepaid include the user must specify an expiration date.
		if(!$recordToSet['to']) {
			$error = "Update balance by prepaid include must receive expiration date";
			$this->reportError($error, Zend_Log::ERR);
			return false;
		}
		
		// No value is set.
		if(!isset($recordToSet['value'])) {
			$error = "Update balance by prepaid include must receive value to update";
			$this->reportError($error, Zend_Log::ERR);
			return false;
		}
		
		$db = Billrun_Factory::db();
		$prepaidIncludes = $db->prepaidincludesCollection();
		$prepaidRecord = $this->getRecord($query, $prepaidIncludes, $this->getTranslateFields());
		if(!$prepaidRecord) {
			$error = "Failed to get prepaid include record";
			$this->reportError($error, Zend_Log::ERR);
			return false;
		}
		
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId);	
		
		// Subscriber was not found.
		if(!$subscriber) {
			$error = "Updating by prepaid include failed to get subscriber id: " . $subscriberId;
			$this->reportError($error, Zend_Log::ERR);
			return false;
		}
		
		// Set subscriber to query.
		$updateQuery['sid'] = $subscriber['sid'];
		$updateQuery['aid'] = $subscriber['aid'];
		
		// Create a default balance record.
		$defaultBalance = $this->getDefaultBalance($subscriber, $prepaidRecord);
		
		$chargingPlan = $this->getPlanObject($prepaidRecord, $recordToSet);
		
		return $this->updateBalance($chargingPlan, 
									$updateQuery, 
									$defaultBalance, 
									$recordToSet['to']);
	}
	
	/**
	 * Get the plan object built from the record values.
	 * @param array $prepaidRecord - Prepaid record.
	 * @param array $recordToSet - Record with values to be set.
	 * @return \Billrun_DataTypes_Wallet Plan object built with values.
	 */
	protected function getPlanObject($prepaidRecord, $recordToSet) {
		$chargingBy = $prepaidRecord['charging_by'];
		$chargingByUsaget = $prepaidRecord['charging_by_usaget'];
		if($chargingBy == $chargingByUsaget) {
			$chargingByUsaget = $recordToSet['value'];
		}else{
			$chargingByUsaget = array($chargingByUsaget => $recordToSet['value']);
		}
		
		return new Billrun_DataTypes_Wallet($chargingBy, 
											$chargingByUsaget);
	}
	
	/**
	 * Get the update balance query. 
	 * @param Mongoldoid_Collection $balancesColl
	 * @param array $query - Query for getting tha balance.
	 * @param Billrun_DataTypes_Wallet $chargingPlan
	 * @param MongoDate $toTime - Expiration date.
	 * @param array $defaultBalance - Default balance to set.
	 * @return array Query for set updating the balance.
	 */
	protected function getUpdateBalanceQuery($balancesColl, 
											 $query, 
											 $chargingPlan,
											 $toTime,
										     $defaultBalance) {
		$update = array();
		// If the balance doesn't exist take the setOnInsert query, 
		// if it exists take the set query.
		if(!$balancesColl->exists($query)) {
			$update = $this->getSetOnInsert($chargingPlan, $defaultBalance);
		} else {
			$this->handleZeroing($query, $balancesColl, $chargingPlan->getFieldName());
			$update = $this->getSetQuery($chargingPlan->getValue(), $chargingPlan->getFieldName(), $toTime);
		}
		
		return $update;
	}
	
	/**
	 * Return the part of the query for setOnInsert
	 * @param Billrun_DataTypes_Wallet $chargingPlan
	 * @param array $defaultBalance
	 * @return array
	 */
	protected function getSetOnInsert($chargingPlan, 
									  $defaultBalance) {
		$defaultBalance['charging_by'] = $chargingPlan->getChargingBy();
		$defaultBalance['charging_by_usegt'] = $chargingPlan->getChargingByUsaget();
		$defaultBalance[$chargingPlan->getFieldName()] = $chargingPlan->getValue();
		return array(
			'$setOnInsert' => $defaultBalance,
		);
	}
	
	/**
	 * Update a single balance.
	 * @param Billrun_DataTypes_Wallet $chargingPlan
	 * @param array $query
	 * @param array $defaultBalance
	 * @param MongoDate $toTime
	 * @return Array with the wallet as the key and the Updated record as the value.
	 */
	protected function updateBalance($chargingPlan, $query, $defaultBalance, $toTime) {
		$balancesColl = Billrun_Factory::db()->balancesCollection();

		// Get the balance with the current value field.
		$query[$chargingPlan->getFieldName()]['$exists'] = 1;
		
		$update = $this->getUpdateBalanceQuery($balancesColl, 
											   $query, 
											   $chargingPlan,
											   $toTime,
										       $defaultBalance);
				
		$options = array(
			'upsert' => true,
			'new' => true,
			'w' => 1,
		);

		// Return the new document.
		return array($chargingPlan=>$balancesColl->findAndModify($query, $update, array(), $options, true));
	}
	
	/**
	 * Get a default balance record, without charging by.
	 * @param type $subscriber
	 * @param type $prepaidRecord
	 * @param type $recordToSet
	 */
	protected function getDefaultBalance($subscriber, $prepaidRecord) {
		$defaultBalance = array();
		$defaultBalance['from'] = new MongoDate();
		
		$defaultBalance['to']    = $prepaidRecord['to'];
		$defaultBalance['sid']   = $subscriber['sid'];
		$defaultBalance['aid']   = $subscriber['aid'];
		$defaultBalance['current_plan'] = $this->getPlanRefForSubscriber($subscriber);
		$defaultBalance['charging_type'] = $subscriber['charging_type'];
		$defaultBalance['charging_by'] = $prepaidRecord['charging_by'];
		$defaultBalance['charging_by_usaget'] = $prepaidRecord['charging_by_usaget'];
		// TODO: This is not the correct way, priority needs to be calculated.
		$defaultBalance['priority'] = 1;
		
		return $defaultBalance;
	}
}
