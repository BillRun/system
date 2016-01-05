<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing u-test controller class
 *
 * @package  Controller
 * @since    4.0
 */
class TestController extends Yaf_Controller_Abstract {

	/**
	 * base url for API calls
	 * 
	 * @var string
	 */
	protected $baseUrl = '';
	protected $baseApiUrl = '';
	
    /**
	 * unique ref for Data and API calls
	 *
	 * @var string
	 */
	protected $reference = '';

	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		Billrun_Factory::log('Start Unit testing');
		if (Billrun_Factory::config()->isProd()) {
			Billrun_Factory::log('Exit Unit testing. Unit testing not allowed on production');
			die();
		}
		$this->baseApiUrl = "http://" . gethostname() . $this->getRequest()->getBaseUri() . '/api';
		$this->reference = rand(1000000000, 9999999999);
		$this->baseUrl = $this->getRequest()->getBaseUri();
	}

	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function indexAction() {
		$this->getView()->baseUrl = $this->baseUrl;
		$formParams = $this->_getTestTestingData();
		foreach ($formParams as $name => $value) {
			$this->getView()->{$name} = $value;
		}
	}
	
	/**
	 * Test Result Page
	 * 
	 * @return void
	 */
	public function resultAction() {
		//redirect if to test page if data not exist
		if(empty($_SERVER['QUERY_STRING'])){
			$url = "http://" . $_SERVER['HTTP_HOST'] . "/" . $this->baseUrl . "/test";
			header("Location: $url");
			die();
		}
		
		$imsi = (string) filter_input(INPUT_GET, 'imsi'); //'425030002438039';
		$type = (string) filter_input(INPUT_GET, 'type');
		$scenarioData = (string) filter_input(INPUT_GET, 'scenario');
		$scenario = array_map('trim', explode("\n", trim($scenarioData)));
		$sid = (int) filter_input(INPUT_GET, 'sid');
		$amount = (int) filter_input(INPUT_GET, 'amount');
		$balanceType = (string) filter_input(INPUT_GET, 'balanceType');
		$removeLines = filter_input(INPUT_GET, 'removelines');

		if (empty($sid)) {
			$sid = $this->_getSid($imsi);
		}
		if($removeLines == 1){
		// Reset - remove all linese before test
			$this->_resetLines($sid);
		}
		// Get balance before scenario
		$balance['before'] = $this->_getBalance($sid);
		// Applay scenarion by type
		switch ($type) {
			case 'data':
				$this->_dataScenario($scenario, $imsi);
				break;
			case 'call':
				$this->_callScenario($scenario, $imsi);
				break;
			case 'addBalance':
				$this->_addBalance($sid, $amount, $balanceType);
				break;
		}
		
		// Get all lines created during scenarion
		$lines = $this->_getLines($sid, (addBalance == $type));
		
		// Get balance after scenario
		$balance['after'] = $this->_getBalance($sid);

		$this->getView()->baseUrl = $this->baseUrl;
		$this->getView()->lines = $lines;
		$this->getView()->balances = $balance;
	}

	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function _dataScenario($scenario, $imsi) {
		foreach ($scenario as $index => $name) {
			$nameAndUssage = explode("|", $name);
			$args = array(
				'imsi' => $imsi,
				'requestNum' => ($index + 1),
				'type' => $nameAndUssage[0],
				'usedUnits' => ($nameAndUssage[1]) ? $nameAndUssage[1] : 1000
			);
			$data = $this->_getDataData($args);
			$this->sendRequest(array('usaget' => 'data', 'request' => $data));
		}
	}

	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function _callScenario($scenario, $imsi) {
		foreach ($scenario as $index => $name) {
			$nameAndUssage = explode("|", $name);
			$args = array(
				'imsi' => $imsi,
				'type' => $nameAndUssage[0],
				'duration' => ($nameAndUssage[1]) ? $nameAndUssage[1] : 4000
			);
			$data = $this->_getCallData($args);
			$this->sendRequest(array('usaget' => 'call', 'request' => $data));
		}
	}

	/**
	 * Send request
	 * 
	 * @param Array $data key value array
	 * @param String $endpoint API endpoint
	 */
	protected function sendRequest($data = array(), $endpoint = 'realtimeevent') {
		//$data['XDEBUG_SESSION_START'] = 'netbeans-xdebug';
		$params = http_build_query($data);
		$endpoint = trim("/" . $endpoint); // 'realtimeevent' / 'balances'
		$URL = $this->baseApiUrl . $endpoint . '?' . $params;
		Billrun_Factory::log('SEND REQUEST' . $URL);
		Billrun_Util::sendRequest($URL);
	}

	/**
	 * Get data for DATA request
	 * @param String $type init / update / final
	 * @param Array $data : imsi, requestNum
	 * @return JSON string
	 */
	protected function _getDataData($data) {
		$type = $data['type'];
		$imsi = $data['imsi'];
		$usedUnits = (int)$data['usedUnits'];
		$requestNum = $data['requestNum'];

		$request = array(
			//"requestType" => "1",
			//"requestNum" => 1,
			"sessionId" => $this->reference,
			"eventTimeStamp" => "20151122", // ??????
			"imsi" => $imsi,
			"imei" => "3542010614744704",
			"msisdn" => "9725050500",
			"msccData" => array(
				array(
					"event" => "initial",
					"reportingReason" => "0",
					"serviceId" => "400700",
					"ratingGroup" => "92",
					"requestedUnits" => 1000,
					//"usedUnits" => 1000
				),
				"Service" => array(
					"PdnConnectionId" => "0",
					"PdpAddress" => "10.161.48.3",
					"CalledStationId" => "test-sacc.labpelephone.net.il",
					"MccMnc" => "42503",
					"GgsnAddress" => "91.135.99.226",
					"SgsnAddress" => "91.135.96.3",
					"ChargingId" => "0",
					"GPRSNegQoSProfile" => "0",
					"ChargingCharacteristics" => "0800",
					"PDPType" => "0",
					"SGSNMCCMNC" => "42503",
					"GGSNMCCMNC" => "0",
					"CGAddress" => "0.0.0.0",
					"NSAPI" => "5",
					"SessionStopIndicator" => "0",
					"SelectionMode" => "1",
					"RATType" => array("1"),
					"MSTimeZone" => array("128", "0"),
					"ChargingRuleBaseName" => "0",
					"FilterId" => "0"
				)
			)
		);

		switch ($type) {
			case 'init':
				$request['requestType'] = "1";
				$request['requestNum'] = $requestNum; //"1";
				break;
			case 'update':
				$request['requestType'] = "2";
				$request['requestNum'] = $requestNum; //"2";
				$request['msccData'][0]['usedUnits'] = $usedUnits;
				break;
			case 'final':
				$request['requestType'] = "3";
				$request['requestNum'] = $requestNum; //"3";
				$request['msccData'][0]['usedUnits'] = $usedUnits;
				break;
			default: return NULL;
				break;
		}

		return json_encode($request);
	}

	/**
	 * Get data for CALL request
	 * @param String $type start_call / answer_call / reservation_time / release_call
	 * @param Array $data : imsi
	 * @return XML string
	 */
	protected function _getCallData($data) {
		//Billrun_Util::msisdn('509889899');
		$type = $data['type'];
		$duration = $data['duration'];
		$imsi = $data['imsi'];
		$request = '<?xml version = "1.0" encoding = "UTF-8"?>';
		switch ($type) {
			case 'start_call': $request .= '<request><api_name>start_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $this->reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><dialed_digits>0390222222</dialed_digits><connected_number>0390222222</connected_number><event_type>2</event_type><service_key>61</service_key><vlr>972500000701</vlr><location_mcc>425</location_mcc><location_mnc>03</location_mnc><location_area>7201</location_area><location_cell>53643</location_cell><time_date>2015/08/13 11:59:03</time_date><call_type>x</call_type></request>';
				break;
			case 'answer_call': $request .= '<request><api_name>answer_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $this->reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><dialed_digits>0390222222</dialed_digits><connected_number>0390222222</connected_number><time_date>2015/08/13 11:59:03.325</time_date><call_type>x</call_type></request>';
				break;
			case 'reservation_time': $request .= '<request><api_name>reservation_time</api_name><calling_number>972502145131</calling_number><call_reference>' . $this->reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><connected_number>0390222222</connected_number><time_date>2015/08/13 11:59:03.423</time_date></request>';
				break;
			case 'release_call': $request = '<request><api_name>release_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $this->reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><connected_number>0390222222</connected_number><time_date>2015/08/13 11:59:03.543</time_date><duration>' . $duration . '</duration><scp_release_cause>mmm</scp_release_cause><isup_release_cause>nnn</isup_release_cause><call_leg>x</call_leg></request>';
				break;
			default: $request = NULL;
				break;
		}
		return $request;
	}

	/**
	 * Get data for AddBalance request
	 * @param String $type start_call / answer_call / reservation_time / release_call
	 * @param Array $data : imsi
	 * @return XML string
	 */
	protected function _getAddBalanceData($data) {
		$sid = $data['sid'];
		$balance_type = $data['balance_type'];
		$amount = (-1) * $data['amount'];
		$request = array(
			'method' => 'update',
			'sid' => $sid,
			'query' => json_encode(["pp_includes_name" => $balance_type]),
			'upsert' => json_encode(["value" => $amount, "expiration_date" => "2016-07-01T00:00:00+02:00"])
		);
		return $request;
	}

	
	/**
	 * Delete all lines by SID 
	 * @param type $sids
	 */
	protected function _resetLines($sid) {
		Billrun_Factory::db()->linesCollection()->remove(array('sid' => $sid));
		
	}

	protected function _getSid($imsi) {
		$searchQuery = ['imsi' => $imsi];
		$cursor = Billrun_Factory::db()->subscribersCollection()->query($searchQuery)->cursor()->limit(100000);
		foreach ($cursor as $row) {
			return $row['sid'];
		}
		return NULL;
	}

	protected function _getBalance($sid) {
		$balances = array();
		$searchQuery = ["sid" => $sid];
		$cursor = Billrun_Factory::db()->balancesCollection()->query($searchQuery)->cursor()->limit(100000);
		foreach ($cursor as $row) {
			if($row['charging_by_usaget'] == 'total_cost'){
				$amount = floatval($row['balance']['cost']);
			} else {
				$amount = $row['balance']['totals'][$row["charging_by_usaget"]][$row["charging_by"]];
			}
			$balances[(string) $row['_id']] = array(
				'amount' => -1 * number_format($amount,3),
				'charging_by_usaget' => $row["charging_by_usaget"],
				'charging_by' => $row["charging_by"]
			);
		}
		return $balances;
	}

	protected function _getLines($sid, $charging = false) {
		$lines = array();
		$amount = 0;
		
		if($charging){
			$searchQuery = array(
				"sid" => $sid,
				"type" => 'charging'
			);
		} else {
			$searchQuery = array(
				"sid" => $sid,
				'$or' => array(
					array("session_id" => (int)$this->reference),
					array("call_reference" => (string)$this->reference)),
			);
		}
		
		$cursor = Billrun_Factory::db()->linesCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['urt' => 1]);
		foreach ($cursor as $row) {
			$amount += $row['aprice'];
			$lines['rows'][] = array(
				'time_date' => date('d/m/Y H:i:s', $row['urt']->sec),
				'record_type' => $row['record_type'],
				'aprice' => $row['aprice'],
				'usaget' => $row['usaget'],
				'usagev' => $row['usagev'],
				'balance_before' => number_format($row['balance_before'],3),
				'balance_after' => number_format($row['balance_after'],3),
				'arate' => (string)$row['arate']['$id']
			);
		}
		$lines['total'] = $amount;
		$lines['ref'] = $charging ? "Charging" : $this->reference;
		return $lines;
	}

	protected function _addBalance($sid, $amount, $type) {
		$args = array('sid' => $sid, 'amount' => $amount, 'balance_type' => $type);
		$data = $this->_getAddBalanceData($args);
		$this->sendRequest($data, 'balances');
	}
	
	protected function _getTestTestingData(){
		$output = array();
		$cursor = Billrun_Factory::db()->prepaidincludesCollection()->query()->cursor()->limit(100000)->sort(['urt' => 1]);
		foreach ($cursor as $row) {
			$output['balance_types'][] = $row['name'];
		}
		
		$output['call_scenario'] = "start_call\nanswer_call\nreservation_time\nrelease_call|5000";
		$output['data_scenario'] = "init\nupdate|1000\nupdate|800\nfinal|500";
		$output['imsi'] = "425030002438039";
		$output['sid'] = "546918666";
		return $output;
	}

}
