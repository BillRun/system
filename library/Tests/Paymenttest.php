<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * 
 * @package  pay API
 * @since    0.5
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');
require_once(APPLICATION_PATH . '/library/Tests/testrail.php');

define('UNIT_TESTING', 'true');

class Tests_paymenttest extends UnitTestCase {

	use Tests_SetUp;

	protected $epsilon = 0.0001;
	protected $message = '';
	protected $cases;
	protected $fails;
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span><br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span><br>';
	protected $tests = array(
	);

	/**
	 * 
	 * @param type $label
	 */
	public function __construct($label = false) {
		//for PHP<7.3
		if (!function_exists('array_key_first')) {

			function array_key_first(array $arr) {
				foreach ($arr as $key => $unused) {
					return $key;
				}
				return NULL;
			}

		}
		parent::__construct("test Payment api");
		$this->serverName = $_SERVER['SERVER_NAME'];
		$request = new Yaf_Request_Http;
		$this->reportTR = $request->get('reportTR');
		$this->testRun = $request->get('testRun');
		$this->user = $request->get('user');
		$this->password = $request->get('password');
		$this->billsCol = Billrun_Factory::db()->billsCollection();
		$this->construct(basename(__FILE__, '.php'), ['bills', 'taxes']);
		$this->setColletions();
		$this->loadDbConfig();
		$this->readCases();
	}

	/**
	 * 
	 */
	public function loadDbConfig() {
		Billrun_Config::getInstance()->loadDbConfig();
	}

	/**
	 * init the tests to run
	 * @throws Exception
	 */
	public function readCases() {
		try {
			$request = new Yaf_Request_Http;
			$this->test_cases = $request->get('tests');
			$casesAsText = file_get_contents(APPLICATION_PATH . '/library/Tests/PaymenttestData/cases.json');
			$cases = json_decode($casesAsText, true);
			if ($this->test_cases) {
				$this->test_cases = explode(',', $this->test_cases);
				foreach ($cases as $case) {
					if (in_array($case['test_id'], $this->test_cases))
						$this->cases[] = $case;
					if (in_array($case['testRailId'], $this->test_cases))
						$this->cases[] = $case;
				}
			} else {
				$this->cases = $cases;
			}
			Billrun_Factory::log("Payment test run with test case : " . implode(',', $this->test_cases), Zend_Log::INFO);
		} catch (Exception $ex) {
			throw new Exception($ex);
		}
	}

	/**
	 * the function is runing all the test cases  
	 * print the test result
	 * and restore the original data 
	 */
	public function TestRunner() {

		foreach ($this->cases as $key => $row) {
			Billrun_Factory::log("***** start test number " . $row['test_id'], Zend_Log::INFO);
			$this->message .= "<span id={$row['test_id']}>test number : " . $row['test_id'] . '</span><br>';
			$this->message .= "<span>test description : " . $row['description'] . '</span><br>';
			Billrun_Factory::log("test description : " . print_r($row['description'], 1), Zend_Log::INFO);
// run fenctions before the test begin 
			if (isset($row['preTest']) && !empty($row['preTest'])) {
				$preRun = $row['preTest'];
				if (!is_array($preRun)) {
					$preRun = array($row['preTest']);
				}
				if (!is_null($preRun)) {
					foreach ($preRun as $func) {
						foreach ($func as $fun => $params) {
							Billrun_Factory::log("run preTest function : $fun  ", Zend_Log::INFO);
							$r = $this->$fun($row, $params);
						}
					}
				}
			}
// run case 
			if (!empty($row['api'])) {
				try {
					$this->respons = $this->bulidAPI($row);
//					echo '<pre>';
//					print_r($this->respons);
				} catch (Exception $ex) {
					throw new Exception($ex);
				}
			}
//run tests functios 
			if (isset($row['testFunctions'])) {
				$function = $row['testFunctions'];
				if (!is_array($function)) {
					$function = array($row['testFunctions']);
				}
				foreach ($function as $func) {
					foreach ($func as $fun => $params) {
						Billrun_Factory::log("test function : $fun ", Zend_Log::INFO);
						@$this->TestRailCases[$row['testRailId']]['comment'] .= "function $fun : ";
						$testFail = $this->assertTrue($this->$fun($row, $params));
						if (!$testFail) {
							Billrun_Factory::log("test function : $fun - fail ", Zend_Log::ERR);
							$this->TestRailCases[$row['testRailId']]['status'] = 5;
							$this->fails .= "  <a href='#{$row['test_id']}'>{$row['test_id']}</a> | ";
						} else {

							Billrun_Factory::log("test function : $fun - pass ", Zend_Log::INFO);
						}
					}
				}
			}

			$post = (isset($row['postTest']) && !empty($row['postTest'])) ? $row['postTest'] : null;

// run functions after the test run 
			if (!is_array($post) && isset($post)) {
				$post = array($row['postRun']);
			}
			if (!is_null($post)) {
				foreach ($post as $func) {
					foreach ($func as $fun => $params) {
						Billrun_Factory::log("run postTest function : $fun  ", Zend_Log::INFO);
						$this->$fun($row, $params);
					}
				}
			}

			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
			Billrun_Factory::log("***** finish test number " . $row['test_id'], Zend_Log::INFO);
		}
		if (!empty($this->fails)) {
			$this->message .='<b>list of fails</b><br>'. $this->fails;
		}
		print_r($this->message);
		if ($this->reportTR) {
			$this->ReportTestRail();
		}
    //  $this->restoreColletions();
		
	}

