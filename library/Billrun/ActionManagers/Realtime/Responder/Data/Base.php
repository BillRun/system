<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a prototype for a Realtime response action.
 *
 */
abstract class Billrun_ActionManagers_Realtime_Responder_Data_Base extends Billrun_ActionManagers_Realtime_Responder_Base {

	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(), Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.data.basic", array()), Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.data.$this->responseApiName", array()));
	}

	protected function getReturnCode() {
		if (isset($this->row['in_data_slowness']) && $this->row['in_data_slowness']) {
			return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1));
		}

		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch ($this->row['granted_return_code']) {
				case ($returnCodes['no_available_balances']):
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_CREDIT_LIMIT_REACHED", -1));
				case ($returnCodes['no_rate']):
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_END_USER_SERVICE_DENIED", -1));
				case ($returnCodes['no_subscriber']):
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_USER_UNKNOWN", -1));
			}
		}

		if ($this->row['usagev'] === 0) {
			return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_CREDIT_LIMIT_REACHED", -1));
		}

		return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1));
	}

	protected function getMsccData() {
		$retMsccData = array();
		$usagev = round($this->row['usagev'] / count($this->row['mscc_data']));
		$balanceRef = $this->row['balance_ref'];
		$defaultValidityTime = Billrun_Factory::config()->getConfigValue("realtimeevent.data.validityTime", 0);
		$balances_coll = Billrun_Factory::db()->balancesCollection();
		if (!$balanceRef || !$balance = $balances_coll->getRef($balanceRef)) {
			$validityTime = max($defaultValidityTime, 60); // protection - in case there is a problem with the balance or default value
		} else {
			$secondsUntilEndOfBalance = ($balance->get('to')->sec - time()) + rand(0, 60); // the rand to spread when returning without concurrent bottlenecks
			$validityTime = max(min($defaultValidityTime, $secondsUntilEndOfBalance), 60); // protection - in case there is a problem with the balance or default value
		}
		$defaultQuotaHoldingTime = Billrun_Factory::config()->getConfigValue("realtimeevent.data.quotaHoldingTime", 0);
		$returnCode = $this->getReturnCode();

		foreach ($this->row['mscc_data'] as $msccData) {
			$currUsagev = $usagev;
			$freeOfChargeRatingGroups = Billrun_Factory::config()->getConfigValue('realtimeevent.data.freeOfChargeRatingGroups', array());
			if (isset($msccData['rating_group']) && in_array($msccData['rating_group'], $freeOfChargeRatingGroups)) {
				$currUsagev = Billrun_Factory::config()->getConfigValue('realtimeevent.data.freeOfChargeRatingGroupsDefaultUsagev', 0);
			}
			$retMsccData[] = array_merge(
				Billrun_Util::parseBillrunConventionToCamelCase($msccData), array(
				"grantedUnits" => $currUsagev,
				"validityTime" => $validityTime,
				"quotaHoldingTime" => $defaultQuotaHoldingTime,
				"resultCode" => $returnCode,
			));
		}
		return $retMsccData;
	}

}
