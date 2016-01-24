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
	protected $baseUrl = '';
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
	 * save all request and responce
	 *
	 * @var string
	 */
	protected $testStartTime = null;
	protected $testEndTime = null;

	/**
	 * method to control and navigate the user to the right view
	 */
	public function init() {
		Billrun_Factory::log('Start Unit testing');
		
		if (Billrun_Factory::config()->isProd()) {
			Billrun_Factory::log('Exit Unit testing. Unit testing not allowed on production');
			die();
		}
		
		if(!AdminController::authorized('write', 'utest')){
			//$this->getRequest()->getQuery();
			header("Location: " . $this->siteUrl . "/admin/login");
			die();
		}
		
		//Load Test conf file
		$this->conf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/utest/conf.ini'));
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/view/menu.ini');
		
		//init self params
		$protocol = $this->conf->getConfigValue('test.protocol', '');
		if(empty($protocol)){
			$protocol = (empty($this->getRequest()->getServer('HTTPS'))) ? 'http' : 'https';
		}
		
		$this->protocol = $protocol.'://';
		$this->baseUrl = $this->getRequest()->getBaseUri();
		
		$hostname = $this->conf->getConfigValue('test.hostname', '');
		if(empty($hostname)){
			$hostname =  $this->getRequest()->getServer('HTTP_HOST') . $this->baseUrl;
		}
		
		$this->siteUrl = $this->protocol . $hostname;
		$this->apiUrl = $this->siteUrl . '/api';
		$this->reference = rand(1000000000, 9999999999);
	}

	/**
	 * Main test page
	 * 
	 * @return void
	 */
	public function indexAction() {
		$tests = array();
		$test_collection = $this->getRequest()->get('testcollection');
		$enabledTests = $this->getEnabledTests($test_collection);
		if (!empty($test_collection)) {
			foreach ($enabledTests as $key => $testModelName) {
				$tests[] = new $testModelName($this);
			}
		} 
		else {
			$enabled_test_collections = array();	
			foreach (array_keys($enabledTests) as $value) {
				$enabled_test_collections[] = array(
					'param' => $value,
					'label' => $this->cleanLabel($value)
				);
			}
			$this->getView()->enabled_test_collections = $enabled_test_collections;
			$test_collection = 'utest';
		}
		
		$this->getView()->tests = $tests;
		$this->getView()->baseUrl = $this->baseUrl;
		$this->getView()->test_collection = $test_collection;
		$this->getView()->test_collection_label = $this->cleanLabel($test_collection);

		$formParams = $this->getTestFormData();
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
		if (empty($_REQUEST)) {
			header("Location: " . $this->siteUrl . "/utest");
			die();
		}
		$removeLines = Billrun_Util::filter_var($this->getRequest()->get('removeLines'), FILTER_SANITIZE_STRING);
		$removeSubscriber = Billrun_Util::filter_var($this->getRequest()->get('removeSubscriber'), FILTER_SANITIZE_STRING);
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);
		$sid = (int) Billrun_Util::filter_var($this->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$imsi = Billrun_Util::filter_var($this->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);
		$msisdn = Billrun_Util::filter_var($this->getRequest()->get('msisdn'), FILTER_SANITIZE_STRING);
		$test_collection = $this->getRequest()->get('testcollection');

		if (empty($sid)) {
			if(!empty($imsi)){
				$query = array('imsi' => $imsi);
			} elseif(!empty($msisdn)) {
				$query = array('msisdn' => $msisdn);
			}
			if (!empty($query)) {
				$sid = $this->getSid($query);
			} else {
				$sid = null;
			}
		}
		
		if ($removeLines == 'remove') {
			//Remove all lines by SID
			$this->resetLines($sid);
		}
		if ($removeSubscriber == 'remove') {
			//Remove all Subscriber by SID
			$this->resetSubscribers($sid);
		}
		
		//Create test by type
		$tetsClassName = $type .'Model';
		$utest = new $tetsClassName($this);
		$result = $utest->getTestResults();//Get parts for results
		
		
		if(in_array('balance_before', $result)){
			// Get balance before scenario
			$balance['before'] = $this->getBalance($sid);
		}
		
		if(in_array('subscriber_before', $result)){
			// Get balance before scenario
			$subscriber['before'] = $this->getSubscriber($sid);
		}
		
		$this->testStartTime = gettimeofday();
		//Run test by type
		$utest->doTest();
		$this->testEndTime = gettimeofday();
		

        //Update SID if SID was changed in test
		$new_sid = (int)Billrun_Util::filter_var($this->getRequest()->get('new_sid'), FILTER_VALIDATE_INT);
		$sid_after_test = (!empty($new_sid)) ? $new_sid : $sid;

		if(in_array('subscriber_after', $result)){
			// Get balance before scenario
			$subscriber['after'] = $this->getSubscriber($sid_after_test);
		}
		
		if(in_array('lines', $result)){
			// Get all lines created during scenarion by sid
			$lines = $this->getLines($sid_after_test);
		}
		
		if(in_array('cards', $result)){
			// Get all lines created during scenarion
			$cards = $this->getCards();
		}

		if(in_array('balance_after', $result)){
			// Get balance after scenario
			$balance['after'] = $this->getBalance($sid_after_test);
		}

		$this->getView()->test = $utest;
		$this->getView()->sid = $sid;
		$this->getView()->sid_after_test = $sid_after_test;
		$this->getView()->baseUrl = $this->baseUrl;
		$this->getView()->cards = isset($cards) ? $cards : null;
		$this->getView()->lines = isset($lines) ? $lines : null;
		$this->getView()->balances = $balance;
		$this->getView()->subscribers = isset($subscriber) ? $subscriber : null;
		$this->getView()->apiCalls = $this->apiCalls;
		$this->getView()->test_collection = $test_collection;
		$this->getView()->test_collection_label = $this->cleanLabel($test_collection);
	}

	public function getReference() {
		return $this->reference;
	}

	/**
	 * Send request
	 * 
	 * @param Array $data key value array
	 * @param String $endpoint API endpoint
	 */
	public function sendRequest($data = array(), $endpoint = 'realtimeevent') {
		//$data['XDEBUG_SESSION_START'] = 'netbeans-xdebug';
		$methodType = $this->conf->getConfigValue('test.requestType', 'GET');
		$URL = $this->apiUrl . trim("/" . $endpoint); // 'realtimeevent' / 'balances'
		//Calc microtine for API
		$time_start = microtime(true);
		try {
			$res = Billrun_Util::sendRequest($URL, $data, $methodType);
		} catch (Exception $exc) {
			$res = "Send Request error (" . $exc->getCode() . ") " . $exc->getMessage();
		}
		$time_end = microtime(true);
		$duration = $time_end - $time_start;

		$this->apiCalls[] = array(
			'uri' => $URL,
			'method' => $methodType,
			'duration' => $duration * 1000,
			'request' => http_build_query($data),
			'response' => $res
		);
	}

	/**
	 * Delete all lines by SID 
	 * @param type $sid
	 */
	protected function resetLines($sid) {
		Billrun_Factory::db()->linesCollection()->remove(array('sid' => $sid));
	}
	/**
	 * Delete all Subscribers by SID 
	 * @param type $sid
	 */
	protected function resetSubscribers($sid) {
		Billrun_Factory::db()->subscribersCollection()->remove(array('sid' => $sid));
	}

	/**
	 * Find SID by IMSI 
	 * @param type $imsi
	 */
	protected function getSid($searchQuery) {
		$cursor = Billrun_Factory::db()->subscribersCollection()->query($searchQuery)->cursor()->limit(100000);
		foreach ($cursor as $row) {
			return $row['sid'];
		}
		return NULL;
	}

	/**
	 * Find all balances by SID
	 * @param type $sid
	 */
	protected function getBalance($sid) {
		$balances = array();
		$searchQuery = ["sid" => $sid];
		$cursor = Billrun_Factory::db()->balancesCollection()->query($searchQuery)->cursor()->limit(100000);
		foreach ($cursor as $row) {
			if ($row['charging_by_usaget'] == 'total_cost') {
				$amount = floatval($row['balance']['cost']);
			} else {
				$amount = $row['balance']['totals'][$row["charging_by_usaget"]][$row["charging_by"]];
			}
			$balances[(string) $row['_id']] = array(
				'amount' => -1 * $amount,
				'pp_includes_name' => $row["pp_includes_name"],
				'charging_by_usaget' => $row["charging_by_usaget"],
				'charging_by_usaget_unit' => $row["charging_by_usaget_unit"],
				'charging_by' => $row["charging_by"],
				'to' => date('d/m/Y H:i:s', $row["to"]->sec),
				'from' => date('d/m/Y H:i:s', $row["from"]->sec)
			);
		}
		return $balances;
	}

	/**
	 * Find Subscriber by SID
	 * @param type $sid
	 */
	protected function getSubscriber($sid) {
		$subscribers = array();
		$searchQuery = array("sid" => (int)$sid);
		$cursor = Billrun_Factory::db()->subscribersCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['to' => 1]);
		foreach ($cursor as $row) {
			$rowData = $row->getRawData();
			ksort($rowData);
			$id = (string)$rowData['_id'];
			foreach ($rowData as $key => $value) {
				if(get_class ($value) == 'MongoId'){
					$subscribers[$id][$key] = $id;
				}
				else if(get_class ($value) == 'MongoDate'){
					$subscribers[$id][$key] = date('d/m/Y H:i:s', $value->sec);
				}
				else if(is_array($value)){
					$subscribers[$id][$key] = implode(", ",$value);
				}
				else {
					$subscribers[$id][$key] = $value;
				}
			}	
		}
		return $subscribers;
	}

	/**
	 * Find all lines by SID during the test
	 * @param type $sid
	 */
	protected function getLines($sid) {
		$lines = array();
		$rates = array();
		$amount = 0;
		$searchQuery = array(
			'sid' => $sid,
			'urt' => array(
				'$gte' => new MongoDate($this->testStartTime['sec'], $this->testStartTime['usec']),
				'$lte' => new MongoDate($this->testEndTime['sec'], $this->testEndTime['usec'])
			)
		);

		$cursor = Billrun_Factory::db()->linesCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['urt' => 1]);
		foreach ($cursor as $row) {
			$amount += $row['aprice'];
			$rateId = (string)$row['arate']['$id'];
			$lines['rows'][] = array(
				'time_date' => date('d/m/Y H:i:s', $row['urt']->sec),
				'record_type' => $row['record_type'],
				'aprice' => $row['aprice'],
				'usaget' => $row['usaget'],
				'usagev' => $row['usagev'],
				'balance_before' => number_format($row['balance_before'], 3),
				'balance_after' => number_format($row['balance_after'], 3),
				'arate' => $rateId
			);
			if(!empty($rateId)){
				$rates[$rateId] = '';
			}
		}
		// get Rates referen
		if(!empty($rates)){
			$rates_mongo_ids = array_map( function($val){ return new MongoId($val);}, array_keys($rates));
			$searchQuery = array('_id' => array( '$in' => $rates_mongo_ids));
			$cursor = Billrun_Factory::db()->ratesCollection()->query($searchQuery)->cursor()->limit(100000);
			foreach ($cursor as $row) {
				$rowData = $row->getRawData();
				$id = (string)$rowData['_id'];
				$key = (string)$rowData['key'];
				$rates[$id] = array('key' => $key);
			}
		}
		$lines['rates'] = $rates;
		$lines['total'] = $amount;
		$lines['ref'] = 'Lines that was created during test run, <strong>from ' . date('d/m/Y H:i:s', ($this->testStartTime['sec'])) .":".$this->testStartTime['usec'] . " to " . date('d/m/Y H:i:s', $this->testEndTime['sec']) .":".$this->testEndTime['usec'] .'</strong>, test ID : ' .  $this->reference;
		return $lines;
	}
	
	/**
	 * Find all Cards for test
	 * @param type $sid
	 * @param type $charging - if TRUE, return only CHARGING lines
	 */
	protected function getCards() {
		$output = array();

		$searchQuery = array(
			'from' => array(
				'$gte' => new MongoDate($this->testStartTime['sec'], $this->testStartTime['usec']),
				'$lte' => new MongoDate($this->testEndTime['sec'], $this->testEndTime['usec'])
			)
		);

		$cursor = Billrun_Factory::db()->cardsCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['urt' => 1]);
		foreach ($cursor as $row) {
			$rowData = $row->getRawData();
			$id = (string)$rowData['_id'];
			foreach ($rowData as $key => $value) {
				if(get_class ($value) == 'MongoId'){
					$output['rows'][$id][$key] = $id;
				}
				else if(get_class ($value) == 'MongoDate'){
					$output['rows'][$id][$key] = date('d/m/Y H:i:s', $value->sec);
				}
				else if(is_array($value)){
					$output['rows'][$id][$key] = implode(", ",$value);
				}
				else {
					$output['rows'][$id][$key] = $value;
				}
			}	
		}
		$output['label'] = 'Cards that was created during test run, <strong>from ' . date('d/m/Y H:i:s', ($this->testStartTime['sec'])) .":".$this->testStartTime['usec'] . " to " . date('d/m/Y H:i:s', $this->testEndTime['sec']) .":".$this->testEndTime['usec'] .'</strong>, Batch Number : ' .  $this->reference;
		return $output;
	}
	
	/**
	 * Create data for main test form page
	 */
	protected function getTestFormData() {
		$output = array();
		$output['balance_types']['prepaidincludes'] = array();
		$cursor = Billrun_Factory::db()->prepaidincludesCollection()->query()->cursor()->limit(100000)->sort(['name' => 1]);
		foreach ($cursor as $row) {
			$output['balance_types']['prepaidincludes'][] = $row['name'];
		}
		$output['balance_types']['plans'] = array();
		$searchQuery = array('type' => 'charging');
		$cursor = Billrun_Factory::db()->plansCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['name' => 1]);
		foreach ($cursor as $row) {
			$output['balance_types']['plans'][] = array('name' => $row['name'], 'desc' => $row['desc'], 'service_provider' => $row['service_provider']);
		}
		$output['customer_plans'] = array();
		$searchQuery = array('type' => 'customer');
		$cursor = Billrun_Factory::db()->plansCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['name' => 1]);
		foreach ($cursor as $row) {
			$output['customer_plans'][] = $row['name'];
		}
		$output['service_providers'] = array();
		$cursor = Billrun_Factory::db()->serviceprovidersCollection()->query()->cursor()->limit(100000)->sort(['name' => 1]);
		foreach ($cursor as $row) {
			$output['service_providers'][] = $row['name'];
		}

		$output['call_scenario'] = str_replace('\n', "\n", $this->conf->getConfigValue('test.call_scenario', ""));
		$output['data_scenario'] = str_replace('\n', "\n", $this->conf->getConfigValue('test.data_scenario', ""));
		$output['charging_types'] = $this->conf->getConfigValue('test.charging_type', array());
		$output['languages'] = $this->conf->getConfigValue('test.language', array());
		$output['imsi'] = $this->conf->getConfigValue('test.imsi', '');
		$output['msisdn'] = $this->conf->getConfigValue('test.msisdn', '');
		$output['sid'] = $this->conf->getConfigValue('test.sid', '');
		$output['aid'] = $this->conf->getConfigValue('test.aid', '');
		$output['dialed_digits'] = $this->conf->getConfigValue('test.dialed_digits', '');
		$output['request_method'] = $this->conf->getConfigValue('test.requestType', 'GET');
		$output['np_codes'] = $this->conf->getConfigValue('test.npCodes', array());
		
		$cardsConf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/cards/conf.ini'));
		$output['card_statuses'] = $cardsConf->getConfigValue('cards.status', array());
		
		return $output;
	}
	
	protected function getEnabledTests($test_collection) {
		if(!empty($test_collection)){
			$tests = $this->conf->getConfigValue("test.enableTests.".$test_collection, array());
		} else {
			$tests = $this->conf->getConfigValue("test.enableTests", array());
		}
		return $tests;
	}
	
	protected function cleanLabel($string) {
		$label = '';
		if (substr($string, 0, strlen('utest')) == 'utest') {
			$label = substr($string, strlen('utest'));
		}
		$label = preg_replace('/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]/', ' $0', $label);
		$label = ucwords($label);
		return $label;
	}
}