	/** 	
	 * report the result to test rail 
	 */
	public function ReportTestRail() {

		$line = '';
		$f = fopen(APPLICATION_PATH . '/.git/logs/HEAD', 'r');
		$cursor = -1;
		fseek($f, $cursor, SEEK_END);
		$char = fgetc($f);
//Trim trailing newline characters in the file
		while ($char === "\n" || $char === "\r") {
			fseek($f, $cursor--, SEEK_END);
			$char = fgetc($f);
		}
//Read until the next line of the file begins or the first newline char
		while ($char !== false && $char !== "\n" && $char !== "\r") {
			//Prepend the new character
			$line = $char . $line;
			fseek($f, $cursor--, SEEK_END);
			$char = fgetc($f);
		}
		$commit = explode(' ', $line);
		$commit_version = " <br><b>test in  commit {$commit[1]}</b> ";
		$branch_version = $commit[10];
		$client = new TestRailAPIClient('https://billrun.testrail.io/');
		$client->set_user($this->user);
		$client->set_password($this->password);
		foreach ($this->TestRailCases as $id => $test) {
			$status = isset($test['status']) == 5 ? 5 : 1;
			$comment = isset($test['status']) == 5 ? $test['comment'] : "";
			if($status ==5){
				$comment .= "<br> to run this case "."http://$_SERVER[HTTP_HOST]$_SERVER[REDIRECT_URL]?tests=$id <br>";
			}
			
			$results[] = [
				"case_id" => $id,
				"status_id" => $status,
				"comment" => $comment . $commit_version,
				"version" => $branch_version
			];
		}
		try {
			$result = $client->send_post(
				"add_results_for_cases/{$this->testRun}",
				[
					"results" => $results,
				]
			);
		} catch (Exception $ex) {
			echo $ex->getMessage();
		}
	}

	/**
	 * 
	 * @param type $params
	 * @return mongo query 
	 */
	public function buildQuery($params) {
		Billrun_Factory::log("run buildQuery function with params : " . print_r($params, 1), Zend_Log::INFO);
		$query = [];
		foreach ($params as $key => $value) {
			if(($key=='$in')) {
				$value = ['$in'=>$value['$in']];
			}
			$query[$key] = $value;
		}
		return $query;
	}

	public function isBillCreated($query) {
		
	}

