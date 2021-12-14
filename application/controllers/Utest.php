<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing u-test controller class
 *
 * @package  Controller
 * @since    4.0
 */
class UtestController extends Yaf_Controller_Abstract {

	use Billrun_Traits_Api_UserPermissions;

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
	 * Test
	 *
	 * @var utest object
	 */
	protected $utest = '';

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
		$this->allowed();
		Billrun_Factory::log('Start Unit testing');

		if (Billrun_Factory::config()->isProd()) {
			Billrun_Factory::log('Exit Unit testing. Unit testing not allowed on production');
			header("Location: " . $this->siteUrl . "/admin/login");
			die();
		}

		if (!AdminController::authorized('write', 'utest')) {
			//$this->getRequest()->getQuery();
			header("Location: " . $this->siteUrl . "/admin/login");
			die();
		}

		//Load Test conf file
		$this->conf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/utest/conf.ini'));
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/view/menu.ini');

		//init self params
		$protocol = $this->conf->getConfigValue('test.protocol', '');
		if (empty($protocol)) {
			$protocol = (empty($this->getRequest()->getServer('HTTPS'))) ? 'http' : 'https';
		}

		$this->protocol = $protocol . '://';
		$this->baseUrl = $this->getRequest()->getBaseUri();

		$hostname = $this->conf->getConfigValue('test.hostname', '');
		if (empty($hostname)) {
			$hostname = $this->getRequest()->getServer('HTTP_HOST') . $this->baseUrl;
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
		} else {
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
			if (!empty($imsi)) {
				$query = array('imsi' => $imsi);
			} elseif (!empty($msisdn)) {
				$query = array('msisdn' => Billrun_Util::msisdn($msisdn));
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
		$tetsClassName = $type . 'Model';
		$this->utest = new $tetsClassName($this);
		$result = $this->utest->getTestResults(); //Get parts for results


		if (in_array('balance_before', $result)) {
			// Get balance before scenario
			$balance['before'] = $this->getBalance($sid);
		}

		if (in_array('autorenew_before', $result)) {
			// Get balance after scenario
			$autorenew['before'] = $this->getAutorenew($sid);
		}

		if (in_array('subscriber_before', $result)) {
			// Get balance before scenario
			$subscriber['before'] = $this->getSubscriber($sid);
		}

		$this->testStartTime = gettimeofday();
		//Run test by type
		$this->utest->doTest();
		$this->testEndTime = gettimeofday();

		//Update SID if SID was changed in test
		$new_sid = (int) Billrun_Util::filter_var($this->getRequest()->get('new_sid'), FILTER_VALIDATE_INT);
		$sid_after_test = (!empty($new_sid)) ? $new_sid : $sid;

		if (in_array('subscriber_after', $result)) {
			// Get balance before scenario
			$subscriber['after'] = $this->getSubscriber($sid_after_test);
		}

		if (in_array('lines', $result)) {
			// Get all lines created during scenarion by sid
			$lines = $this->getLines($sid_after_test);
		}

		if (in_array('cards', $result)) {
			// Get all lines created during scenarion
			$cards = $this->getCards();
		}

		if (in_array('balance_after', $result)) {
			// Get balance after scenario
			$balance['after'] = $this->getBalance($sid_after_test);
		}
		if (in_array('autorenew_after', $result)) {
			// Get balance after scenario
			$autorenew['after'] = $this->getAutorenew($sid_after_test);
		}

		$this->getView()->test = $this->utest;
		$this->getView()->sid = $sid;
		$this->getView()->sid_after_test = $sid_after_test;
		$this->getView()->baseUrl = $this->baseUrl;
		$this->getView()->cards = isset($cards) ? $cards : null;
		$this->getView()->lines = isset($lines) ? $lines : null;
		$this->getView()->balances = $balance;
		$this->getView()->subscribers = isset($subscriber) ? $subscriber : null;
		$this->getView()->autorenew = isset($autorenew) ? $autorenew : null;
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
		return $res;
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
		$cursor = Billrun_Factory::db()->subscribersCollection()->query(array_merge($searchQuery, Billrun_Utils_Mongo::getDateBoundQuery()))->cursor()->limit(100000);
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
			if ($row['charging_by_usaget'] == 'total_cost' || $row['charging_by_usaget'] == 'cost') {
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
		$searchQuery = array_merge(array("sid" => (int) $sid), Billrun_Utils_Mongo::getDateBoundQuery());
		$cursor = Billrun_Factory::db()->subscribersCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['to' => 1]);
		foreach ($cursor as $row) {
			$rowData = $row->getRawData();
			ksort($rowData);
			$id = (string) $rowData['_id'];
			foreach ($rowData as $key => $value) {
				if (get_class($value) == 'MongoId') {
					$subscribers[$id][$key] = $id;
				} else if (get_class($value) == 'Mongodloid_Date') {
					$subscribers[$id][$key] = date('d/m/Y H:i:s', $value->sec);
				} else if (is_array($value)) {
					$subscribers[$id][$key] = implode(", ", $value);
				} else {
					$subscribers[$id][$key] = $value;
				}
			}
		}
		return $subscribers;
	}

	/**
	 * Find Auto renew by SID
	 * @param type $sid
	 */
	protected function getAutorenew($sid) {
		$output = array();
		$searchQuery = array("sid" => (int) $sid);
		$cursor = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['to' => 1]);
		foreach ($cursor as $row) {
			$rowData = $row->getRawData();
			ksort($rowData);
			$id = (string) $rowData['_id'];
			foreach ($rowData as $key => $value) {
				if (get_class($value) == 'MongoId') {
					$subscribers[$id][$key] = $id;
				} else if (get_class($value) == 'Mongodloid_Date') {
					$subscribers[$id][$key] = date('d/m/Y H:i:s', $value->sec);
				} else if (is_array($value)) {
					$output[$id][$key] = json_encode($value);
				} else {
					$output[$id][$key] = $value;
				}
			}
		}
		return $output;
	}

