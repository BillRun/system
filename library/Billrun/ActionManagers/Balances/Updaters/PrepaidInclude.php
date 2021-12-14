<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Holds the logic for updating balances using the prepaid include.
 *
 */
class Billrun_ActionManagers_Balances_Updaters_PrepaidInclude extends Billrun_ActionManagers_Balances_Updaters_Updater {

	protected $type = 'PrepaidInclude';

	/**
	 * Get the array of strings to translate the names of the input fields to the names used in the db.
	 * @return array.
	 */
	protected function getTranslateFields() {
		// TODO: Should this be in conf?
		return array(
			'pp_includes_name' => 'name',
			'pp_includes_external_id' => 'external_id'
		);
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
		if (!$recordToSet['to']) {
			$errorCode = 6;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// No value is set.
		if (!isset($recordToSet['value'])) {
			$errorCode = 7;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		$db = Billrun_Factory::db();
		$prepaidIncludes = $db->prepaidincludesCollection()->setReadPreference('RP_PRIMARY', array());
		$prepaidRecord = $this->getRecord($query, $prepaidIncludes, $this->getTranslateFields());
		if (!$prepaidRecord) {
			$errorCode = 8;
			$this->reportError($errorCode, Zend_Log::NOTICE);
			return false;
		}

		// Check if the prepaid record is unlimited.
		if (!empty($prepaidRecord['unlimited'])) {
			$recordToSet['to'] = new Mongodloid_Date(strtotime(Billrun_Utils_Time::UNLIMITED_DATE));
		}
		// Get the subscriber.
		$subscriber = $this->getSubscriber($subscriberId);

		// Subscriber was not found.
		if ($subscriber === false) {
			return false;
		}

		// Set subscriber to query.
		$findQuery['aid'] = $subscriber['aid'];
		$findQuery['sid'] = $subscriber['sid'];

		// Create a default balance record.
		$defaultBalance = $this->getDefaultBalance($subscriber, $prepaidRecord, $recordToSet);

		$chargingPlan = $this->getPlanObject($prepaidRecord, $recordToSet);

		// Get the balance with the current value field.
		$findQuery[$chargingPlan->getFieldName()]['$exists'] = 1;
		$findQuery['pp_includes_external_id'] = $chargingPlan->getPPID();

		// Check if passing the max.
		if ($chargingPlan->getUnlimited() && !$this->handleUnlimitedBalance($subscriber['plan'], $chargingPlan, $findQuery)) {
			return false;
		}

		// TODO: Use the new values calculted, value before and balance before.
		$updateResult = $this->updateBalance($chargingPlan, $findQuery, $defaultBalance, $recordToSet['to']);
		$normalizeResult = $this->normalizeBalance($findQuery, $subscriber['plan'], $chargingPlan);
		if ($normalizeResult === false) {
			return false;
		}

		// Report on changes
		if ($normalizeResult['nModified'] > 0) {
			$valueName = $chargingPlan->getFieldName();
			$beforeNormalizing = $updateResult[0]['balance'][$valueName];
			$updateResult[0]['balance'][$valueName] = $normalizeResult['max'];
			$updateResult[0]['normalized']['before'] = $beforeNormalizing - $chargingPlan->getValue();
			$updateResult[0]['normalized']['after'] = $beforeNormalizing;
			$updateResult[0]['normalized']['normalized'] = $normalizeResult['max'];
		}

		$updateResult[0]['source'] = $prepaidIncludes->createRefByEntity($prepaidRecord);
		$updateResult[0]['subscriber'] = $subscriber;
		return $updateResult;
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
		if ($chargingBy == $chargingByUsaget) {
			$chargingByValue = $recordToSet['value'];
		} else {
			$chargingByValue = array($chargingBy => $recordToSet['value']);
		}

		$ppPair['priority'] = $prepaidRecord['priority'];
		$ppPair['pp_includes_name'] = $prepaidRecord['name'];
		$ppPair['pp_includes_external_id'] = $prepaidRecord['external_id'];
		$ppPair['unlimited'] = !empty($prepaidRecord['unlimited']);

		return new Billrun_DataTypes_Wallet($chargingByUsaget, $chargingByValue, $ppPair);
	}

	/**
	 * Update a single balance.
	 * @param Billrun_DataTypes_Wallet $wallet
	 * @param array $query
	 * @param array $defaultBalance
	 * @param Mongodloid_Date $toTime
	 * @return Array with the wallet as the key and the Updated record as the value.
	 */
	protected function updateBalance($wallet, $query, $defaultBalance, $toTime) {
		$balance = parent::updateBalance($wallet, $query, $defaultBalance, $toTime);

		// Return the new document.
		return array(
			array(
				'wallet' => $wallet,
				'balance' => $balance
			)
		);
	}

	/**
	 * Get a default balance record, without charging by.
	 * @param type $subscriber
	 * @param type $prepaidRecord
	 * @param type $recordToSet
	 */
	protected function getDefaultBalance($subscriber, $prepaidRecord, $recordToSet) {
		$defaultBalance = array();
		$defaultBalance['from'] = new Mongodloid_Date();

		$defaultBalance['to'] = $recordToSet['to'];

		// If the prepaid record is shared, then set the sid value to 0.
		if (!empty($prepaidRecord['shared'])) {
			$defaultBalance['sid'] = 0;
		} else {
			$defaultBalance['sid'] = $subscriber['sid'];
		}
		$defaultBalance['aid'] = $subscriber['aid'];
//		$defaultBalance['current_plan'] = $this->getPlanRefForSubscriber($subscriber);
		// Setting the connection type to prepaid by deafault (when updating by prepaid includes).
		$defaultBalance['connection_type'] = 'prepaid';
		$defaultBalance['charging_by'] = $prepaidRecord['charging_by'];
		$defaultBalance['charging_by_usaget'] = $prepaidRecord['charging_by_usaget'];

		return $defaultBalance;
	}

}
