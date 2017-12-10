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
abstract class Billrun_ActionManagers_Realtime_Responder_Call_Base extends Billrun_ActionManagers_Realtime_Responder_Base {

	protected function getResponseFields() {
		return array_merge(parent::getResponseFields(), Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.call.basic", array()), Billrun_Factory::config()->getConfigValue("realtimeevent.responseData.call.$this->responseApiName", array()));
	}

	/**
	 * Gets the clear casue value, based on $this->row data
	 * 
	 * @return int clear cause value
	 */
	protected function getClearCause() {
		//TODO: check if call excceeded max duration (realtimeevent.callReservationTime.max)
		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch ($this->row['granted_return_code']) {
				case ($returnCodes['no_available_balances']):
					return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.no_balance');
				case ($returnCodes['no_rate']):
					return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.invalid_called_number');
				case ($returnCodes['no_subscriber']):
					return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.inactive_account');
				case ($returnCodes['block_rate']):
					return Billrun_Factory::config()->getConfigValue('realtimeevent.clearCause.black_list_number');
			}
		}

		return "";
	}

	/**
	 * Gets the reservation time allowed for call, until next check with BillRun
	 * 
	 * @return reservation time in 10th of seconds
	 */
	protected function getReservationTime() {
		$log10size = Billrun_Factory::config()->getConfigValue('prepaid.reservation.log10size', 1);
		$duration = $this->row['usagev'] * $log10size;
		$method = Billrun_Factory::config()->getConfigValue('prepaid.reservation.method', '');
		if (!empty($method)) {
			$args = Billrun_Factory::config()->getConfigValue('prepaid.reservation.args', array());
			$method_args = array_merge(array($duration), $args);
			return call_user_func_array($method, $method_args);
		}
		return $duration;
	}

	protected function getReturnCode() {
		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch ($this->row['granted_return_code']) {
				case ($returnCodes['no_available_balances']):
				case ($returnCodes['no_rate']):
				case ($returnCodes['no_subscriber']):
				case ($returnCodes['block_rate']):
					return Billrun_Factory::config()->getConfigValue("realtimeevent.returnCode.call_not_allowed");
			}
		}

		return Billrun_Factory::config()->getConfigValue("realtimeevent.returnCode.call_allowed");
	}

	protected function getAnnouncement() {
		$announcement = false;
		$language = (isset($this->row['subscriber_lang']) ? $this->row['subscriber_lang'] : Billrun_Factory::config()->getConfigValue("realtimeevent.announcement.default_language"));
		if (isset($this->row['granted_return_code'])) {
			$returnCodes = Billrun_Factory::config()->getConfigValue('prepaid.customer', array());
			switch ($this->row['granted_return_code']) {
				case ($returnCodes['no_available_balances']):
					$announcement = Billrun_Factory::config()->getConfigValue("realtimeevent.announcement.insufficient_credit");
					break;
				case ($returnCodes['no_rate']):
				case ($returnCodes['block_rate']):
					$announcement = Billrun_Factory::config()->getConfigValue("realtimeevent.announcement.call_to_blocked_number");
					break;
				case ($returnCodes['no_subscriber']):
					$announcement = Billrun_Factory::config()->getConfigValue("realtimeevent.announcement.subscriber_not_found");
					break;
			}
		}

		if (!$announcement) {
			return "";
		}
		return $language . '-' . $announcement;
	}

}
