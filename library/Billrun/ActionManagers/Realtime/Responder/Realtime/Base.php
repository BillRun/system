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
					$unitsField => $serviceRating['usagev'] ?? 0,
				];
			}
			
			if ($serviceRating['rebalance_required'] ?? false) {
				$serviceRatingRes['consumedUnit'] = [
					$unitsField => $serviceRating['consumedUnit'][$unitsField] ?? 0,
				];
			}
			
			$ret[] = $serviceRatingRes;
		}

		return $ret;
	}

	/**
	 * get result code used by OpenAPI
	 * 
	 * @return string
	 */
	protected function getResultCode($serviceRating) {
		if (empty($serviceRating['reservation_required'])) {
			return 'SUCCESS';
		}
		
		$returnCode = $serviceRating['return_code'];
		$returnCodes = Billrun_Factory::config()->getConfigValue('realtime.granted_code', []);
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
