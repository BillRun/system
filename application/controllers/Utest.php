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
 * @author	 Roman Edneral
 */
class UtestController extends Yaf_Controller_Abstract {

	/**
	 * base url for API calls
	 * 
	 * @var string
	 */
	protected $protocol = '';
	protected $subdomain = '';
	protected $siteUrl = '';
	protected $apiUrl = '';
	protected $conf = '';
	
    /**
	 * unique ref for Data and API calls
	 *
	 * @var string
	 */
	protected $reference = '';
	
    /**
	 * save all request and responce
	 *
	 * @var string
	 */
	protected $apiCalls = array();

	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		Billrun_Factory::log('Start Unit testing');
		if (Billrun_Factory::config()->isProd()) {
			Billrun_Factory::log('Exit Unit testing. Unit testing not allowed on production');
			die();
		}
		$this->protocol = (empty($this->getRequest()->getServer('HTTPS'))) ? 'http://' : 'https	://';
		$this->subdomain = $this->getRequest()->getBaseUri();
		$this->siteUrl = $this->protocol . $this->basehost .  $this->getRequest()->getServer('HTTP_HOST') . $this->subdomain;
		$this->apiUrl = $this->siteUrl . '/api';
		$this->reference = rand(1000000000, 9999999999);
		//Load Test conf file
		$this->conf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/utest/conf.ini'));
	}

	/**
	 * main action to do basic tests
	 * 
	 * @return void
	 */
	public function indexAction() {
		//var_dump(Billrun_Util::filter_var($this->getRequest()->get('imsi'), FILTER_SANITIZE_STRING));
		$this->getView()->subdomain = $this->subdomain;
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
		//redirect to test page if test data not exist
		if(empty($_SERVER['QUERY_STRING'])){
			header("Location: " . $this->siteUrl. "/utest");
			die();
		}
		$imsi	= Billrun_Util::filter_var($this->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);
		$type	= Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);
		$sid	= (int)Billrun_Util::filter_var($this->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$amount = (int)Billrun_Util::filter_var($this->getRequest()->get('amount'), FILTER_SANITIZE_NUMBER_INT);
		$scenarioData	= Billrun_Util::filter_var($this->getRequest()->get('scenario'), FILTER_SANITIZE_STRING);
		$scenario		= array_map('trim', explode("\n", trim($scenarioData)));
		$balanceType	= Billrun_Util::filter_var($this->getRequest()->get('balanceType'), FILTER_SANITIZE_STRING);
		$removeLines	= Billrun_Util::filter_var($this->getRequest()->get('removeLines'), FILTER_SANITIZE_STRING);
		$dialedDigits	= Billrun_Util::filter_var($this->getRequest()->get('dialedDigits'), FILTER_SANITIZE_STRING);


		if (empty($sid)) {
			$sid = $this->_getSid($imsi);
		}
		if($removeLines == 'remove'){
			//Remove all lines by SID
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
				$this->_callScenario($scenario, $imsi, $dialedDigits);
				break;
			case 'addBalance':
				$this->_addBalance($sid, $amount, $balanceType);
				break;
			case 'addCharge':
				$this->_addCharge($sid, $balanceType);
				break;
		}
		
		// Get all lines created during scenarion
		$lines = $this->_getLines($sid, (addBalance == $type));
		
		// Get balance after scenario
		$balance['after'] = $this->_getBalance($sid);

		$this->getView()->subdomain = $this->subdomain;
		$this->getView()->lines = $lines;
		$this->getView()->balances = $balance;
		$this->getView()->apiCalls = $this->apiCalls;
		$this->getView()->testType = ucfirst (preg_replace('/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]/', ' $0', $type));
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
	public function _callScenario($scenario, $imsi, $dialedDigits) {
		foreach ($scenario as $index => $name) {
			$nameAndUssage = explode("|", $name);
			$args = array(
				'imsi' => $imsi,
				'type' => $nameAndUssage[0],
				'duration' => ($nameAndUssage[1]) ? $nameAndUssage[1] : 4000, 
				'dialedDigits' => $dialedDigits
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
		$requestType = $this->conf->getConfigValue('test.requestType','');
		$URL = $this->apiUrl . trim("/" . $endpoint); // 'realtimeevent' / 'balances'
		$res = Billrun_Util::sendRequest($URL, $data, $requestType);
		$this->apiCalls[] = array(
			'uri' => $requestType . " " . $URL ,
			'request' => http_build_query($data),
			'response' => $res
		);
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
		$dialedDigits = $data['dialedDigits'];
		
		$request = '<?xml version = "1.0" encoding = "UTF-8"?>';
		switch ($type) {
			case 'start_call': $request .= '<request><api_name>start_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $this->reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><dialed_digits>'. $dialedDigits .'</dialed_digits><connected_number>'. $dialedDigits .'</connected_number><event_type>2</event_type><service_key>61</service_key><vlr>972500000701</vlr><location_mcc>425</location_mcc><location_mnc>03</location_mnc><location_area>7201</location_area><location_cell>53643</location_cell><time_date>2015/08/13 11:59:03</time_date><call_type>x</call_type></request>';
				break;
			case 'answer_call': $request .= '<request><api_name>answer_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $this->reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><dialed_digits>'. $dialedDigits .'</dialed_digits><connected_number>'. $dialedDigits .'</connected_number><time_date>2015/08/13 11:59:03.325</time_date><call_type>x</call_type></request>';
				break;
			case 'reservation_time': $request .= '<request><api_name>reservation_time</api_name><calling_number>972502145131</calling_number><call_reference>' . $this->reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><connected_number>'. $dialedDigits .'</connected_number><time_date>2015/08/13 11:59:03.423</time_date></request>';
				break;
			case 'release_call': $request = '<request><api_name>release_call</api_name><calling_number>972502145131</calling_number><call_reference>' . $this->reference . '</call_reference><call_id>rm7123123123</call_id><imsi>' . $imsi . '</imsi><connected_number>'. $dialedDigits .'</connected_number><time_date>2015/08/13 11:59:03.543</time_date><duration>' . $duration . '</duration><scp_release_cause>mmm</scp_release_cause><isup_release_cause>nnn</isup_release_cause><call_leg>x</call_leg></request>';
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
	 * Get data for AddBalance request
	 * @param String $type start_call / answer_call / reservation_time / release_call
	 * @param Array $data : imsi
	 * @return XML string
	 */
	protected function _getAddChargeData($data) {
		$sid = $data['sid'];
		$balance_type = $data['balance_type'];
		$amount = (-1) * $data['amount'];
		$request = array(
			'method' => 'update',
			'sid' => $sid,
			'query' => json_encode(["charging_plan_name" => $balance_type]),
			'upsert' => json_encode(["a" => 1])
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
				'amount' => -1 * $amount,
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
	
	protected function _addCharge($sid, $type) {
		$args = array('sid' => $sid, 'balance_type' => $type);
		$data = $this->_getAddChargeData($args);
		$this->sendRequest($data, 'balances');
	}

	protected function _getTestTestingData(){
		$output = array();
		$cursor = Billrun_Factory::db()->prepaidincludesCollection()->query()->cursor()->limit(100000)->sort(['name' => 1]);
		foreach ($cursor as $row) {
			$output['balance_types']['prepaidincludes'][] = $row['name'];
		}
		$searchQuery = array('type' => 'charging');
		$cursor = Billrun_Factory::db()->plansCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['name' => 1]);
		foreach ($cursor as $row) {
			$output['balance_types']['plans'][] = array('name' => $row['name'], 'desc' => $row['desc']);
		}
		
		$output['call_scenario'] = str_replace('\n', "\n", $this->conf->getConfigValue('test.call_scenario',""));
		$output['data_scenario'] = str_replace('\n', "\n", $this->conf->getConfigValue('test.data_scenario',""));
		$output['imsi'] = $this->conf->getConfigValue('test.imsi','');
		$output['sid'] = $this->conf->getConfigValue('test.sid','');
		$output['dialed_digits'] = $this->conf->getConfigValue('test.dialed_digits','');
		return $output;
	}

}
