<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * PL plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    4.0
 */
class pelephonePlugin extends Billrun_Plugin_BillrunPluginBase {
	
	use Billrun_FieldValidator_SOC;

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'pelephone';

	/**
	 * billing row to handle
	 * use to pre-fetch the billing line if the line is not passed in the requested event
	 * 
	 * @var array
	 */
	protected $row;

	public function extendRateParamsQuery(&$query, &$row, &$calculator) {
		if ($this->isInterconnect($row)) {
			$prefixes = Billrun_Util::getPrefixes($row['np_code'] . $row['called_number']);
			$query[0]['$match']['params.prefix']['$in'] = $prefixes;
			$query[3]['$match']['params_prefix']['$in'] = $prefixes;
		}
		return;
		if (!in_array($row['usaget'], array('call', 'video_call', 'sms', 'mms'))) {
			return;
		}
		$current_time = date('His');
		$weektime = date('w') . '-' . $current_time;
		$current_datetime = $row['urt']->sec;
		$day_type = Billrun_HebrewCal::getDayType($current_datetime);
		if (
			($weektime >= '5-160000' && $weektime <= '6-200000') ||
			($day_type == HEBCAL_SHORTDAY && $current_time >= '160000' && $current_time <= '235959') ||
			(
			$day_type == HEBCAL_HOLIDAY &&
			(
			($current_time >= '000000' && $current_time <= '195959') ||
			(Billrun_HebrewCal::getDayType($nextday = strtotime('+1 day', $current_datetime)) == HEBCAL_HOLIDAY || date('w', $nextday) == 6)
			)
			)
		) {
			$shabbat = true;
		} else {
			$shabbat = false;
		}
		if ($this->isInterconnect($row)) {
			$interconnect = true;
		} else {
			$interconnect = false;
		}
		$query[0]['$match']['params.shabbat'] = $shabbat;
		$query[0]['$match']['params.interconnect'] = $interconnect;
	}

	protected function canSubscriberEnterDataSlowness($row) {
		return isset($row['service']['code']) && $this->validateSOC($row['service']['code']);
	}

	protected function isSubscriberInDataSlowness($row) {
		return isset($row['in_data_slowness']) && $row['in_data_slowness'];
	}

	/**
	 * Gets data slowness speed and SOC according to plan or default 
	 * 
	 * @param string $socKey
	 * @return array slowness speed in Kb/s and SOC code
	 * @todo Check if plan has a value for slowness
	 */
	protected function getDataSlownessParams($socKey = NULL) {
		// TODO: Check first if it's set in plan
		$slownessParams = Billrun_Factory::config()->getConfigValue('realtimeevent.data.slowness');
		if (!is_null($socKey) && !isset($slownessParams[$socKey])) {
			$socKey = NULL;
		}
		return array(
			'speed' => is_null($socKey) ? '' : $slownessParams[$socKey]['speed'],
			'soc' => is_null($socKey) ? '' : $slownessParams[$socKey]['SOC'],
			'command' => $slownessParams['command'],
			'applicationId' => $slownessParams['applicationId'],
			'requestUrl' => $slownessParams['requestUrl'],
		);
	}
	
	protected function getSubscriber($subscriberId) {
		// Get subscriber query.
		$subscriberQuery = array_merge(Billrun_Util::getDateBoundQuery(), array('sid' => $subscriberId));
		
		$coll = Billrun_Factory::db()->subscribersCollection();
		$results = $coll->query($subscriberQuery)->cursor()->limit(1)->current();
		if ($results->isEmpty()) {
			return false;
		}
		return $results->getRawData();
	}
	
	public function afterSubscriberBalanceAutoRenewUpdate(&$autoRenewRecord) {
		$subscriber = $this->getSubscriber($autoRenewRecord['sid']);
		if (!$subscriber) {
			return false;
		}
		$this->updateSubscriberInDataSlowness($subscriber, false, true);
	}

	public function afterSubscriberBalanceNotFound(&$row) {
		if ($row['type'] === 'gy') {
			$in_slowness = FALSE;
			if ($this->isSubscriberInDataSlowness($row)) {
				$in_slowness = TRUE;
			} else if ($this->canSubscriberEnterDataSlowness($row)) {
				$this->updateSubscriberInDataSlowness($row, true, true);
				$row['in_data_slowness'] = TRUE;
				$in_slowness = TRUE;
			}
			if ($in_slowness) {
				if ($row['request_type'] == intval(Billrun_Factory::config()->getConfigValue('realtimeevent.data.requestType.FINAL_REQUEST'))) {
					$row['usagev'] = 1;
				} else {
					$row['usagev'] = Billrun_Factory::config()->getConfigValue('realtimeevent.data.quotaDefaultValue', 10 * 1024 * 1024);
				}
			}
		}
	}