	/**
	 * Find all lines by SID during the test
	 * @param type $sid
	 */
	protected function getLines($sid) {
		$lines = array();
		$total_aprice = $total_usagev = 0;
		//Search lines by testID + sid (for test with configurable date)
		if ($this->utest->getTestName() == 'utest_Call') {
			$searchQuery = array(
				'sid' => $sid,
				'call_reference' => (string) $this->reference
			);
		} else if (in_array($this->utest->getTestName(), array('utest_Sms', 'utest_Service'))) {
			$searchQuery = array(
				'sid' => $sid,
				'association_number' => (string) $this->reference
			);
		} else { //Search lines by test time + sid
			$searchQuery = array(
				'sid' => $sid,
				'urt' => array(
					'$gte' => new Mongodloid_Date($this->testStartTime['sec'], $this->testStartTime['usec']),
					'$lte' => new Mongodloid_Date($this->testEndTime['sec'], $this->testEndTime['usec'])
				)
			);
		}

		$cursor = Billrun_Factory::db()->linesCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['urt' => 1]);
		foreach ($cursor as $row) {
			$rowData = $row->getRawData();
			$total_aprice += $rowData['aprice'];
			$total_usagev += $rowData['usagev'];
			$line = array(
				'time_date' => date('d/m/Y H:i:s', $rowData['urt']->sec),
				'record_type' => isset($rowData['record_type']) ? $rowData['record_type'] : null,
				'aprice' => $rowData['aprice'],
				'usaget' => $rowData['usaget'],
				'usagev' => $rowData['usagev'],
				'balance_before' => number_format($rowData['balance_before'], 3),
				'balance_after' => number_format($rowData['balance_after'], 3),
				'pp_includes_name' => $rowData['pp_includes_name'],
			);

			//Get Line rates
			$arate = Billrun_Factory::db()->ratesCollection()->getRef($rowData['arate']);
			if (!empty($arate)) {
				$line['arate'] = array(
					'id' => (string) $arate->get('_id'),
					'key' => $arate->get('key')
				);
			}

			//Get Archive lines
			$this->archiveDb = Billrun_Factory::db();
			$lines_coll = $this->archiveDb->archiveCollection();
			$archive_lines = $lines_coll->query(array('u_s' => $rowData['stamp']))->cursor()->sort(array('urt' => 1));
			foreach ($archive_lines as $archive_line) {
				$archive_line_data = $archive_line->getRawData();
				$arate = Billrun_Factory::db()->ratesCollection()->getRef($archive_line_data['arate']);
				if (!empty($arate)) {
					$archive_line_data['arate'] = array(
						'id' => (string) $arate->get('_id'),
						'key' => $arate->get('key')
					);
				}
				$line['archive_lines']['rows'][] = $archive_line_data;
			}
			if (isset($line['archive_lines'])) {
				$line['archive_lines']['ref'] = "Archive line details";
			}
			$lines['rows'][] = $line;
		}

