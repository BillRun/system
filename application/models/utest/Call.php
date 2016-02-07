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
		$calling_number = Billrun_Util::filter_var($this->controller->getRequest()->get('msisdn'), FILTER_SANITIZE_STRING);
		$called_number = Billrun_Util::filter_var($this->controller->getRequest()->get('called_number'), FILTER_SANITIZE_STRING);
		$call_type = Billrun_Util::filter_var($this->controller->getRequest()->get('call_type'), FILTER_SANITIZE_STRING);
		$time_date = Billrun_Util::filter_var($this->controller->getRequest()->get('time_date'), FILTER_SANITIZE_STRING);
		$send_time_date = Billrun_Util::filter_var($this->controller->getRequest()->get('send_time_date'), FILTER_SANITIZE_STRING);
		$np_code = Billrun_Util::filter_var($this->controller->getRequest()->get('np_code'), FILTER_SANITIZE_STRING);
		$send_np_code = Billrun_Util::filter_var($this->controller->getRequest()->get('send_np_code'), FILTER_SANITIZE_STRING);
		
		//Run test scenario
		foreach ($scenario as $index => $name) {
			$nameAndUssage = explode("|", $name);
			$params = array(
				'msisdn' => $calling_number,
				'dialedDigits' => $called_number,
				'duration' => isset($nameAndUssage[1]) ? ($nameAndUssage[1]*10) : 4800, // default 8 minutes
				'type' => $nameAndUssage[0],
				'call_type' => $call_type
			);
			if($send_np_code === 'on'){
				$params['np_code'] = $np_code;
			}
			if($send_time_date === 'on'){
				$params['time_date'] = date_format(date_add(date_create_from_format('d/m/Y H:i', $time_date), new DateInterval('PT' . $index . 'S')), 'Y/m/d H:i:s.000'); // 2015/08/13 11:59:03.325
			}
			$data = $this->getRequestData($params);
			$this->controller->sendRequest(array('usaget' => 'call', 'request' => $data));
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
		$duration = $params['duration'];
		$call_type = $params['call_type'];
		$dialedDigits = $params['dialedDigits'];
		$time_date = isset($params['time_date']) ? $params['time_date'] : date_format(date_create(), 'Y/m/d H:i:s.000');

		$data = array(
			'api_name' => $type,
			'calling_number' => $msisdn,
			'call_reference' => $this->controller->getReference(),
			'call_id' => 'rm7xxxxxxxxx',
			'connected_number' =>  $dialedDigits,
			'time_date' => $time_date,
		);
		
		if (isset($params['np_code'])) {
			$data['np_code'] = $params['np_code'];
		}

		switch ($type) {
			case 'start_call':
				$data['dialed_digits'] = $dialedDigits;
				$data['event_type'] = 2;
				$data['service_key'] = 61;
				$data['vlr'] = 972500000701;
				$data['location_mcc'] = 425;
				$data['location_mnc'] = 03;
				$data['location_area'] = 7201;
				$data['location_cell'] = 53643;
				$data['call_type'] = $call_type;
				break;
			case 'answer_call':
				$data['dialed_digits'] = $dialedDigits;
				$data['call_type'] = $call_type;
				break;
			case 'reservation_time':
				break;
			case 'release_call':
				$data['duration'] = $duration;
				$data['scp_release_cause'] = 'tmp';
				$data['isup_release_cause'] = 'tmp';
				$data['call_leg'] = 'x'; //(call party terminated the call: 0 – MSC, 1 – originator (Calling party), 2 – terminator (Called party), 3 – SCP, 4 - Billing)
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
