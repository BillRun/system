<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
		$this->label = 'Call';
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
		$dialedDigits = Billrun_Util::filter_var($this->controller->getRequest()->get('dialedDigits'), FILTER_SANITIZE_STRING);
		$imsi = Billrun_Util::filter_var($this->controller->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);

		//Run test scenario
		foreach ($scenario as $index => $name) {
			$nameAndUssage = explode("|", $name);
			$params = array(
				'imsi' => $imsi,
				'type' => $nameAndUssage[0],
				'duration' => isset($nameAndUssage[1]) ? $nameAndUssage[1] : 4000,
				'dialedDigits' => $dialedDigits,
				'call_reference' => $this->controller->getReference()
			);
			$data = $this->getRequestData($params);
			$this->controller->sendRequest(array('usaget' => 'call', 'request' => $data));
		};
	}

	/**
	 * Get data for CALL request
	 * @param String $type start_call / answer_call / reservation_time / release_call
	 * @param Array $data : imsi
	 * @return XML string
	 */
	protected function getRequestData($params) {
		//Billrun_Util::msisdn('509889899');
		$type = $params['type'];
		$imsi = $params['imsi'];
		$duration = $params['duration'];
		$dialedDigits = $params['dialedDigits'];
		$call_reference = $params['call_reference'];

		$request = '<?xml version = "1.0" encoding = "UTF-8"?>';
		switch ($type) {
			case 'start_call': $request .= '<request><api_name>start_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $call_reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><dialed_digits>' . $dialedDigits . '</dialed_digits><connected_number>' . $dialedDigits . '</connected_number><event_type>2</event_type><service_key>61</service_key><vlr>972500000701</vlr><location_mcc>425</location_mcc><location_mnc>03</location_mnc><location_area>7201</location_area><location_cell>53643</location_cell><time_date>2015/08/13 11:59:03</time_date><call_type>x</call_type></request>';
				break;
			case 'answer_call': $request .= '<request><api_name>answer_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $call_reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><dialed_digits>' . $dialedDigits . '</dialed_digits><connected_number>' . $dialedDigits . '</connected_number><time_date>2015/08/13 11:59:03.325</time_date><call_type>x</call_type></request>';
				break;
			case 'reservation_time': $request .= '<request><api_name>reservation_time</api_name><calling_number>972502145131</calling_number><call_reference>' . $call_reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><connected_number>' . $dialedDigits . '</connected_number><time_date>2015/08/13 11:59:03.423</time_date></request>';
				break;
			case 'release_call': $request = '<request><api_name>release_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $call_reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><connected_number>' . $dialedDigits . '</connected_number><time_date>2015/08/13 11:59:03.543</time_date><duration>' . $duration . '</duration><scp_release_cause>mmm</scp_release_cause><isup_release_cause>nnn</isup_release_cause><call_leg>x</call_leg></request>';
				break;
			default: $request = NULL;
				break;
		}
		return $request;
	}
	
}
