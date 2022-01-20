<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
					// in  ALLOT && Sasn we return 2001 in CCR level / 4012 move to MSCC level 
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1));
				case ($returnCodes['no_rate']):
					// in  ALLOT && Sasn we return 2001 in CCR level / 4012 move to MSCC level 
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1));
				case ($returnCodes['no_subscriber']):
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_USER_UNKNOWN", -1));
			}
		}

		if ($this->row['usagev'] === 0 && !$this->row['billrun_pretend']) {
			return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_CREDIT_LIMIT_REACHED", -1));
		}

		return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1));
	}

	protected function getMsccReturnCode($granted_return_code, $usagev) {
		if (isset($this->row['in_data_slowness']) && $this->row['in_data_slowness']) {
			return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1));
		}

		if (isset($granted_return_code)) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch ($granted_return_code) {
				case ($returnCodes['no_available_balances']):
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_CREDIT_LIMIT_REACHED", -1));
				case ($returnCodes['no_rate']):
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_END_USER_SERVICE_DENIED", -1));
				case ($returnCodes['no_subscriber']):
					return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_USER_UNKNOWN", -1));
			}
		}

		if ($usagev === 0 && !$this->row['billrun_pretend']) {
			return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_CREDIT_LIMIT_REACHED", -1));
		}

		return intval(Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1));
	}

	protected function getMsccData() {
		$retMsccData = array();
		$balanceRef = isset($this->row['balance_ref']) ? $this->row['balance_ref'] : false;
		$defaultValidityTime = Billrun_Factory::config()->getConfigValue("realtimeevent.data.validityTime", 0);
		$balances_coll = Billrun_Factory::db()->balancesCollection();
		if (!$balanceRef || !$balance = $balances_coll->getRef($balanceRef)) {
			$validityTime = max($defaultValidityTime, 60); // protection - in case there is a problem with the balance or default value
		} else {
			$secondsUntilEndOfBalance = ($balance->get('to')->sec - time()) + rand(0, 60); // the rand to spread when returning without concurrent bottlenecks
			$validityTime = max(min($defaultValidityTime, $secondsUntilEndOfBalance), 60); // protection - in case there is a problem with the balance or default value
		}
		$defaultQuotaHoldingTime = Billrun_Factory::config()->getConfigValue("realtimeevent.data.quotaHoldingTime", 0);
		$redirectionConfig = Billrun_Factory::config()->getConfigValue("realtimeevent.data.redirect", array("finalUnitAction" => 0));
		if (isset($redirectionConfig["finalUnitAction"])) {
			$redirectionConfig["finalUnitAction"] = intval($redirectionConfig["finalUnitAction"]);
		}

		foreach ($this->row['mscc_data'] as $msccData) {
			$redirectionAnswer = array();
			if (isset($msccData['granted_usagev'])) {
                            $currUsagev = $msccData['granted_usagev'];
                            unset($msccData['granted_usagev']);
                        } else {
                            $currUsagev = $msccData['usagev'];
                        }
                        $returnCode = $this->getMsccReturnCode($msccData['granted_return_code'], $currUsagev);
			unset($msccData['granted_return_code']);
			unset($msccData['usagev']);
			$quotaHoldingTimeArray = array("quotaHoldingTime" => $defaultQuotaHoldingTime);
			if ($redirectionConfig["finalUnitAction"] && $returnCode == Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_CREDIT_LIMIT_REACHED", -1)) {
				$returnCode = Billrun_Factory::config()->getConfigValue("realtimeevent.data.returnCode.DIAMETER_SUCCESS", -1);
				$redirectionAnswer = $redirectionConfig;
				$quotaHoldingTimeArray = array();
			}
			$freeOfChargeRatingGroups = Billrun_Factory::config()->getConfigValue('realtimeevent.data.freeOfChargeRatingGroups', array());
			if (isset($msccData['rating_group']) && in_array($msccData['rating_group'], $freeOfChargeRatingGroups)) {
				$currUsagev = Billrun_Factory::config()->getConfigValue('realtimeevent.data.freeOfChargeRatingGroupsDefaultUsagev', 0);
			}
			$basic_data = array(
				"grantedUnits" => $currUsagev,
				"validityTime" => $validityTime,
				"returnCode" => $returnCode,
			);
			$retMsccData[] = array_merge(Billrun_Util::parseBillrunConventionToCamelCase($msccData), $redirectionAnswer, $basic_data, $quotaHoldingTimeArray);
		}
		return $retMsccData;
	}

}