	/**
	 * test  by compar expected and result Fields
	 * @param type $row
	 * @param type $params
	 * @return boolean
	 */
	public function FieldComparison($row, $params = null) {
		$pass = true;
	 	sleep(2);
		$bills = $this->getBills($this->buildQuery($params));
		print_r("***params");
		print_r( $params);
		Billrun_Factory::log("run FieldComparison function with params : " . print_r($params, 1), Zend_Log::INFO);
		Billrun_Factory::log(" FieldComparison function match bills : " . print_r($bills, 1), Zend_Log::INFO);
		$this->TestRailCases[$row['testRailId']]['comment'].="run FieldComparison function with params : " . print_r($params, 1);
		$this->TestRailCases[$row['testRailId']]['comment'].=" FieldComparison function match bills : " . print_r($bills, 1);
		// echo '<pre>';
		// 		print_r($row['expected']);
		// 		print_r($bills);
		if (count($row['expected']) != count($bills)) {
			$this->message .= "The number of invoices does not match the expected number of invoices" . $this->fail;
			$this->TestRailCases[$row['testRailId']]['comment'] .= "The number of invoices does not match the expected number of invoices<br>";
			Billrun_Factory::log("The number of invoices does not match the expected number of invoices", Zend_Log::ERR);
			$pass = false;
		}

		$sort = function ($a, $b) {


			$fields = [
				'aid',
				'total_paid',
				'left_to_pay',
				"payment_agreement.installment_index",
				'type',
				'cancelled',
				'correction',
				'dir',
				'amount',
				'left',
				'paid',
				'bills_merged',
			];

			foreach ($fields as $field) {
				if (strpos($field, '.')) {
					if (!empty(Billrun_Util::getIn($b, $field)) && Billrun_Util::getIn($a, $field) != Billrun_Util::getIn($b, $field)) {
						return Billrun_Util::getIn($a, $field) < Billrun_Util::getIn($b, $field);
					}
				} else {
					if (!empty($a[$field]) && !empty($b[$field]) && $a[$field] != $b[$field]) {
						return $a[$field] < $b[$field];
					}
				}
			}
			return 0;
		};
		usort($row['expected'], $sort);
		usort($bills, $sort);
		echo '<pre>';
		print_r($row['expected']);
		print_r($bills);

		$i = 0;
		foreach ($bills as $bill) {
			$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
			$this->message .= " ****  tests for  bill id  $identify **** <br>";
			Billrun_Factory::log("  ****  tests for  bill id $identify ****", Zend_Log::INFO);
			$this->TestRailCases[$row['testRailId']]['comment'] .= " **tests for  bill id  $identify**<br> ";
			foreach ($row['expected'][$i] as $k => $v) {
				if (!is_array($v)) {
					Billrun_Factory::log("  test field  $k Expected is $v", Zend_Log::INFO);
					$this->message .= '<b>test field</b> : ' . $k . ' </br>	Expected : ' . $v . '</br>';
					$this->message .= '	Result : </br>';
				}
				$nested = false;
				if (strpos($k, '.')) {
					$DataField = Billrun_Util::getIn($bill, $k);
					$nestedKey = explode('.', $k);
					$k = end($nestedKey);
					$nested = true;
				}
				//// check if  are their field that should not exist
				if (is_null($v)) {
					if (array_key_exists($k, $bill) && !is_null($bill[$k])) {
						$this->message .= " -- the key $k exists although it should not exist " . $this->fail;
						$this->TestRailCases[$row['testRailId']]['comment'] .= "  test field  $k Expected is $v<br> ";
						$this->TestRailCases[$row['testRailId']]['comment'] .= " -- the key $k exists although it should not exist<br> ";
						Billrun_Factory::log("The  key $k exists although it should not exist", Zend_Log::ERR);
						$pass = false;
					} else {
						$this->message .= "-- the key $k isn't exists  " . $this->pass;
					}
					continue;
				}
				$DataField = $nested ? $DataField : $bill[$k];

				if (is_array($v)) {
					// check dates 

					$first = array_key_first($v);
					$format = @$v['date']['format'];
					if ($first == 'date') {

						if (date('t') == date('d')) {
							$v = date($format, strtotime($v['date']['date'], strtotime('-1 day', time())));
						} else {
							$v = date($format, strtotime($v['date']['date']));
						}
						$Date_ = date($format, $DataField->sec);
						$this->message .= '<b>test field</b> : ' . $k . ' </br>	Expected : ' . $v . '</br>';
						Billrun_Factory::log("  test field  $k Expected is $v", Zend_Log::INFO);
						$this->message .= '	Result : </br>';
						if ($v != $Date_) {
							Billrun_Factory::log("but the actual result  is  :  $Date_ ", Zend_Log::ERR);
							$this->message .= '	-- but the actual result  is : ' . $Date_ . $this->fail;
							$this->TestRailCases[$row['testRailId']]['comment'] .= "  test field **$k** Expected is $v<br> ";
							$this->TestRailCases[$row['testRailId']]['comment'] .= '	-- but the actual result  is : ' . $Date_ . '<br>';
							$pass = false;
						}
					}
					continue;
				}
				if (!$nested) {
					if (empty(array_key_exists($k, $bill))) {
						Billrun_Factory::log("The result key isnt exists", Zend_Log::ERR);
						$this->message .= ' 	-- the result key isnt exists' . $this->fail;
						$this->TestRailCases[$row['testRailId']]['comment'] .= "  test field  **$k**  Expected is $v<br> ";
						$this->TestRailCases[$row['testRailId']]['comment'] .= ' 	-- the result key isnt exists<br>';
						$pass = false;
					}
				}

				if (empty($DataField) && $DataField != 0) {
					Billrun_Factory::log("The  result is empty", Zend_Log::ERR);
					$this->message .= '-- the result is empty' . $this->fail;
					$this->TestRailCases[$row['testRailId']]['comment'] .= "  test field  **$k** Expected is $v<br> ";
					$this->TestRailCases[$row['testRailId']]['comment'] .= '-- the result is empty<br>';
	
				$pass = false;
				}
				if (!is_numeric($DataField)) {
					if ($DataField != $v) {
						Billrun_Factory::log("  actual result  is : . $DataField .", Zend_Log::ERR);
						$this->message .= '	--   actual result  is : ' . $DataField . $this->fail;
						$this->TestRailCases[$row['testRailId']]['comment'] .= "  test field   **$k** Expected is $v<br> ";
						$this->TestRailCases[$row['testRailId']]['comment'] .= '	--   actual result  is : ' . $DataField . '<br>';
						$pass = false;
					}
					if ($DataField == $v) {
						$this->message .= '	-- the result is equel to expected : ' . $DataField . $this->pass;
					}
				} else {
					if (!Billrun_Util::isEqual($DataField, $v, $this->epsilon)) {
						Billrun_Factory::log("Actual result  : . $DataField .", Zend_Log::ERR);
						$this->message .=  ' ----- ' . $DataField . $this->fail;
						$this->TestRailCases[$row['testRailId']]['comment'] .= "  test field  **$k** Expected is $v<br> ";
						$this->TestRailCases[$row['testRailId']]['comment'] .= '	--  actual result  is : ' . $DataField . '<br>';
						$pass = false;
					}
					if (Billrun_Util::isEqual($DataField, $v, $this->epsilon)) {
						$this->message .= '	-- the result is equel to expected : ' . $DataField . $this->pass;
					}
				}
			}
			$i++;
		}
		Billrun_Factory::log("finish test functioon FieldComparison ", Zend_Log::INFO);
		return $pass;
	}

