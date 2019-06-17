<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test call model class
 *
 * @package  Models
 * @subpackage uTest
 * @since    4.0
 */
class utest_CallModel extends utest_AbstractUtestModel {

	public function __construct(\UtestController $controller) {
		parent::__construct($controller);
		$this->result = array('balance_before', 'balance_after', 'lines');
		$this->label = 'Call | Real-time event';
	}

	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function doTest() {
		//Get test params
		$scenarioData = Billrun_Util::filter_var($this->controller->getRequest()->get('scenario'), FILTER_SANITIZE_STRING);
		$scenario = array_map('trim', explode("\n", trim($scenarioData)));
		$msc = Billrun_Util::filter_var($this->controller->getRequest()->get('msc'), FILTER_SANITIZE_STRING);
		$calling_number = Billrun_Util::filter_var($this->controller->getRequest()->get('msisdn'), FILTER_SANITIZE_STRING);
		$imsi = Billrun_Util::filter_var($this->controller->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);
		$called_number = Billrun_Util::filter_var($this->controller->getRequest()->get('called_number'), FILTER_SANITIZE_STRING);
		$call_type = Billrun_Util::filter_var($this->controller->getRequest()->get('call_type'), FILTER_SANITIZE_STRING);
		$call_tech = Billrun_Util::filter_var($this->controller->getRequest()->get('call_tech'), FILTER_SANITIZE_STRING);
		$time_date = Billrun_Util::filter_var($this->controller->getRequest()->get('time_date'), FILTER_SANITIZE_STRING);
		$send_time_date = Billrun_Util::filter_var($this->controller->getRequest()->get('send_time_date'), FILTER_SANITIZE_STRING);
		$np_code = Billrun_Util::filter_var($this->controller->getRequest()->get('np_code'), FILTER_SANITIZE_STRING);
		$send_np_code = Billrun_Util::filter_var($this->controller->getRequest()->get('send_np_code'), FILTER_SANITIZE_STRING);
		$mode = Billrun_Util::filter_var($this->controller->getRequest()->get('mode'), FILTER_SANITIZE_STRING);
		$send_mode = Billrun_Util::filter_var($this->controller->getRequest()->get('send_mode'), FILTER_SANITIZE_STRING);

		if ($call_tech == 'UMTS') {
			$subscriber = Billrun_Factory::db()->subscribersCollection()->query(array('imsi' => $imsi))->cursor()->sort(array('_id' => -1))->limit(1)->current()->getRawData();
			$msisdn = $subscriber['msisdn'];
		} else {
			$msisdn = $calling_number;
		}

		//Run test scenario
		foreach ($scenario as $index => $name) {
			$nameAndUssage = explode("|", $name);
			$params = array(
				'msisdn' => $msisdn,
				'imsi' => $imsi,
				'dialedDigits' => $called_number,
				'duration' => isset($nameAndUssage[1]) ? ($nameAndUssage[1] * 10) : 4800, // default 8 minutes
				'type' => $nameAndUssage[0],
				'call_type' => $call_type,
				'call_tech' => $call_tech,
				'msc' => $msc
			);
			if ($send_np_code === 'on') {
				$params['np_code'] = $np_code;
			}
			if ($send_time_date === 'on') {
				$params['time_date'] = date_format(date_add(date_create_from_format('d/m/Y H:i', $time_date), new DateInterval('PT' . $index . 'S')), 'Y/m/d H:i:s.000'); // 2015/08/13 11:59:03.325
			}
			$data = $this->getRequestData($params);
			$requestBody = array('usaget' => 'call', 'request' => $data);
			if ($send_mode === 'on') {
				$requestBody['mode'] = $mode;
			}
			$this->controller->sendRequest($requestBody);
			sleep(1);
		}
	}

	/**
	 * Get data for CALL request
	 * @param String $type start_call / answer_call / reservation_time / release_call
	 * @param Array $data : imsi
	 * @return XML string
	 */
	protected function getRequestData($params) {
		$type = $params['type'];
		$msisdn = $params['msisdn'];
		$imsi = $params['imsi'];
		$duration = $params['duration'];
		$call_type = $params['call_type'];
		$call_tech = $params['call_tech'];
		$dialedDigits = $params['dialedDigits'];
		$msc = $params['msc'];
		$time_date = isset($params['time_date']) ? $params['time_date'] : date_format(date_create(), 'Y/m/d H:i:s.000');

		$data = array(
			'api_name' => $type,
			'calling_number' => $msisdn,
			'call_reference' => $this->controller->getReference(),
			'call_id' => 'rm7xxxxxxxxx',
			'connected_number' => $dialedDigits,
			'time_date' => $time_date,
			'vlr' => $msc
		);

		if ($call_tech == 'UMTS') {
			$data['imsi'] = $imsi;
		}

		if (isset($params['np_code'])) {
			$data['np_code'] = $params['np_code'];
		}

		if (isset($params['vlr'])) {
			$data['vlr'] = $params['vlr'];
		}

		switch ($type) {
			case 'start_call':
				$data['dialed_digits'] = $dialedDigits;
				$data['event_type'] = 2;
				$data['service_key'] = 61;
				if ($call_tech == 'UMTS') {
					$data['location_mnc'] = 03;
					$data['location_area'] = 7201;
					$data['location_cell'] = 53643;
				}
				$data['call_type'] = $call_type;
				break;
			case 'answer_call':
				$data['dialed_digits'] = $dialedDigits;
				$data['call_type'] = $call_type;
				break;
			case 'reservation_time':
				$data['call_type'] = $call_type;
				break;
			case 'release_call':
				$data['duration'] = $duration;
				$data['scp_release_cause'] = 'tmp';
				$data['isup_release_cause'] = 'tmp';
				$data['call_leg'] = 'x'; //(call party terminated the call: 0 – MSC, 1 – originator (Calling party), 2 – terminator (Called party), 3 – SCP, 4 - Billing)
				$data['call_type'] = $call_type;
				break;
			default:
				$data = array(); // Case with Error, not support API name
				break;
		}

		$xmlParams = array('rootElement' => 'request');
		$xmlRequest = Billrun_Util::arrayToXml($data, $xmlParams);
		return $xmlRequest;
	}

}