		$lines['total_aprice'] = $total_aprice;
		$lines['total_usagev'] = $total_usagev;
		if (in_array($this->utest->getTestName(), array('utest_Call'))) {
			$lines['ref'] = 'Lines that was created during test run, test ID : ' . $this->reference;
		} else {
			$lines['ref'] = 'Lines that was created during test run, <strong>from ' . date('d/m/Y H:i:s', ($this->testStartTime['sec'])) . ":" . $this->testStartTime['usec'] . " to " . date('d/m/Y H:i:s', $this->testEndTime['sec']) . ":" . $this->testEndTime['usec'] . '</strong>, test ID : ' . $this->reference;
		}
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
				'$gte' => new Mongodloid_Date($this->testStartTime['sec'], $this->testStartTime['usec']),
				'$lte' => new Mongodloid_Date($this->testEndTime['sec'], $this->testEndTime['usec'])
			)
		);

		$cursor = Billrun_Factory::db()->cardsCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['urt' => 1]);
		foreach ($cursor as $row) {
			$rowData = $row->getRawData();
			$id = (string) $rowData['_id'];
			foreach ($rowData as $key => $value) {
				if (get_class($value) == 'MongoId') {
					$output['rows'][$id][$key] = $id;
				} else if (get_class($value) == 'Mongodloid_Date') {
					$output['rows'][$id][$key] = date('d/m/Y H:i:s', $value->sec);
				} else if (is_array($value)) {
					$output['rows'][$id][$key] = implode(", ", $value);
				} else {
					$output['rows'][$id][$key] = $value;
				}
			}
		}
		$output['label'] = 'Cards that was created during test run, <strong>from ' . date('d/m/Y H:i:s', ($this->testStartTime['sec'])) . ":" . $this->testStartTime['usec'] . " to " . date('d/m/Y H:i:s', $this->testEndTime['sec']) . ":" . $this->testEndTime['usec'] . '</strong>, Batch Number : ' . $this->reference;
		return $output;
	}

	/**
	 * Create data for main test form page
	 */
	protected function getTestFormData() {
		$output = array();
		$output['prepaidincludes'] = array();
		$cursor = Billrun_Factory::db()->prepaidincludesCollection()->query()->cursor()->limit(100000)->sort(['name' => 1]);
		foreach ($cursor as $row) {
			$output['prepaidincludes'][] = $row['name'];
		}
		$output['charging_plans'] = array();
		$searchQuery = array(
			'type' => 'charging',
			'$or' => array(
				array('recurring' => 0),
				array('recurring' => array('$exists' => 0)),
			),
		);
		$cursor = Billrun_Factory::db()->plansCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['service_provider' => 1, 'name' => 1]);
		foreach ($cursor as $row) {
			$output['charging_plans'][] = array('name' => $row['name'], 'desc' => $row['desc'], 'service_provider' => $row['service_provider']);
		}
		$output['autorenew_plans'] = array();
		$searchQuery = array(
			'type' => 'charging',
			'recurring' => 1,
			'include' => array('$exists' => 1),
		);
		$cursor = Billrun_Factory::db()->plansCollection()->query($searchQuery)->cursor()->limit(100000)->sort(['service_provider' => 1, 'name' => 1]);
		foreach ($cursor as $row) {
			$output['autorenew_plans'][] = array('name' => $row['name'], 'service_provider' => $row['service_provider'], 'exp' => date('d/m/Y', $row["to"]->sec));
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
		$output['connection_types'] = $this->conf->getConfigValue('test.connection_type', array());
		$output['languages'] = $this->conf->getConfigValue('test.language', array());
		$output['service_codes'] = $this->conf->getConfigValue('test.service_codes', array());
		$output['imsi'] = $this->conf->getConfigValue('test.imsi', '');
		$output['msisdn'] = $this->conf->getConfigValue('test.msisdn', '');
		$output['sid'] = $this->conf->getConfigValue('test.sid', '');
		$output['aid'] = $this->conf->getConfigValue('test.aid', '');
		$output['dialed_digits'] = $this->conf->getConfigValue('test.dialed_digits', '');
		$output['mcc'] = $this->conf->getConfigValue('test.mcc', '');
		$output['msc'] = $this->conf->getConfigValue('test.msc', '');
		$output['request_method'] = $this->conf->getConfigValue('test.requestType', 'GET');
		$output['np_codes'] = $this->conf->getConfigValue('test.npCodes', array());

		$cardsConf = Billrun_Config::getInstance(new Yaf_Config_Ini(APPLICATION_PATH . '/conf/cards/conf.ini'));
		$output['card_statuses'] = $cardsConf->getConfigValue('cards.status', array());

		return $output;
	}

	protected function getEnabledTests($test_collection) {
		if (!empty($test_collection)) {
			$tests = $this->conf->getConfigValue("test.enableTests." . $test_collection, array());
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

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