	/**
	 * 
	 * @param type $query
	 * @return type
	 */
	public function getBills($query) {
		$allBills = [];
		
		$BillsCollection = Billrun_Factory::db()->billsCollection();
		$bills = $BillsCollection->query($query)->cursor()->setReadPreference('RP_PRIMARY')->timeout(10800000);
		sleep(2);
		foreach ($bills as $bill) {
			$allBills[] = $bill->getRawData();
		}
		return $allBills;
	}

	/**
	 * test the link between the bils
	 * @param type $row
	 * @param type $params
	 * @return boolean
	 */
	public function checkLink($row, $params = null) {
		Billrun_Factory::log("test function checkLink with params " . print_r($params, 1), Zend_Log::INFO);
		$this->TestRailCases[$row['testRailId']]['comment'] .= "test function checkLink with params " .print_r($params, 1);
		$pass = true;
		$bills = $this->getBills($this->buildQuery($params));
		$pays = $this->getPays($bills);
		$paidBy = $this->getPaidBy($bills);

		foreach ($paidBy as $id => $paid) {
			if (isset($pays[array_key_first($paid)])) {
				if (Billrun_Util::isEqual($paid[array_key_first($paid)]['amount'], $pays[array_key_first($paid)][$id]['amount'], $this->epsilon)) {
					Billrun_Factory::log("the  amount  in pays and paid by for invoice id $id  match : pays amount {$pays[array_key_first($paid)][$id]['amount']} paid_by amount is {$paid[array_key_first($paid)]['amount']}", Zend_Log::INFO);
					$this->message .= "-- the  amount  in pays and paid by for invoice id $id  match :pays amount {$pays[array_key_first($paid)][$id]['amount']} paid_by amount is {$paid[array_key_first($paid)]['amount']}" . $this->pass;
				} else {
					Billrun_Factory::log("the  amount  in pays and paid by for invoice id $id do not match :pays amount {$pays[array_key_first($paid)][$id]['amount']} paid_by amount is {$paid[array_key_first($paid)]['amount']}", Zend_Log::ERR);
					$this->message .= "-- the  amount  in pays and paid by for invoice id $id do not match :pays amount {$pays[array_key_first($paid)][$id]['amount']} paid_by amount is {$paid[array_key_first($paid)]['amount']}" . $this->fail;
					$this->TestRailCases[$row['testRailId']]['comment'] .= "-- the  amount  in pays and paid by for invoice id $id do not match :pays amount {$pays[array_key_first($paid)][$id]['amount']} paid_by amount is {$paid[array_key_first($paid)]['amount']}<br>";
					$pass = false;
				}
			} else {
				Billrun_Factory::log("the pays for invoice id $id isn't exists", Zend_Log::ERR);
				$this->message .= "-- the pays for invoice id $id isn't exists" . $this->fail;
				$this->TestRailCases[$row['testRailId']]['comment'] .= "-- the pays for invoice id $id isn't exists<br>";
				$pass = false;
			}
		}

		foreach ($bills as $bill) {
			if (isset($bill['due']) && $bill['due'] > 0) {
				if (Billrun_Util::isEqual($bill['due'], $bill['total_paid'], $this->epsilon)) {
					if ($bill['paid'] == 1) {
						$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
						Billrun_Factory::log("the paid status for  invoice id $identify is corect ", Zend_Log::INFO);
						$this->message .= "--the paid status for  invoice id $identify is corect,  " . $this->pass;
					} else {
						$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
						Billrun_Factory::log("the paid status for  invoice id $identify isn't corect, exept to be true but it's false  ", Zend_Log::ERR);
						$this->message .= "--the paid status for  invoice id $identify isn't corect, exept to be true but it's false  " . $this->fail;
						$this->TestRailCases[$row['testRailId']]['comment'] .= "--the paid status for  invoice id $identify isn't corect, exept to be true but it's false <br> ";
						$pass = false;
					}
				}
				if ($bill['due'] > $bill['total_paid']) {
					if ($bill['paid'] == 1) {

						$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
						Billrun_Factory::log("the paid status for  invoice id $identify isn't corect, exept to be false but it's true  ", Zend_Log::ERR);
						$this->message .= "--the paid status for  invoice id $identify isn't corect, exept to be false but it's true   " . $this->fail;
						$this->TestRailCases[$row['testRailId']]['comment'] .= "--the paid status for  invoice id $identify isn't corect, exept to be false but it's true   <br>";
						$pass = false;
					} else {
						$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
						Billrun_Factory::log("the paid status for  invoice id $identify is corect ", Zend_Log::INFO);
						$this->message .= "--the paid status for  invoice id $identify is corect,  " . $this->pass;
					}

					if (!Billrun_Util::isEqual(($bill['total_paid'] + $bill['left_to_pay']), $bill['due'], $this->epsilon)) {
						$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
						Billrun_Factory::log("the left_to_pay  for  invoice id $identify isn't corect, ", Zend_Log::ERR);
						$this->message .= "--the left_to_pay  for  invoice id $identify isn't corect,   " . $this->fail;
						$this->TestRailCases[$row['testRailId']]['comment'] .= "--the left_to_pay  for  invoice id $identify isn't corect,<br>   ";
						$pass = false;
					} else {
						$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
						Billrun_Factory::log("the left_to_pay  for  invoice id $identify is corect ", Zend_Log::INFO);
						$this->message .= "--the left_to_pay  for  invoice id $identify is corect,  " . $this->pass;
					}
				}
			}
			if (isset($bill['due']) && $bill['due'] < 0) {
				$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
				if (!Billrun_Util::isEqual(($bill['amount'] - $bill['left']), $pays[$identify]['total'], $this->epsilon)) {
					Billrun_Factory::log("the total pays is big than bill due  for  invoice id $identify  ", Zend_Log::ERR);
					$this->message .= "--  the total pays is big than bill due  for  invoice id $identify " . $this->fail;
					$this->TestRailCases[$row['testRailId']]['comment'] .= "--  the total pays is big than bill due  for  invoice id $identify <br>";
					$pass = false;
				}
				if (abs($pays[$identify]['total']) == abs($bill['due']) && $bill['left'] != 0) {
					Billrun_Factory::log("the total pays is big than bill due  for  invoice id $identify  ", Zend_Log::ERR);
					$this->message .= "-- the total pays is big than bill due  for  invoice id $identify " . $this->fail;
					$this->TestRailCases[$row['testRailId']]['comment'] .= "-- the total pays is big than bill due  for  invoice id $identify <br>";
					$pass = false;
				}
			}
		}
		return $pass;
		;
	}

