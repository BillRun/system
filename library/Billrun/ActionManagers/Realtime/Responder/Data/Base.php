<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch($this->row['granted_return_code']) {
				case ($returnCodes['no_available_balances']):
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
		$usagev = $this->row['usagev'] / count($this->row['mscc_data']);
		$secondsUntilMidnight = (strtotime('tomorrow 00:00:00') - time());
		$defaultValidityTime = Billrun_Factory::config()->getConfigValue("realtimeevent.data.validityTime", 0);
		$validityTime = min(array($defaultValidityTime, $secondsUntilMidnight));
		$defaultQuotaHoldingTime = Billrun_Factory::config()->getConfigValue("realtimeevent.data.quotaHoldingTime", 0);
		$returnCode = $this->getReturnCode();

		foreach ($this->row['mscc_data'] as $msccData) {
			$retMsccData[] = array_merge($msccData, array(
				"grantedUnits" => $usagev,
				"validityTime" => $validityTime,
				"quotaHoldingTime" => $defaultQuotaHoldingTime,
				"resultCode" => $returnCode,
			));
		}
		return $retMsccData;
	}
	
	/**
	 * Gets the real usagev of the user (known only on the next API call)
	 * 
	 * @return type
	 */
	protected function getRealUsagev() {
		$sum = 0;	
		foreach ($this->row['mscc_data'] as $msccData) {
			$sum += intval($msccData['used_units']);
		}
		return $sum;
	}
	
	/**
	 * Gets a query to find amount of balance (usagev) calculated in last data call
	 * 
	 * @return array
	 */
	protected function getRebalanceQuery() {
		return array(
			array(
				'$match' => array(
					"session_id" => $this->row['session_id']
				)
			),
			array(
				'$group' => array(
					'_id' => '$session_id',
					'sum' => array('$last' => '$usagev')
				)
			)
		);
	}

}
