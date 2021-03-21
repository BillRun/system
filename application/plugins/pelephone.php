<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	
	/**
	 * flag to indicate that customer notification already sent and not to send another one (on balance update)
	 * @var boolean
	 */
	protected $notificationSent = false;

	/**
	 * method to extend rate query
	 * 
	 * @param array $query
	 * @param array $row
	 * @param Billrun_Calculator_Rate $calculator calculator instance that trigger this event
	 * 
	 * @return void
	 */
	public function extendRateParamsQuery(&$query, &$row, &$calculator) {
		if ($this->isInterconnect($row)) {
			$numberField = $this->getNumberField($row);
			$prefixes = Billrun_Util::getPrefixes($row['np_code'] . $calculator->getCleanNumber($row[$numberField]));
			$query[0]['$match']['params.prefix']['$in'] = $prefixes;
			$query[4]['$match']['params_prefix']['$in'] = $prefixes;
		}
		return;
		if (!in_array($row['usaget'], array_merge(Billrun_Util::getCallTypes(), array('sms', 'mms')))) {
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

	protected function canUseDataFromCurrencyBalances($row, $plan = null) {
		if (is_null($plan)) {
			$plan = Billrun_Factory::db()->plansCollection()->getRef($row['plan_ref']);
		}
		return ($plan && isset($plan['data_from_currency']) && $plan['data_from_currency']);
	}

	protected function hasAvailableBalances($row) {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['sid'] = $row['sid'];
		if ($this->canUseDataFromCurrencyBalances($row)) {
			$query['$or'] = array(
				array('charging_by_usaget' => 'data'),
				array('charging_by' => 'total_cost')
			);
		} else {
			$query['charging_by_usaget'] = 'data';
		}

		$availableBalances = Billrun_Factory::db()->balancesCollection()->query($query)->cursor()->current();
		return !$availableBalances->isEmpty();
	}

	protected function canSubscriberEnterDataSlowness($row) {
		return isset($row['service']['code']) &&
			$this->validateSOC($row['service']['code']) &&
			$this->hasAvailableBalances($row);
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
		$startTime = Billrun_Billrun::getStartTimeByPeriod($period);
		$match = array(
			'type' => 'gy',
			'sid' => $row['sid'],
			'pp_includes_external_id' => array(
				'$in' => array(1, 2, 9, 10), // @TODO change to config values
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

		return array(array('$match' => $match), array('$group' => $group));
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
		$subscriberQuery = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(), array('sid' => $subscriberId));

		$coll = Billrun_Factory::db()->subscribersCollection();
		$results = $coll->query($subscriberQuery)->cursor()->limit(1)->current();
		if ($results->isEmpty()) {
			return false;
		}
		return $results;
	}

	protected function getSubscriberPlan($subscriber) {
		$planQuery = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(), array('name' => $subscriber['plan']));

		$coll = Billrun_Factory::db()->plansCollection();
		$results = $coll->query($planQuery)->cursor()->limit(1)->current();
		if ($results->isEmpty()) {
			return false;
		}
		return $results;
	}

	public function afterSubscriberBalanceAutoRenewUpdate($autoRenewRecord) {
		$subscriber = $this->getSubscriber($autoRenewRecord['sid']);
		if (!$subscriber) {
			return false;
		}
		$this->updateSubscriberInDataSlowness($subscriber->getRawData(), false, true);
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

	public function handleSendRquestErrors($sids) {
		foreach ($sids as $sid) {
			$subscriber = $this->getSubscriber($sid);
			if (!$subscriber) {
				continue;
			}
			$this->updateSubscriberInDataSlowness($subscriber, false, true);
		}
	}

	public function afterUpdateSubscriberAfterBalance($row, $balance, $balanceBefore, $balanceAfter) {
		if (Billrun_Util::isEqual($balanceBefore, $balanceAfter, 0.00001)) {
			return;
		}
		$plan = Billrun_Factory::db()->plansCollection()->getRef($row['plan_ref']);
		$this->handleBalanceNotifications("BALANCE_AFTER", $plan, Billrun_Util::msisdn($row['sid']), $balance, $balanceBefore);
	}

	protected function shouldSendNotification($source) {
		$dontSendNotifications = Billrun_Factory::config()->getConfigValue('realtimeevent.notifications.dontSendNotification', array());
		return isset($source['$ref']) &&
			!in_array($source['$ref'], $dontSendNotifications);
	}

	public function afterBalanceLoad($balance, $subscriber, $source) {
		if (!$balance) {
			return;
		}
		$this->updateDataSlownessOnBalanceUpdate($balance, $subscriber);
		if (!$this->shouldSendNotification($source)) {
			return;
		}
		$update = array(
			'$unset' => array(
				'notifications_sent' => 1,
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

	protected function needToSendNotification($type, $notification, $balance, $balanceBefore = 0) {
		switch ($type) {
			case ('BALANCE_AFTER'):
				if (!$this->notificationSent) {
					$this->notificationSent = true;
					return $balanceBefore >= $notification['value'];
				} else {
					return false;
				}
			case ('BALANCE_LOAD'):
				if (!$this->notificationSent) {
					$this->notificationSent = true;
					return in_array($balance->get('pp_includes_external_id'), $notification['pp_includes']);
				} else {
					return false;
				}
			case ('BALANCE_EXPIRATION'):
				return true;
		}
		return false;
	}

	public function handleBalanceNotifications($type, $plan, $msisdn, $balance, $balanceBefore = 0) {
		try {
			if (!$balance || !$plan || !isset($plan['notifications_threshold'])) {
				return;
			}
			$notificationKey = $this->getNotificationKey($type, $balance);
			foreach ($plan['notifications_threshold'][$notificationKey] as $index => $notification) {
				if (!$notificationSent = $balance->get('notifications_sent')) {
					$notificationSent = array($notificationKey => array());
				}
				if (isset($notificationSent[$notificationKey]) && in_array($index, $notificationSent[$notificationKey])) { // If the notification was already sent
					continue;
				}
				if ($this->needToSendNotification($type, $notification, $balance, $balanceBefore)) {
					$modifyParams = array('balance' => $balance);
					$msg = $this->modifyNotificationMessage($notification['msg'], $modifyParams);
					$this->sendNotification($notification['type'], $msg, $msisdn);
					if (is_null($notificationSent[$notificationKey])) {
						$notificationSent[$notificationKey] = array($index);
					} else {
						array_push($notificationSent[$notificationKey], $index);
					}
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
	
	/**
	 * @param type $obj
	 * @param type $data
	 * @return int
	 */
	protected function getPositiveValue($obj, $data) {
		if (!isset($data['field']) || !is_numeric($val = $obj->get($data['field']))) {
			return 0;
		}
		if ($val > 0) { // on positive value subscriber have over-due balance
			return 0;
		}
		return abs(round($val));
	}
	
	/**
	 * 
	 * @param obj $obj
	 * @param array $data
	 * @return type
	 */
	protected function getPositiveValuePrettifyDuration($obj, $data) {
		$timePositiveValue = $this->getPositiveValue($obj, $data);
		return Billrun_Util::secondFormat($timePositiveValue, 'minute', 0, false, 'ceil', '', '');
	}
	
	/**
	 * @param type $obj
	 * @param type $data
	 * @return type
	 */
	protected function getDataValuePrettify($obj, $data) {
		$val = $this->getPositiveValue($obj, $data);
		$dataUnit = isset($data['units']) ? $data['units'] : 'MB';
		return Billrun_Util::byteFormat($val, $dataUnit, 2, true);
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
					$val = $this->{$replace['classMethod']}($obj, $replace);
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
				$row['entering_data_slowness'] = TRUE;
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
			if (!$this->sendSlownessStateToProv($row['msisdn'], $row['sid'], $serviceCode, $enterToDataSlowness)) {
				return;
			}
		}
	}

	/**
	 * Send request to slowdown / cancel slowdown of the subscriber
	 * @param string $msisdn
	 * @param string $subscriberSoc
	 */
	public function sendSlownessStateToProv($msisdn, $sid, $subscriberSoc = NULL, $enterToDataSlowness = true) {
		Billrun_Factory::log("Send to provisioning slowness of subscriber " . $msisdn . " with status " . ($enterToDataSlowness ? "true" : "false"), Zend_Log::INFO);
		$slownessParams = $this->getDataSlownessParams($subscriberSoc);
		if (!isset($slownessParams['sendRequestToProv']) || !$slownessParams['sendRequestToProv']) {
			return true;
		}
		$encoder = new Billrun_Encoder_Xml();
		$requestBody = array(
			'HEADER' => array(
				'APPLICATION_ID' => $slownessParams['applicationId'],
				'COMMAND' => $slownessParams['command'],
			),
			'PARAMS' => array(
				'MSISDN' => Billrun_Util::msisdn($msisdn),
				'SLOWDOWN_SPEED' => ($enterToDataSlowness ? $slownessParams['speed'] : ''),
				'SLOWDOWN_SOC' => $slownessParams['soc'],
			)
		);
		$params = array(
			'root' => 'REQUEST',
			'addHeader' => false,
		);
		$request = $encoder->encode($requestBody, $params);
		$additionalParams = array(
			'dataSlownessRequest' => true,
			'enterDataSlowness' => $enterToDataSlowness,
			'sid' => $sid
		);
		return $this->sendRequest($request, $slownessParams['requestUrl'], $additionalParams, $slownessParams['sendRequestTries'], true);
	}

	/**
	 * Send request to send notification
	 * @todo Should be generic (same as sendSlownessStateToProv)
	 */
	public function sendNotification($notificationType, $msg, $msisdn, $additionalParams = array()) {
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
				'TO_PHONE' => Billrun_Util::msisdn($msisdn),
			)
		);
		$params = array(
			'root' => 'REQUEST',
			'addHeader' => false,
		);
		$request = $encoder->encode($requestBody, $params);
		return $this->sendRequest($request, $notificationParams['requestUrl'], $additionalParams, $notificationParams['sendRequestTries'], true);
	}

	protected function sendRequest($request, $requestUrl, $additionalParams = array(), $numOfTries = 3, $inDifferentFork = false) {
		if ($inDifferentFork) {
			return $this->sendRequestInDifferentFork($request, $requestUrl, $additionalParams, $numOfTries);
		}
		$start_time = microtime(1);
		$logColl = Billrun_Factory::db()->logCollection();
		$saveData = array(
			'source' => 'pelephonePlugin',
			'type' => 'sendRequest',
			'process_time' => new MongoDate(),
			'request' => $request,
			'response' => array(),
			'server_host' => Billrun_Util::getHostName(),
			'server_pid' => Billrun_Util::getPid(),
			'request_host' => $_SERVER['REMOTE_ADDR'],
			'rand' => rand(1, 1000000),
		);
		$saveData['stamp'] = Billrun_Util::generateArrayStamp($saveData);
		for ($i = 0; $i < $numOfTries; $i++) {
			Billrun_Factory::log('Sending request to prov, try number ' . ($i + 1) . '. Details: ' . $request, Zend_Log::DEBUG);
			$response = Billrun_Util::sendRequest($requestUrl, $request);
			if ($response) {
				array_push($saveData['response'], 'attempt ' . ($i + 1) . ': ' . $response);
				Billrun_Factory::log('Got response from prov. Details: ' . $response, Zend_Log::DEBUG);
				$decoder = new Billrun_Decoder_Xml();
				$response = $decoder->decode($response);
				if (isset($response['HEADER']['STATUS_CODE']) &&
					$response['HEADER']['STATUS_CODE'] === 'OK') {
					$saveData['time'] = (microtime(1) - $start_time) * 1000;
					$saveData['success'] = true;
					$logColl->save(new Mongodloid_Entity($saveData), 0);
					$this->updateSubscriberInDB($additionalParams);
					return true;
				}
			}
		}
		Billrun_Factory::log('No response from prov. Request details: ' . $request, Zend_Log::ALERT);
		$saveData['time'] = (microtime(1) - $start_time) * 1000;
		$saveData['success'] = false;
		$logColl->save(new Mongodloid_Entity($saveData), 0);
		return false;
	}
	
	protected function updateSubscriberInDB($additionalParams) {
		if (isset($additionalParams['dataSlownessRequest']) && $additionalParams['dataSlownessRequest']) {
			$enterDataSlowness = $additionalParams['enterDataSlowness'];
			$sid = $additionalParams['sid'];
			// Update subscriber in DB
			$subscribersColl = Billrun_Factory::db()->subscribersCollection();
			$findQuery = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(), array('sid' => $sid));
			if ($enterDataSlowness) {
				$updateQuery = array('$set' => array(
					'in_data_slowness' => true,
					'data_slowness_enter' => new MongoDate()
					)
				);
			} else {
				$updateQuery = array(
					'$unset' => array('in_data_slowness' => 1),
					'$set' => array('data_slowness_exit' => new MongoDate()),
				);
			}
			$subscribersColl->update($findQuery, $updateQuery);
		}
	}

	protected function sendRequestInDifferentFork($request, $requestUrl, $additionalParams = array(), $numOfTries = 3) {
		$url = Billrun_Factory::config()->getConfigValue('realtimeevent.notification.sms.sendRequestForkUrl', '');
		if ($url === '') {
			return false;
		}
		$params = array(
			'request' => $request,
			'requestUrl' => $requestUrl,
			'numOfTries' => $numOfTries,
			'additionalParams' => $additionalParams,
		);
		Billrun_Util::forkProcessWeb($url, $params);
		return true;
	}

	/**
	 * method to check if billing row is interconnect (not under PL network)
	 * 
	 * @param array $row the row to check
	 * 
	 * @return boolean true if not under PL network else false
	 */
	protected function isInterconnect($row) {
		return isset($row['np_code']) && is_string($row['np_code']) && strlen($row['np_code']) > 2 && (!isset($row['call_type']) || !in_array($row['call_type'], array("11", "12")));
	}

	/**
	 * use to store the row to extend balance query (method getBalanceLoadQuery)
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
	public function getBalanceLoadQuery(&$query, &$timeNow, &$chargingType, &$usageType, $minUsage, $minCost, Billrun_Balance $balance) {
		if (!empty($this->row)) {
			$pp_includes_external_ids = array();
			// Only certain subscribers can use data from CORE BALANCE
			if ($this->row['type'] === 'gy' && isset($this->row['plan_ref'])) {
				if (!$this->canUseDataFromCurrencyBalances($this->row)) {
					array_push($pp_includes_external_ids, 1, 2, 9, 10); // todo: change to logic (charging_by = total_cost) instead of hard-coded values
				}
			}
			$pp_includes_exclude = $this->getPPIncludesToExclude($this->row->get('plan'), $this->row->get('arate_key'));
			if (!empty($pp_includes_exclude)) {
				$unique_pp_includes_external_ids = array_merge($pp_includes_external_ids, $pp_includes_exclude);
			} else {
				$unique_pp_includes_external_ids = $pp_includes_external_ids;
			}

			if (!empty($unique_pp_includes_external_ids) && is_array($unique_pp_includes_external_ids)) {
				$query['pp_includes_external_id'] = array('$nin' => $unique_pp_includes_external_ids);
			}
			
			$additionalUsageTypes = $this->getUsageTypesByAdditionalUsageType($usageType);
			foreach ($additionalUsageTypes as $additionalUsageType) {
				$query['$or'][] = array("balance.totals.$additionalUsageType.usagev" => array('$lte' => $minUsage));
				$query['$or'][] = array("balance.totals.$additionalUsageType.cost" => array('$lte' => $minCost));
			}
		}
	}
	
	/**
	 * this will return also available usage types (in balances) 
	 * according to the additional types set in the prepaid includes document.
	 * 
	 * @param type $usaget
	 * @return main usaget types
	 */
	protected function getUsageTypesByAdditionalUsageType($usaget) {
		$pp_includes_query = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(), array("additional_charging_usaget" => array('$in' => array($usaget))));
		$ppincludes = Billrun_Factory::db()->prepaidincludesCollection()->query($pp_includes_query)->cursor();
		$usageTypes = array();
		foreach ($ppincludes as $ppinclude) {
			$usageTypes[] = $ppinclude['charging_by_usaget'];
		}
		return array_unique($usageTypes);
	}

	protected function getPPIncludesToExclude($plan_name, $rate_key) {
		$prepaidIncludesCollection = Billrun_Factory::db()->prepaidincludesCollection();
		$query = $this->getPPIncludesNotAllowedQuery($plan_name, $rate_key);
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
	public function beforeSubscriberSave(&$record, $prevRecord, Billrun_ActionManagers_Subscribers_Update $updateAction) {
		$prevService = $prevRecord->get('service');
		$prevServiceCode = ($prevService && isset($prevService['code']) ? $prevService['code'] : NULL);
		if (isset($record['service']) &&
			array_key_exists('code', $record['service']) &&
			$record['service']['code'] === NULL &&
			isset($record['in_data_slowness']) &&
			$record['in_data_slowness']) {
			$record['in_data_slowness'] = FALSE;
			$this->sendSlownessStateToProv($record['msisdn'], $record['sid'], $prevServiceCode, false);
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
		if ($row['type'] !== 'gy') {
			return;
		}
		$balance = Billrun_Factory::db()->balancesCollection()->getRef($this->row['balance_ref']);
		if ($balance['charging_by'] == 'total_cost') {
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
		$writer = new XMLWriter();
		$writer->openMemory();
		$writer->startDocument('1.0', 'UTF-8');
		$writer->setIndent(4);
		$writer->startElement('REQUEST');
		$writer->startElement("HEADER");
		$writer->writeElement("COMMAND", "IT_UserAuthGroup");
		$writer->writeElement("APPLICATION_ID", 283);
		$writer->endElement();
		$writer->startElement("PARAMS");
		$writer->startElement("IT_IN_PARAMS");
		$writer->writeElement("User", $username);
		$writer->writeElement("PASSWORD", $password);
		$writer->startElement("MemberOf");
		$writer->writeElement("Group", "Billrun_read");
		$writer->writeElement("Group", "Billrun_write");
		$writer->writeElement("Group", "Billrun_admin");
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endElement();
		$writer->endDocument();
		$data = $writer->outputMemory();

		$res = Billrun_Util::sendRequest(Billrun_Factory::config()->getConfigValue('pelephone.ldapurl'), $data);
		// TODO: The next line disables the XML parsing error if we have an authentication problem
		// libxml_use_internal_errors(true);
		$xml = simplexml_load_string($res);
		if (!isset($xml->PARAMS->IT_OUT_PARAMS->STATUS[0]) || $xml->PARAMS->IT_OUT_PARAMS->STATUS[0] != 0) {
			return false;
		}
		return $res;
	}

	protected function updateDataSlownessOnBalanceUpdate($balance, $subscriber) {
		if (isset($subscriber['in_data_slowness']) && $subscriber['in_data_slowness'] &&
			in_array($balance['pp_includes_external_id'], array(5, 8))) { // todo: change to logic (charging_by = total_cost) instead of hard-coded values
			$this->updateSubscriberInDataSlowness($subscriber, false, true);
		}
	}

	/**
	 * method to extend realtime data
	 * 
	 * @param array $event event information
	 * @param string $usaget the usage type
	 * 
	 * @return void
	 */
	public function realtimeAfterSetEventData(&$event, &$usaget) {
		if (in_array($usaget, Billrun_Util::getCallTypes()) || $usaget === 'forward_call') {
			if (!isset($event['called_number'])) {
				if (isset($event['dialed_digits'])) {
					$event['called_number'] = $event['dialed_digits'];
				} else if (isset($event['connected_number'])) {
					$event['called_number'] = $event['connected_number'];
				}
			}

			$numberField = $this->getNumberField($event);
			if (stripos($usaget, 'roaming') === FALSE && !empty($event[$numberField]) && strlen($event[$numberField]) > 3 && substr($event[$numberField], 0, 3) == '972') {
				$number = $event[$numberField];
				if (substr($number, 0, 4) == '9721') {
					$prefix = '';
				} else {
					$prefix = '0';
				}
				$event[$numberField] = $prefix . substr($number, (-1) * strlen($number) + 3);
			} else if (stripos($usaget, 'roaming') !== FALSE) {
				if ($usaget == 'roaming_callback' && !empty($event['destination_number'])) {
					$event['called_number'] = $event['destination_number'];
				}
				if ($event['call_type'] == "11") { // roaming calls to israel, let's enforce country prefix if not already added
					$event[$numberField] = Billrun_Util::msisdn($event[$numberField]); // this will add 972
				} else if ($event['call_type'] >= "11") {
					$event[$numberField] = Billrun_Util::cleanLeadingZeros($event[$numberField]); // this will cleaning leading zeros and pluses
				}
			}
			// backward compatibility to local calls without vlr
			if (empty($event['vlr']) && stripos($usaget, 'roaming') === FALSE) {
				$event['vlr'] = '97250';
			}
		}
	}
	
	protected function getNumberField($row) {
		return ($this->isIncomingCall($row) ? 'calling_number' : 'called_number');
	}
	
	protected function isIncomingCall($row) {
		return isset($row['usaget']) && in_array($row['usaget'], Billrun_Factory::config()->getConfigValue('realtimeevent.incomingCallUsageTypes', array()));
	}

}