	/**
	 * 
	 * @param type $billls
	 * @return type
	 */
	public function getPays($bills) {
		$pays = [];
		foreach ($bills as $bill) {
			if (isset($bill['pays'])) {
				$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];
				foreach ($bill['pays'] as $pay) {
					$pays[$identify][$pay['id']] = $pay;
					@$pays[$identify]['total'] += $pay['amount'];
				}
			}
		}
		Billrun_Factory::log("getPays function build pays  " . print_r($pays, 1), Zend_Log::INFO);
		return $pays;
	}

	/**
	 * 
	 * @param type $bills
	 */
	public function getPaidBy($bills) {
		$PaidBy = [];
		foreach ($bills as $bill) {
			if (isset($bill['paid_by'])) {
				$identify = isset($bill['invoice_id']) ? $bill['invoice_id'] : $bill['txid'];

				foreach ($bill['paid_by'] as $pay) {
					$PaidBy[$identify][$pay['id']] = $pay;
					@$PaidBy[$identify] ['total'] += $pay['amount'];
				}
			}
		}
		Billrun_Factory::log("getPaidBy function build getPaidBy  " . print_r($PaidBy, 1), Zend_Log::INFO);
		return $PaidBy;
	}

	/**
	 * 
	 * @param type $row
	 * @return type
	 */
	public function bulidAPI($row, $params = null) {
		if (!empty($params)) {
			$api = $params['api'];
		} else {
			$api = $row['api'];
		}
		$baseApi =($api != 'chargeAccount') ? 'api' :'billrun';
		$url = "http://$this->serverName/$baseApi/$api";
		echo '<pre> URL:';
		print_r($url);
		$paramsToSend = !empty($params) ? $params : $row['params'];
		foreach ($paramsToSend as $key => $val) {

			//on case of  invoice_unixtime parmter is pass in onetime 
			if ($key == 'invoice_unixtime') {
				if ($val == 'future') {
					$val = time() + 2592000;
				} else if($val == 'past') {
					$val = time() - 2592000;
				}
			}

			//in case that we need to pass specific invoice id , we get the invoice id by passing a query 
			if (isset($val['invoice_id'])) {
				$bills = $this->getBills($this->buildQuery($val['invoice_id']));
				$request[$key] = json_encode(['invoice_id' => $bills[0]['invoice_id']]);
				continue;
			}
			$adjustments = [];
			if ($key == 'adjustments') {
				foreach ($val as $i => $adjustment) {
					if (isset($adjustment['id']) && $adjustment['id'] != 'Unknown') {
						$bills = $this->getBills($this->buildQuery($adjustment['id']));
						$adjustments[$i]['id'] = $bills[0]['txid'];
					} else {
						if ($adjustment['id'] == 'Unknown') {
							$adjustments[$i]['id'] = "123456789";
						}
					}

					foreach ($adjustment as $k => $v) {
						if ($k == 'id')
							continue;
						$adjustments[$i][$k] = $v;
					}
				}
				$request['adjustments'] = json_encode($adjustments);
//				$request[$key] = json_encode(['id' => $bills[0]['txid']]);
				continue;
			}
			//in case that we need to pass replace  any  paramter  , we get it by passing a query 
			if (isset($val['query'])) {
				$bills = $this->getBills($this->buildQuery($val['query']));
				if (isset($val['type']) && $val['type'] == 'array') {
					$request[$key] = json_encode(explode(',', $bills[0][$key]));
				}
				if ($key == 'split_bill_id') {

					$request[$key] = (string) $bills[0]['payment_agreement']['id'];
				}
				continue;
			}

			//convert date with date + format
			if (isset($val['date'])) {
				if (date('t') == date('d')) {
					$request[$key] = date($val['date']['format'], strtotime($val['date']['date'], strtotime('-1 day', time())));
				} else {
					$request[$key] = date($val['date']['format'], strtotime($val['date']['date']));
				}
				continue;
			}

			//
			if (!is_array($val)) {
				$request[$key] = (string) $val;
			} else {
				if ($key == 'cdrs') {
					$request['cdrs'] = json_encode($val);
				}
				if ($key == 'installments') {
					foreach ($val as $i => $installment) {
						//date($format, strtotime('+1 day', strtotime('last day of this month', time())));
						if (date('t') == date('d')) {
							$installments[$i]['due_date'] = date($installment['due_date']['format'], strtotime($installment['due_date']['date'], strtotime('-1 day', time())));
						} else {
							$installments[$i]['due_date'] = date($installment['due_date']['format'], strtotime($installment['due_date']['date']));
						}

						$installments[$i]['amount'] = (string) $installment['amount'];
						if (isset($installment['uf'])) {

							foreach ($installment['uf'] as $uf => $value) {
								$installments[$i]['uf'][$uf] = $value;
							}
						}
					}

					$request['installments'] = json_encode($installments);
				} else {
					if (isset($val[0]['pays'])) {
						$Bill = $this->getBills($this->buildQuery(['aid' => 1, 'invoice_id' => ['$exists' => 1]]));
						$amount = $val[0]['pays']['inv']['amount'];
						unset($val[0]['pays']['inv']['amount']);
						$val[0]['pays']['inv'][$Bill[0]['invoice_id']] = $amount;
					}
					if ($key == 'cancellations') {

						foreach ($val as $k => &$v) {
							if (is_array($v['txid'])) {
								$query=[];
								$query['aid']=1;
								foreach ($v['txid'] as $field=>$fvualue){
									$query[$field]=$fvualue;
								}
								$KeyQueryField = array_key_first($v['txid']);
								$Bill = $this->getBills($this->buildQuery(/*['aid' => 1, $KeyQueryField => $v['txid'][$KeyQueryField],'left' => ['$exists' => 1]]*/$query));
								$v['txid'] = $Bill[0]['txid'];
							}
						}
						$request['cancellations'] = json_encode($val);
					}
					$request[$key] = json_encode($val);
				}
			}
		}
		$secret = Billrun_Utils_Security::getValidSharedKey();
		$signed = Billrun_Utils_Security::addSignature($request, $secret['key']);
	//	$request['XDEBUG_SESSION_START'] ="VSCODE";
		$request['_sig_'] = $signed['_sig_'];
		$request['_t_'] = $signed['_t_'];
		// echo '<pre>';
		// print_r($request);

		return $this->sendAPI($url, $request);
	}

	/**
	 * 
	 * @param type $url
	 * @param type $request
	 * @return type
	 */
	public function sendAPI($url, $request) {
		Billrun_Factory::log("send api API to $url with params l" . print_r($request, 1), Zend_Log::INFO);
		$api = explode('/', $url);
		if (in_array('onetimeinvoice', $api))
	     	sleep(3);
		$respons = json_decode(Billrun_Util::sendRequest($url, $request), true);
		Billrun_Factory::log("response is :" . print_r($respons, 1), Zend_Log::INFO);
		echo '<pre>';
		print_r($$url);
		print_r($request);
		//print_r($this->getBills(['aid' =>['$in'=>[72,722]]]));
		return $respons;
	}

	/**
	 * check if response message is correct
	 * @param type $row
	 * @param type $params
	 * @return boolean
	 */
	public function checkApiRespons($row, $params = null) {
		$pass = true;
		Billrun_Factory::log("test function checkApiRespons, with array  of pathes and valuses  with params " . print_r($params, 1), Zend_Log::INFO);
		$this->TestRailCases[$row['testRailId']]['comment'] .="test function checkApiRespons with params " . print_r($params, 1);
		Billrun_Factory::log("test API respons for {$row['api']} API", Zend_Log::INFO);
		$this->message .= "test API respons for {$row['api']} API </br>";
		foreach ($params as $path => $message) {
			$respons = Billrun_Util::getIn($this->respons, $path);
			if (is_null($message)) {
				if (is_null($respons)) {
					$this->message .= "-- Expected respons for path \"$path\" is null </br>";
					Billrun_Factory::log("respons  is null ", Zend_Log::INFO);
					$this->message .= "-- respons  is null " . $this->pass;
				} else {
					$pass = false;
					Billrun_Factory::log("respons will be null  but it $respons ", Zend_Log::ERR);
					$this->message .= "respons will be null  but it  $respons " . $this->fail;
					$this->TestRailCases[$row['testRailId']]['comment'] .= "Expected respons for \"$path\" to be null  but actual $respons <br> ";
				}
			} else {
				if (isset($respons)) {
					$this->message .= "-- Expected respons for path \"$path\" is \"$message\" </br>";
					if ($respons == $message) {
						Billrun_Factory::log("respons  is \"$message\" " . print_r($message, 1), Zend_Log::INFO);
						$this->message .= "-- respons is\"$message\" " . $this->pass;
					} else {
						$pass = false;
						Billrun_Factory::log("respons  for path \"$path\" will be \"$message\" but it $respons ", Zend_Log::ERR);
						$this->message .= "respons will be \"$message\" but it $respons " . $this->fail;
						$this->TestRailCases[$row['testRailId']]['comment'] .= "Expected respons  for  \"$path\" to be  \"$message\" but actual  $respons<br> ";
					}
				} else {
					$pass = false;
					Billrun_Factory::log("the path $path not exists in the respons" . Zend_Log::ERR);
					$this->message .= "the path $path not exists in the respons" . $this->fail;
					$this->TestRailCases[$row['testRailId']]['comment'] .= "the path $path not exists in the respons<br>";
				}
			}
		}
		return $pass;
	}

	public function checkMergeBillDueDate($row, $params = null) {
		$pass = true;
		Billrun_Factory::log("test function checkMergeBillDueDate with params " . print_r($params, 1), Zend_Log::INFO);
		$bill = $this->getBills($this->buildQuery($params));
		$mergedBill = $this->getBills($this->buildQuery(["bills_merged" => ['$exists' => true]]));
		if (!empty($bill) && !empty($mergedBill)) {
			if ($bill[0]['due_date']->sec != $mergedBill[0]['due_date']->sec) {
				$pass = false;
				Billrun_Factory::log("due date of merged insatllment which was not given a first_charge_date/first_due_date are wrong, expected to  :" . date('Y-m-d', $bill[0]['due_date']->sec) . 'result is :' . date('Y-m-d', $mergedBill[0]['due_date']->sec), Zend_Log::ERR);
				$this->message .= "due date of merged insatllment which was not given a first_charge_date/first_due_date are wrong, expected to  :" . date('Y-m-d', $bill[0]['due_date']->sec) . 'result is :' . date('Y-m-d', $mergedBill[0]['due_date']->sec) . $this->fail;
				$this->TestRailCases[$row['testRailId']]['comment'] .= "due date of merged insatllment which was not given a first_charge_date/first_due_date are wrong, expected to  :" . date('Y-m-d', $bill[0]['due_date']->sec) . 'result is :' . date('Y-m-d', $mergedBill[0]['due_date']->sec) . '<br>';
			} else {
				Billrun_Factory::log("due date of merged insatllment which was not given a first_charge_date/first_due_date is correct", Zend_Log::INFO);
				$this->message .= "due date of merged insatllment which was not given a first_charge_date/first_due_date is correct" . $this->pass;
			}
		} else {
			$pass = false;
			Billrun_Factory::log("one of the bills isnt created ", Zend_Log::ERR);
			$this->message .= "one of the bills isnt created " . $this->fail;
			$this->TestRailCases[$row['testRailId']]['comment'] .= "one of the bills isnt created <br>";
		}
		return $pass;
	}

	/**
	 * 
	 * @param type $row
	 * @param type $params
	 */
	public function cleanDB($row, $params = null) {
		if ($params) {
			foreach ($params as $key => $value) {
				@$paramToprint = "$key = $value";
			}
			Billrun_Factory::log("remove from bills collection by this query  : $paramToprint", Zend_Log::INFO);
			$this->billsCol->remove($this->buildQuery($params));
		}
	}

}
