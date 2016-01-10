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
		$this->siteUrl = $this->protocol . $this->basehost . $this->getRequest()->getServer('HTTP_HOST') . $this->subdomain;
		$this->apiUrl = $this->siteUrl . '/api';
		$this->reference = rand(1000000000, 9999999999);
		//Load Test conf file
		$this->conf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/utest/conf.ini'));
	}

	/**
	 * Main test page
	 * 
	 * @return void
	 */
	public function indexAction() {
		$this->getView()->subdomain = $this->subdomain;
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
		$type = Billrun_Util::filter_var($this->getRequest()->get('type'), FILTER_SANITIZE_STRING);
		$sid = (int) Billrun_Util::filter_var($this->getRequest()->get('sid'), FILTER_VALIDATE_INT);
		$imsi = Billrun_Util::filter_var($this->getRequest()->get('imsi'), FILTER_SANITIZE_STRING);

		if (empty($sid)) {
			$sid = $this->getSid($imsi);
		}
		
		$result = array();

		//Create test by type
		switch ($type) {
			case 'data':
				$utest = new TestDataModel($this);
				$result = array('balance_before', 'balance_after', 'lines');
				break;
			case 'call':
				$utest = new TestCallModel($this);
				$result = array('balance_before', 'balance_after', 'lines');
				break;
			case 'addBalance':
				$utest = new AddBalanceModel($this);
				$result = array('balance_before', 'balance_after', 'lines');
				break;
			case 'addCharge':
				$utest = new AddChargeModel($this);
				$result = array('balance_before', 'balance_after', 'lines');
				break;
			case 'cretaeSubscriber':
				$utest = new CretaeSubscriberModel($this);
				$result = array('subscriber_after', 'subscriber_before');
				break;
			case 'updateSubscriber':
				$utest = new UpdateSubscriberModel($this);
				$result = array('subscriber_after', 'subscriber_before', 'balance_before', 'balance_after', 'lines');
				break;
		}
		
		if ($removeLines == 'remove') {
			//Remove all lines by SID
			$this->resetLines($sid);
		}

		if(in_array('balance_before', $result)){
			// Get balance before scenario
			$balance['before'] = $this->getBalance($sid);
		}
		
		if(in_array('subscriber_before', $result)){
			// Get balance before scenario
			$subscriber['before'] = $this->getSubscriber($sid);
		}
		
		//Run test by type
		$utest->doTest();

		if(in_array('subscriber_after', $result)){
			// Get balance before scenario
			$subscriber['after'] = $this->getSubscriber($sid);
		}
		
		if(in_array('lines', $result)){
			// Get all lines created during scenarion
			$lines = $this->getLines($sid, $type);
		}

		if(in_array('balance_after', $result)){
			// Get balance after scenario
			$balance['after'] = $this->getBalance($sid);
		}

		$this->getView()->sid = $sid;
		$this->getView()->subdomain = $this->subdomain;
		$this->getView()->lines = $lines;
		$this->getView()->balances = $balance;
		$this->getView()->subscribers = $subscriber;
		$this->getView()->apiCalls = $this->apiCalls;
		$this->getView()->testType = ucfirst(preg_replace('/(?!^)[A-Z]{2,}(?=[A-Z][a-z])|[A-Z][a-z]/', ' $0', $type));
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
		$res = Billrun_Util::sendRequest($URL, $data, $methodType);
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
	 * Find SID by IMSI 
	 * @param type $imsi
	 */
	protected function getSid($imsi) {
		$searchQuery = ['imsi' => $imsi];
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
				'charging_by_usaget' => $row["charging_by_usaget"],
				'charging_by' => $row["charging_by"]
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
	 * Find all lines by SID and unique reference
	 * @param type $sid
	 * @param type $charging - if TRUE, return only CHARGING lines
	 */
	protected function getLines($sid, $type) {
		$lines = array();
		$amount = 0;

		
		if ($type == 'addBalance') {
			$searchQuery = array(
				"sid" => $sid,
				"type" => 'charging'
			);
		} else if($type == 'updateSubscriber'){
			$searchQuery = array("sid" => $sid);
		} else {
			$searchQuery = array(
				"sid" => $sid,
				'$or' => array(
					array("session_id" => (int) $this->reference),
					array("call_reference" => (string) $this->reference)),
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
				'balance_before' => number_format($row['balance_before'], 3),
				'balance_after' => number_format($row['balance_after'], 3),
				'arate' => (string) $row['arate']['$id']
			);
		}
		$lines['total'] = $amount;
		$lines['ref'] = $charging ? "Charging" : $this->reference;
		return $lines;
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
		return $output;
	}

}

spl_autoload_register(function ($class) {
	$class = preg_replace('/' . preg_quote('Model', '/') . '$/', '', $class);
	$path = __DIR__ . '/../models/utest/' . $class . '.php';
	include $path;
});