	/**
	 * Enter and exit subscriber from data_slowness mode
	 * 
	 * @param type $row
	 * @param bool $enterToDataSlowness true - enter to data slowness, false - exit from data slowness
	 * @param bool $sendToProv true - send to provisioning, false - don't send to provisioning
	 */
	protected function updateSubscriberInDataSlowness($row, $enterToDataSlowness = true, $sendToProv = true) {
		// Update subscriber in DB
		$subscribersColl = Billrun_Factory::db()->subscribersCollection();
		$findQuery = array_merge(Billrun_Util::getDateBoundQuery(), array('sid' => $row['sid']));
		if ($enterToDataSlowness) {
			$updateQuery = array('$set' => array('in_data_slowness' => true));
		} else {
			$updateQuery = array('$unset' => array('in_data_slowness' => 1));		
		}
		$subscribersColl->update($findQuery, $updateQuery);
		if ($sendToProv) {
			$this->sendSlownessStateToProv($row['msisdn'], $row['service']['code'], $enterToDataSlowness);
		}
	}
	
	/**
	 * Send request to slowdown / cancel slowdown of the subscriber
	 * @param string $msisdn
	 * @param string $subscriberSoc
	 */
	protected function sendSlownessStateToProv($msisdn, $subscriberSoc = NULL, $enterToDataSlowness = true) {
		$encoder = new Billrun_Encoder_Xml();
		$slownessParams = $this->getDataSlownessParams($subscriberSoc);
		$requestBody = array(
			'HEADER' => array(
				'APPLICATION_ID' => $slownessParams['applicationId'],
				'COMMAND' => $slownessParams['command'],
			),
			'PARAMS' => array(
				'MSISDN' => $msisdn,
				'SLOWDOWN_SPEED' => ($enterToDataSlowness ? $slownessParams['speed'] : ''),
				'SLOWDOWN_SOC' => $slownessParams['soc'],
			)
		);
		$params = array(
			'root' => 'REQUEST',
			'addHeader' => false,
		);
		$request = array($encoder->encode($requestBody, $params));
		$requestUrl = $slownessParams['requestUrl'];
		return Billrun_Util::sendRequest($requestUrl, $request);
	}

	/**
	 * method to check if billing row is interconnect (not under PL network)
	 * 
	 * @param array $row the row to check
	 * 
	 * @return boolean true if not under PL network else false
	 */
	protected function isInterconnect($row) {
		return isset($row['np_code']) && is_string($row['np_code']) && strlen($row['np_code']) > 2;
	}

	/**
	 * use to store the row to extend balance query (method extendGetBalanceQuery)
	 * 
	 * @param array $row
	 * @param Billrun_Calculator $calculator
	 */
	public function beforeCalculatorUpdateRow(&$row, Billrun_Calculator $calculator) {
		if ($calculator->getType() == 'pricing') {
			$this->row = $row;
		}
	}

	/**
	 * method to extend the balance
	 * 
	 * @param array $query the query that will pull the balance
	 * @param int $timeNow the time of the row (unix timestamp)
	 * @param string $chargingType
	 * @param string $usageType
	 * @param Billrun_Balance $balance
	 * 
	 * @todo change the values to be with flag taken from pp_includes into balance object
	 * 
	 */
	public function extendGetBalanceQuery(&$query, &$timeNow, &$chargingType, &$usageType, Billrun_Balance $balance) {
		if (!empty($this->row)) {
			$pp_includes_external_ids = array();
			if (($this->isInterconnect($this->row) && $this->row['np_code'] != '831') || (isset($this->row['call_type']) && $this->row['call_type'] == '2')) {
				// we are out of PL network
				array_push($pp_includes_external_ids, 6);
			}

			if (isset($this->row['call_type']) && $this->row['call_type'] == '2') {
				array_push($pp_includes_external_ids, 3, 4);
			}

			$rate = Billrun_Factory::db()->ratesCollection()->getRef($this->row->get('arate'));
			if (isset($rate['params']['premium']) && $rate['params']['premium']) {
				array_push($pp_includes_external_ids, 3, 4, 5, 6, 7, 8);
			}

			if (count($pp_includes_external_ids)) {
				$query['pp_includes_external_id'] = array('$nin' => $pp_includes_external_ids);
			}
		}
	}

	/**
	 * 
	 * @param Mongodloid_Entity $record
	 * @param Billrun_ActionManagers_Subscribers_Update $updateAction
	 */
	public function beforeSubscriberSave(&$record, Billrun_ActionManagers_Subscribers_Update $updateAction) {
		if (isset($record['service']['code']) && empty($record['service']['code'])) {
			$record['in_data_slowness'] = FALSE;
		}
	}

}
