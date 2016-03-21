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
	
	protected function getMaxCurrencyValues($row) {
		$plan = Billrun_Factory::db()->plansCollection()->getRef($row['plan_ref']);
		if ($plan && isset($plan['max_currency'])) {
			$maxCurrency = $plan['max_currency'];
		} else {
			$maxCurrency = Billrun_Factory::config()->getConfigValue('realtimeevent.data.maxCurrency', array());
		}
		
		return $maxCurrency;
	}
	
	protected function getSubscriberDiffFromMaxCurrency($row) {
		$maxCurrency = $this->getMaxCurrencyValues($row);
		$query = $this->getSubscriberCurrencyUsageQuery($row, $maxCurrency['period']);
		$archiveDb = Billrun_Factory::db();
		$lines_archive_coll = $archiveDb->archiveCollection();
		$res = $lines_archive_coll->aggregate($query)->current();
		return $maxCurrency['cost'] - $res->get('total_price');
	}
	
	protected function isSubscriberInMaxCurrency($row) {
		$diff = $this->getSubscriberDiffFromMaxCurrency($row);
		return ($diff <= 0);
	}
			
	
	protected function getSubscriberCurrencyUsageQuery($row, $period) {
		$startTime = Billrun_Util::getStartTimeByPeriod($period);
		$match = array(
			'type' => 'gy',
			'sid' => $row['sid'],
			'pp_includes_external_id' => array(
				'$in' => array(1,2,9,10)
			),
			'urt' => array(
				'$gte' => new MongoDate($startTime),
			),
		);
		$group = array(
			'_id' => '$sid',
			'total_price' => array(
				'$sum' => '$aprice'
			),
		);
		
		return array(array('$match' => $match),array('$group' => $group));
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
		if (!is_null($socKey) && !isset($slownessParams['bandwidth_cap'][$socKey])) {
			$socKey = NULL;
		}
		return array(
			'speed' => is_null($socKey) ? '' : $slownessParams['bandwidth_cap'][$socKey]['speed'],
			'soc' => is_null($socKey) ? '' : $slownessParams['bandwidth_cap'][$socKey]['SOC'],
			'command' => $slownessParams['command'],
			'applicationId' => $slownessParams['applicationId'],
			'requestUrl' => $slownessParams['requestUrl'],
			'sendRequestToProv' => $slownessParams['sendRequestToProv'],
			'sendRequestTries' => $slownessParams['sendRequestTries'],
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
		return $results;
	}

	protected function getSubscriberPlan($subscriber) {
		$planQuery = array_merge(Billrun_Util::getDateBoundQuery(), array('name' => $subscriber['plan']));
		
		$coll = Billrun_Factory::db()->plansCollection();
		$results = $coll->query($planQuery)->cursor()->limit(1)->current();
		if ($results->isEmpty()) {
			return false;
		}
		return $results;
	}
	
	public function afterSubscriberBalanceAutoRenewUpdate($autoRenewRecord) {
		$subscriber = $this->getSubscriber($autoRenewRecord['sid'])->getRawData();
		if (!$subscriber) {
			return false;
		}
		$this->updateSubscriberInDataSlowness($subscriber, false, true);
	}

	public function subscribersPlansEnded($sids) {
		foreach ($sids as $sid) {
			$subscriber = $this->getSubscriber($sid);
			if (!$subscriber) {
				continue;
			}
			if (isset($subscriber['in_data_slowness']) && $subscriber['in_data_slowness']) {
				$this->updateSubscriberInDataSlowness($subscriber, false, true);
			}
		}
	}

	public function afterUpdateSubscriberAfterBalance($row,$balance,$balanceAfter) {
		$plan = Billrun_Factory::db()->plansCollection()->getRef($row['plan_ref']);
		$this->handleBalanceNotifications("BALANCE_AFTER", $plan, Billrun_Util::msisdn($row['sid']), $balance, $balanceAfter);
	}

	public function afterBalanceLoad($balance, $subscriber) {
		$this->updateDataSlownessOnBalanceUpdate($balance, $subscriber);
		$update = array(
			'$unset' => array(
				'notifications_sent' => $notificationSent,
			),
		);
		$query = array(
			'sid' => $balance->get('sid'), // this is for balance sharding mechanism
			'_id' => $balance->getId()->getMongoId(),
		);
		Billrun_Factory::db()->balancesCollection()->update($query, $update);
		$plan = $this->getSubscriberPlan($subscriber);
		$this->handleBalanceNotifications("BALANCE_LOAD", $plan, $subscriber['msisdn'], $balance);
	}

	public function balanceExpirationDate($balance, $subscriber) {
		$plan = $this->getSubscriberPlan($subscriber);
		$this->handleBalanceNotifications("BALANCE_EXPIRATION", $plan, $subscriber['msisdn'], $balance);
	}
	
	protected function getNotificationKey($type, $balance) {
		switch ($type) {
			case ('BALANCE_AFTER'):
				return $balance->get('pp_includes_external_id');
			case ('BALANCE_LOAD'):
				return 'on_load';
			case ('BALANCE_EXPIRATION'):
				return 'expiration_date';
		}
		return false;
	}

	protected function needToSendNotification($type, $notification, $balance, $balanceAfter = 0) {
		switch ($type) {
			case ('BALANCE_AFTER'):
				return $balanceAfter >= $notification['value'];
			case ('BALANCE_LOAD'):
				return in_array($balance->get('pp_includes_external_id'), $notification['pp_includes']);
			case ('BALANCE_EXPIRATION'):
				return true;
		}
		return false;
	}
	
	public function handleBalanceNotifications($type, $plan, $msisdn, $balance, $balanceAfter = 0) {
		try {
			if (!$balance || !$plan || !isset($plan['notifications_threshold'])) {
				return;
			}
			$notificationKey = $this->getNotificationKey($type, $balance);
			foreach ($plan['notifications_threshold'][$notificationKey] as $index => $notification) {
				if (!$notificationSent = $balance->get('notifications_sent')) {
					$notificationSent = array($notificationKey => array());
				}
				if (in_array($index, $notificationSent[$notificationKey])) { // If the notification was already sent
					continue;
				}
				if ($this->needToSendNotification($type, $notification, $balance, $balanceAfter)) {
					$modifyParams = array('balance' => $balance);
					$msg = $this->modifyNotificationMessage($notification['msg'], $modifyParams);
					$this->sendNotification($notification['type'], $msg, $msisdn);
					array_push($notificationSent[$notificationKey], $index);
					$update = array(
						'$set' => array(
							'notifications_sent' => $notificationSent,
						),
					);
					$query = array(
						'sid' => $balance->get('sid'), // this is for balance sharding mechanism
						'_id' => $balance->getId()->getMongoId(),
					);
					Billrun_Factory::db()->balancesCollection()->update($query, $update);
				}
			}
		} catch (Exception $ex) {
			Billrun_Factory::log("Handle balance notifications failed. Reason: " . $ex->getCode() . " " . $ex->getMessage(), Zend_Log::ERR);
		}
	}
	
	protected function getNotificationExpireDate($obj) {
		return date('Y-m-d H:i:s', $obj->get('to')->sec);
	}
	
	protected function modifyNotificationMessage($str, $params) {
		$msg = $str;
		foreach ($params as $key => $obj) {
			$replaces = Billrun_Factory::config()->getConfigValue('realtimeevent.notifications.replace.' . $key, array());
			foreach ($replaces as $search => $replace) {
				$val = null;
				if (!is_array($replace)) {
					$val = $obj->get($replace);
				} else if (isset($replace['classMethod']) && method_exists($this, $replace['classMethod'])) {
					$val = $this->{$replace['classMethod']}($obj);	
				}
				if (!is_null($val)) {
					$msg = str_replace("~$search~", $val, $msg);
				}
			}
		}
		return $msg;
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
		if ($sendToProv) {
			$serviceCode = (isset($row['service']['code']) ? $row['service']['code'] : NULL);
			if (!$this->sendSlownessStateToProv($row['msisdn'], $serviceCode, $enterToDataSlowness)) {
				return;
			}
		}
		// Update subscriber in DB
		$subscribersColl = Billrun_Factory::db()->subscribersCollection();
		$findQuery = array_merge(Billrun_Util::getDateBoundQuery(), array('sid' => $row['sid']));
		if ($enterToDataSlowness) {
			$updateQuery = array('$set' => array('in_data_slowness' => true));
		} else {
			$updateQuery = array('$unset' => array('in_data_slowness' => 1));		
		}
		$subscribersColl->update($findQuery, $updateQuery);
	}
	
	/**
	 * Send request to slowdown / cancel slowdown of the subscriber
	 * @param string $msisdn
	 * @param string $subscriberSoc
	 */
	public function sendSlownessStateToProv($msisdn, $subscriberSoc = NULL, $enterToDataSlowness = true) {
		$slownessParams = $this->getDataSlownessParams($subscriberSoc);
		if (!isset($slownessParams['sendRequestToProv']) || !$slownessParams['sendRequestToProv']) {
			return;
		}
		$encoder = new Billrun_Encoder_Xml();
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
		$request = $encoder->encode($requestBody, $params);
		return $this->sendRequest($request, $slownessParams['requestUrl'], $slownessParams['sendRequestTries']);
	}
	
	/**
	 * Send request to send notification
	 * @todo Should be generic (same as sendSlownessStateToProv)
	 */
	public function sendNotification($notificationType, $msg, $msisdn) {
		$notificationParams = Billrun_Factory::config()->getConfigValue('realtimeevent.notification.' . $notificationType);
		if (!isset($notificationParams['sendRequestToProv']) || !$notificationParams['sendRequestToProv']) {
			return;
		}
		$encoder = new Billrun_Encoder_Xml();
		$requestBody = array(
			'HEADER' => array(
				'APPLICATION_ID' => $notificationParams['applicationId'],
				'COMMAND' => $notificationParams['command'],
			),
			'PARAMS' => array(
				'SENDER' => $notificationParams['sender'],
				'USER_ID' => $notificationParams['userId'],
				'SOURCE' => $notificationParams['source'],
				'MSG' => $msg,
				'TO_PHONE' => $msisdn,
			)
		);
		$params = array(
			'root' => 'REQUEST',
			'addHeader' => false,
		);
		$request = $encoder->encode($requestBody, $params);
		return $this->sendRequest($request, $notificationParams['requestUrl'], $notificationParams['sendRequestTries']);
	}
	
	protected function sendRequest($request, $requestUrl, $numOfTries = 3) {
		$logColl = Billrun_Factory::db()->logCollection();
		$saveData = array(
			'source' => 'pelephonePlugin',
			'type' => 'sendRequest',
			'process_time' => new MongoDate(),
			'request' => $request,
			'response' => array(),
			'server_host' => gethostname(),
			'request_host' => $_SERVER['REMOTE_ADDR'],
			'rand' => rand(1,1000000),
			'time' => (microtime(1))*1000,
		);
		$saveData['stamp'] = Billrun_Util::generateArrayStamp($saveData);
		for ($i = 0; $i < $numOfTries; $i++) {
			Billrun_Factory::log('Sending request to prov, try number ' . ($i+1) . '. Details: ' . $request,  Zend_Log::DEBUG);
			$response = Billrun_Util::sendRequest($requestUrl, $request);
			if ($response) {
				array_push($saveData['response'], 'attempt ' . ($i+1) . ': ' . $response);
				Billrun_Factory::log('Got response from prov. Details: ' . $response,  Zend_Log::DEBUG);
				$decoder = new Billrun_Decoder_Xml();
				$response = $decoder->decode($response);
				if (isset($response['HEADER']['STATUS_CODE']) && 
					$response['HEADER']['STATUS_CODE'] === 'OK') {
					$logColl->save(new Mongodloid_Entity($saveData), 0);
					return true;
				}
			}
			
		}
		Billrun_Factory::log('No response from prov. Request details: ' . $request,  Zend_Log::ALERT);
		$logColl->save(new Mongodloid_Entity($saveData), 0);
		return false;
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
			$is_intl = isset($this->row['call_type']) && $this->row['call_type'] == '2';
			if (($this->isInterconnect($this->row) && $this->row['np_code'] != '831') || $is_intl) {
				// we are out of PL network
				array_push($pp_includes_external_ids, 6);
			}

			if ($is_intl) {
				array_push($pp_includes_external_ids, 3, 4);
			}

			$rate = Billrun_Factory::db()->ratesCollection()->getRef($this->row->get('arate'));
			if (isset($rate['params']['premium']) && $rate['params']['premium']) {
				array_push($pp_includes_external_ids, 3, 4, 5, 6, 7, 8);
			}
			
			$plan = Billrun_Factory::db()->plansCollection()->getRef($this->row['plan_ref']);
			// Only certain subscribers can use data from CORE BALANCE
			if ($this->row['type'] === 'gy' && isset($this->row['plan_ref'])) {
				if ($plan && (!isset($plan['data_from_currency']) || !$plan['data_from_currency'])) {
					array_push($pp_includes_external_ids, 1, 2, 9, 10);
				}
			}
			
			$pp_includes_external_ids = array_merge($pp_includes_external_ids, $this->getPPIncludesToExclude($plan, $rate));

			if (count($pp_includes_external_ids)) {
				$query['pp_includes_external_id'] = array('$nin' => $pp_includes_external_ids);
			}
		}
	}
		
	protected function getPPIncludesToExclude($plan, $rate) {
		$prepaidIncludesCollection = Billrun_Factory::db()->prepaidincludesCollection();
		$query = $this->getPPIncludesNotAllowedQuery($plan['name'], $rate['key']);
		$ppIncludes = $prepaidIncludesCollection->query($query)->cursor();
		$notAllowedPPIncludes = array();
		if ($ppIncludes->count() > 0) {
			$notAllowedPPIncludes = array_map(function($doc) {
				return $doc['external_id'];
			}, iterator_to_array($ppIncludes));
		}
		return array_values($notAllowedPPIncludes);
	}
	
	protected function getPPIncludesNotAllowedQuery($planName, $rateName) {
		$basePlanName = "BASE";
		return array('$or' => array(
			array('$and' => array(
				array("allowed_in." . $planName => array('$exists' => 1)),
				array("allowed_in." . $planName => array('$nin' => array($rateName))),
			)),
			array('$and' => array(
				array("allowed_in." . $planName => array('$exists' => 0)),
				array("allowed_in." . $basePlanName => array('$exists' => 1)),
				array("allowed_in." . $basePlanName => array('$nin' => array($rateName))),
			)),
		));
	}

	/**
	 * 
	 * @param Mongodloid_Entity $record
	 * @param Billrun_ActionManagers_Subscribers_Update $updateAction
	 */
	public function beforeSubscriberSave(&$record, Billrun_ActionManagers_Subscribers_Update $updateAction) {
		if (isset($record['service']) && 
			array_key_exists('code', $record['service']) &&
			$record['service']['code'] === NULL) {
			$record['in_data_slowness'] = FALSE;
		}
	}
	
	public function isFreeLine(&$row, &$isFreeLine) {
		$isFreeLine = false;
		if ($row['type'] === 'gy') {
			if ($this->isSubscriberInDataSlowness($row)) {
				$row['free_line_reason'] = 'In data slowness';
				$isFreeLine = true;
				return;
			} 
			if ($this->isSubscriberInMaxCurrency($row)) {
				$row['free_line_reason'] = 'Passed max currency';
				$isFreeLine = true;
				return;
			} 
		}
	}

	public function afterChargesCalculation(&$row, &$charges) {
		$balance = Billrun_Factory::db()->balancesCollection()->getRef($this->row['balance_ref']);
		if ($row['type'] === 'gy' &&
			in_array($balance['pp_includes_external_id'], array(1,2,9,10))) {
			$diff = $this->getSubscriberDiffFromMaxCurrency($row);
			
			if ($charges['total'] > $diff) {
				$row['over_max_currency'] = $charges['total'] - $diff;
				$charges['total'] = $diff;
			}
		}
	}

	/**
	 * Method to authenticate active directory login
	 * @param string $username
	 * @param string $password
	 */
	public function userAuthenticate($username, $password) {
		$billrun_auth = new Billrun_Auth('msg_type', 'UserAuthGroup', 'username', 'password');
		$billrun_auth->setIdentity($username);
		$billrun_auth->setCredential($password);
		$auth = Zend_Auth::getInstance();
		$result = $auth->authenticate($billrun_auth);
		if ($result->code == 0) {
			return false;
		}
		return $result;
	}
	
	protected function updateDataSlownessOnBalanceUpdate($balance, $subscriber) {
		if (isset($subscriber['in_data_slowness']) && $subscriber['in_data_slowness'] &&
			in_array($balance['pp_includes_external_id'], array(5, 8))) {
			$this->updateSubscriberInDataSlowness($subscriber, false, true);
		}
	}

}
