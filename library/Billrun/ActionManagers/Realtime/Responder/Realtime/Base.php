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
class Billrun_ActionManagers_Realtime_Responder_Realtime_Base extends Billrun_ActionManagers_Realtime_Responder_Base {
	
	protected $config = array();

	public function __construct(array $options = array()) {
		parent::__construct($options);
		$this->config = $options['config'];
	}
	
	protected function getResponseFields() {
		return (isset($this->config['response']['fields']) ? $this->config['response']['fields'] : Billrun_Factory::config()->getConfigValue('realtimeevent.responseData.basic', array()));
	}

	public function getResponsApiName() {
		return 'realtime';
	}
	
	/**
	 * get service rating array for OpenAPI response
	 *
	 * @return array
	 */
	protected function getServiceRating() {
		$ret = [];
		$unitsField = in_array($this->row['usaget'], ['call', 'incoming_call']) ? 'time' : 'totalVolume';

		foreach ($this->row['service_rating'] ?? [] as $serviceRating) {
			$serviceRatingRes = [
				'resultCode' => $this->getResultCode($serviceRating),
			];

			if (isset($serviceRating['serviceContextId'])) {
				$serviceRatingRes['serviceContextId'] =  $serviceRating['serviceContextId'];
			}

			if (isset($serviceRating['serviceId'])) {
				$serviceRatingRes['serviceId'] =  $serviceRating['serviceId'];
			}

			if (isset($serviceRating['ratingGroup'])) {
				$serviceRatingRes['ratingGroup'] =  intval($serviceRating['ratingGroup']);
			}
			
			if ($serviceRating['reservation_required'] ?? false) {
				$serviceRatingRes['grantedUnit'] = [
					$unitsField => $this->getGrantedUnit($serviceRating),
				];
			}
			
			if ($serviceRating['rebalance_required'] ?? false) {
				$serviceRatingRes['consumedUnit'] = [
					$unitsField => $serviceRating['consumedUnit'][$unitsField] ?? $this->row['usagev'],
				];
			}
			
			$expirationTime = $this->getExpirationTime();
			if ($expirationTime) {
				$serviceRatingRes['expiryTime'] =  $expirationTime;
			}
			
			$ret[] = $serviceRatingRes;
		}

		return $ret;
	}
	
	protected function getExpirationTime() {
		return $this->config['realtime'][$this->row['usaget']]['expiryTime'] ?? 3600;
	}
	
	/**
	 * method to get the granted unit
	 * in postpaid this the leftover of the group
	 * in prepaid is the predefined volume stored in usagev field
	 * 
	 * @param array $serviceRating service rating container
	 * @return int the granted volume
	 */
	protected function getGrantedUnit($serviceRating = array()) {
		if ($this->requirePostpaidOverGroupBlock()) {
			return $this->getRateGroupLeft();
		}
		return $serviceRating['usagev'] ?? 0;
	}
	
	/**
	 * method to check if we need postpaid block over group
	 * 
	 * @return boolean true if required to block over group on postpaid
	 */
	protected function requirePostpaidOverGroupBlock() {
		return Billrun_Utils_Realtime::getRealtimeConfigValue($this->config, 'postpay_charge') && 
				Billrun_Utils_Realtime::getRealtimeConfigValue($this->config, 'block_over_group');
	}
	
	/**
	 * method to retrieve how much left on postpaid group balance
	 * 
	 * @return int the volume left
	 */
	protected function getRateGroupLeft() {
		$arategroups = $this->row['arategroups'] ?? 0;
		
		if (empty($arategroups)) {
			return 0;
		}
		
		$left = $arategroups[count($arategroups)-1]['left'] ?? 0;
		// require to support minimum
		if ($left <= 0) {
			return 0;
		}
		
		$grantConfig = $this->config['realtime'][$this->row['usaget']]['default_values'] ?? 0;
		return min($left, $grantConfig[$this->row['record_type']] ?? ($grantConfig['default']));
	}

	/**
	 * get result code used by OpenAPI
	 * 
	 * @return string
	 */
	protected function getResultCode($serviceRating) {
		if (empty($serviceRating['reservation_required']) && !$this->requirePostpaidOverGroupBlock()) {
			return 'SUCCESS';
		}
		
		$returnCode = $serviceRating['return_code'];
		$returnCodes = Billrun_Factory::config()->getConfigValue('realtime.granted_code', []);

		if ($returnCode == $returnCodes['ok'] && $this->requirePostpaidOverGroupBlock() && $this->getRateGroupLeft() == 0) {
			$returnCode = $returnCodes['no_available_balances'];
		}
		
		switch ($returnCode) {
			case $returnCodes['no_available_balances']:
				return 'QUOTA_LIMIT_REACHED';
			case $returnCodes['failed_calculator']['rate']:
				if (!empty($serviceRating['blocked_rate'])) {
					return 'END_USER_SERVICE_REJECTED';
				}
				return 'END_USER_SERVICE_DENIED';
			case $returnCodes['failed_calculator']['customer']:
				return 'USER_UNKNOWN';
			case $returnCodes['failed_calculator']['pricing']:
				return 'RATING_FAILED';
			case $returnCodes['ok']:
				return 'SUCCESS';
			default:
				return 'QUOTA_MANAGEMENT_NOT_APPLICABLE';
		}
	}

}
