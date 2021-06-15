<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * Billing calculator for finding rates  of lines
 *
 * @package  calculator
 * @since    0.5
 */
require_once( APPLICATION_PATH . '/library/Tests/discounttestData/discountData.php');
require_once(APPLICATION_PATH . '/library/Tests/discounttestData/discountTestCases.php');
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');
define('UNIT_TESTING', 'true');

class Tests_Discounttest extends UnitTestCase {

	use Tests_SetUp;

	public $Tests; // = new discountTestCases();
	public $discountData;
	public $message = '';
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span></br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span></br>';

	public function __construct($label = false) {
		parent::__construct("test Rate");
		$this->TestsC = new discountTestCases();
		$this->Tests = $this->TestsC->tests;
		$this->discountData = new discountData(); //$Discount
		$this->discounts = (array) $this->discountData->Discount;
		$this->conditions = (array) $this->discountData->conditions;
		//list of indexs to run a subset of tests
		//$this->subsetTests($this->Tests ,[66]);
		ini_set('xdebug.var_display_max_depth', 100);
		ini_set('xdebug.var_display_max_children', 256);
		ini_set('xdebug.var_display_max_data', 1024);
	}


	public function TestPerform() {
		$myfile = fopen("/home/yossi/Documents/discounttestAprice", "w") or die("Unable to open file!");
		foreach ($this->Tests as $key => $row) {
			$this->message .= "Test number : {$row['test_num']}<br>";
			$aid = $row['test']['subsAccount'][0]['aid'];
			$expectedEligibility = '<b>expected </b> </br>';
//			foreach ($row['test']['cdrs'] as &$cdr) {
//				$cdr['aprice'] = $cdr['final_charge'] / 100 * 85.47008547008548;
//			}
//			echo '<pre>';
//			$a = var_export($row, 1);
//			$pattern = '/(\d){1,2}+(\s)+(=>)/';
//
//			$txt .= preg_replace($pattern, '', $a);
			//	$pattern = '/(\d)+(\d)+(\s)+(=>)/';
			//$txt .= $this->revar($row);
//			
//			
			foreach ($row['expected'] as $Dname => $dates) {
				$expectedEligibility .= "<b>Eligibility for discount : <br>$Dname</b><br>";
				foreach ($dates['eligibility'] as $date) {
					$expectedEligibility .= ' from ' . date("Y-m-d H:i:s", strtotime($date['from']));
					$expectedEligibility .= ' to ' . date("Y-m-d H:i:s", strtotime($date['to'])) . '</br>';
				}
			}

			if (empty($row['expected']) && $row['test']['function']) {
				$expectedEligibility .= 'no eligibility for subscriber</br>';
			}

			$this->message .= $expectedEligibility;

			//convert dates of revisions  To MongoDates
			$this->convertToMongoDates($row);
			$discounts = $this->discountBuilder($row['test']['discounts']);
			if (isset($row['SubscribersDiscount'])) {
				$this->subscribersDiscount($row);
			}
			// run fenctions before the test begin 
			if (isset($row['preRun']) && !empty($row['preRun'])) {
				$preRun = $row['preRun'];
				if (!is_array($preRun)) {
					$preRun = array($row['preRun']);
				}
				foreach ($preRun as $pre) {
					$this->$pre($key, $row);
				}
			}
			// run discount manager
			if (array_key_exists('aid', $row['test']['subsAccount'][0])) {
				if(isset($row['test']['charge_test'])){
					Billrun_DiscountManager::setCharges($discounts, $row['test']['options']['stamp']);
				} else {
					Billrun_DiscountManager::setDiscounts($discounts, $row['test']['options']['stamp']);
				}
				$cycle = new Billrun_DataTypes_CycleTime($row['test']['options']['stamp']);
				$from = new MongoDate($cycle->start());
				$to = new MongoDate($cycle->end());
				$row['test']['subsAccount'][0]['from'] = $from;
				$row['test']['subsAccount'][0]['to'] = $to;
				$dm = new Billrun_DiscountManager($row['test']['subsAccount'], $row['test']['subsRevisions'], $cycle);
				$eligibility = $dm->getEligibleDiscounts();
				if (!empty($row['subjectExpected'])) {
					$this->addStamp($row['test']['cdrs']);
					$this->addTaxData($row['test']['cdrs']);
					$returndCdrs = $dm->generateCDRs($row['test']['cdrs']);
					$this->assertTrue($this->checkSubject($row['subjectExpected'], $returndCdrs));
				}
			}
			//run tests functios 
			$this->message .= "<b>Result : </b></br>";
			if (isset($row['test']['function'])) {
				$function = $row['test']['function'];
				if (!is_array($function)) {
					$function = array($row['test']['function']);
				}
				foreach ($function as $func) {
					$this->assertTrue($this->$func($eligibility, $row['expected']));
				}
			}
			$this->message .= '<p style="border-top: 1px dashed black;"></p>';
		}


//		fwrite($myfile, $txt);
//		fclose($myfile);
		print_r($this->message);
	}

	public function addTaxData(&$cdrs) {
		foreach ($cdrs as &$cdr) {
			if (empty($cdr['tax_data'])) {
				$cdr['tax_data'] = [
					"total_amount" => $cdr['aprice'],
					"total_tax" => 0.17,
					"taxes" => [
						[
							"tax" => 0.17,
							"amount" => $cdr['aprice'],
							"description" => "Vat",
							"pass_to_customer" => 1
						]
					]
				];
			}
		}
	}

	public function addStamp(&$cdrs) {
		foreach ($cdrs as &$cdr) {
			if (!isset($cdr['stamp'])) {
				$cdr['stamp'] = rand(10000, 100000000);
			}
		}
	}

	public function checkEligibility($eligibility, $expected) {
		$pass = true;
		if (empty($expected)) {
			if (empty($eligibility)) {
				$this->message .= "no eligibility for subscriber " . $this->pass;
			} else {
				$pass = false;
				$this->message .= "the subscriber isn't eligibel for discount but these discounts are return : ";
				foreach ($eligibility as $eli => $value) {
					$this->message .= $eli . '<br/>';
				}
				$this->message .= $this->fail;
			}
		}
		$diff = array_diff(array_keys($eligibility), array_keys($expected));
		if ($diff) {
			$pass = false;
			$this->message .= "the subscriber isn't eligibel for these returnd discounts  : ";
			foreach ($diff as $value) {
				$this->message .= $value . '<br/>';
			}
			$this->message .= $this->fail;
		}
		foreach ($expected as $discountName => $dates) {

			if (count($eligibility[$discountName]['eligibility']) == count($dates['eligibility'])) {
				$returnEligibal = $eligibility[$discountName]['eligibility'];
				for ($i = 0; $i <= count($dates['eligibility']) - 1; $i++) {
					if ($dates['eligibility'][$i]['from']->sec != $returnEligibal[$i]['from']) {
						$pass = false;
						$this->message .= "$discountName worng 'from' eligibility expected : " . date("Y-m-d H:i:s", $dates['eligibility'][$i]['from']->sec) .
							"result : " . date("Y-m-d H:i:s", $returnEligibal[$i]['from']) . $this->fail;
					} else {
						$this->message .= "$discountName </br> '<b>from</b>' " . date("Y-m-d H:i:s", $returnEligibal[$i]['from']) . $this->pass;
					}
					if ($dates['eligibility'][$i]['to']->sec != $returnEligibal[$i]['to']) {
						$pass = false;
						$this->message .= "$discountName worng 'to' eligibility expected : " . date("Y-m-d H:i:s", $dates['eligibility'][$i]['to']->sec) .
							"result : " . date("Y-m-d H:i:s", $returnEligibal[$i]['to']) . $this->fail;
					} else {
						$this->message .= "$discountName </br> '<b>to</b>' " . date("Y-m-d H:i:s", $returnEligibal[$i]['to']) . $this->pass;
					}
				}
			} else {
				$pass = false;
				$this->message .= "$discountName missing eligibility" . $this->fail;
			}
		}
		return $pass;
	}

	public function checkSubject($expected, $returndCdrs) {
		$pass = true;
		$epsilon = 0.001;
		$sort = function ($a, $b) {
			$fields = [
				'sid',
				'aprice',
				'key',
				'final_charge',
				'type'
			];

			foreach ($fields as $field) {
				if ($field == 'aprice') {
					if (isset($a['aprice'])) {
						if ($a['aprice'] != $b['aprice']) {
							return $a['aprice'] < $b['aprice'];
						}
					}
					if ($a['full_price'] != $b['full_price']) {
						return $a['full_price'] < $b['full_price'];
					}
				}
				if ($a[$field] != $b[$field]) {
					return $a[$field] < $b[$field];
				}
			}
			return 0;
		};

		usort($expected, $sort);
		usort($returndCdrs, $sort);

		if (empty($expected)) {
			return true;
		}
		if (count($expected) <> count($returndCdrs)) {
			$this->message .= "the number of cdrs isn't equel to expected number of cdrs" . $this->fail;
		}
		for ($i = 0; $i <= count($expected) - 1; $i++) {
			$this->message .= 'cdr for discount <b>' . $expected[$i]['key'] . '</b><br>';
			if (isset($expected[$i]['sid'])) {
				//$this->message .= " sid {$expected[$i]['sid']}";
				if ($expected[$i]['sid'] == $returndCdrs[$i]['sid']) {
					$this->message .= "the eligibale subscriber is: {$expected[$i]['sid']}" . $this->pass;
				} else {
					$pass = false;
					$this->message .= "the eligibale subscriber is: {$expected[$i]['sid']} NOT  {$returndCdrs[$i]['sid']}" . $this->fail;
				}
			}$this->message .= '</b>';
			if (isset($expected[$i]['full_price'])) {
				if (Billrun_Util::isEqual($expected[$i]['full_price'], $returndCdrs[$i]['aprice'], $epsilon)) {
					$this->message .= 'the aprice is ' . $expected[$i]['full_price'] . $this->pass;
				} else {
					$pass = false;
					$this->message .= 'the aprice worng!!! expected is ' . $expected[$i]['full_price'] . ' result is' . $returndCdrs[$i]['aprice'] . $this->fail;
				}
			}
			if (isset($expected[$i]['final_charge'])) {
				if (Billrun_Util::isEqual($expected[$i]['final_charge'], $returndCdrs[$i]['final_charge'], $epsilon)) {
					$this->message .= 'the final_charge is ' . $expected[$i]['final_charge'] . $this->pass;
				} else {
					$pass = false;
					$this->message .= 'the final_charge worng!!! expected is ' . $expected[$i]['final_charge'] . ' result is' . $returndCdrs[$i]['final_charge'] . $this->fail;
				}
			}
			if (isset($expected[$i]['type'])) {
				if ($expected[$i]['type'] == $returndCdrs[$i]['type']) {
					$this->message .= 'the type is ' . $expected[$i]['type'] . $this->pass;
				} else {
					$pass = false;
					$this->message .= 'the type is worng!!! expected is ' . $expected[$i]['type'] . ' result is' . $returndCdrs[$i]['type'] . $this->fail;
				}
			}
			if (isset($expected[$i]['discounts'])) {
				foreach ($expected[$i]['discounts'] as $name => $value) {
					if (array_key_exists($name, $returndCdrs[$i]['discounts'])) {
						$this->message .= 'the discount  is aboubt  ' . $expected[$i]['discounts'][$name] . $this->pass;
						if ($value == $returndCdrs[$i]['discounts'][$name]['value']) {
							$this->message .= 'the discount aprice is  ' . $value . 'the result is ' . $returndCdrs[$i]['discounts'][$name]['value'] . $this->pass;
						} else {
							$pass = false;
							$this->message .= 'the aprice worng!!! expected is ' . $value . ' result is' . $returndCdrs[$i]['type'] . $this->fail;
						}
					}
				}
			}
		}
		return $pass;
	}

	public function convertToMongoDates(&$row) {
		foreach ($row as $key => &$valus) {

			if (is_array($valus)) {
				$this->convertToMongoDates($valus);
			}
			$valusList = ['from', 'to', 'deactivation_date', 'plan_activation', 'service_activation', 'start', 'end'];
			foreach ($valusList as $field) {
				if (isset($valus[$field])) {
					$valus[$field] = new MongoDate(strtotime($valus[$field]));
				}
			}
		}
	}

	public function subscribersDiscount(&$row) {
		foreach ($row['test']['subsRevisions'] as $key => &$revisions) {
			foreach ($revisions as &$revision) {
				foreach ($row['SubscribersDiscount'] as $sid => $sd) {
					if ($revision['sid'] == $sid) {
						$revision['discounts'] = $this->discountBuilder($sd['discounts']);
					}
				}
			}
		}
	}

	public function discountBuilder($discounts) {
		$discountsToPass = [];
		foreach ($discounts as $discount) {
			$discountsToPass[$discount['name']] = $this->discounts['general'];
			$discountsToPass[$discount['name']]['key'] = $discount['name'];
			if (!array_key_exists('root', $discount)) {
				$discount['root'] = [];
			}
			$discountsToPass[$discount['name']] = array_merge($discountsToPass[$discount['name']], $discount['root']);
			if (array_key_exists('min_subscribers', $discount['params_override'])) {
				$values = $discount['params_override']['min_subscribers'];
				$discountsToPass[$discount['name']]['params']['min_subscribers'] = $this->getParam('min_subscribers', $values);
			}
			if (array_key_exists('max_subscribers', $discount['params_override'])) {
				$values = $discount['params_override']['max_subscribers'];
				$discountsToPass[$discount['name']]['params']['max_subscribers'] = $this->getParam('max_subscribers', $values);
			}
			if (array_key_exists('cycles', $discount['params_override'])) {
				$values = $discount['params_override']['cycles'];
				$discountsToPass[$discount['name']]['params']['cycles'] = $this->getParam('cycles', $values);
			}
			foreach ($discount['params_override']['condition'] as $params) {
				$conditions = $this->conditions;
				foreach ($params as $param) {
					$type = $param['type'] ? $param['type'] : null;
					$values = $param['values'] ? $param['values'] : null;
					$field = $param['field'] ? $param['field'] : null;
					$op = isset($param['op']) ? $param['op'] : 'eq';
					if ($type == 'service') {
						$conditions['subscriber'][0]['service']['any'][]['fields'][] = $this->getParam($type, $values, $field, $op);
						continue;
					}
					if ($type == 'subscriber') {
						$conditions[$type][0]['fields'][] = $this->getParam($type, $values, $field, $op);
					}
					if ($type == 'account') {
						$conditions[$type]['fields'][] = $this->getParam($type, $values, $field, $op);
					}
				}
				$discountsToPass[$discount['name']]['params']['conditions'][] = $conditions;
			}
		}
		return $discountsToPass;
	}

	public function getParam($type, $values = null, $field = null, $op = '$eq') {
		if ($field == 'plan_activation') {
			$values = new MongoDate(strtotime($values));
		}
		switch ($type) {
			case 'subscriber':
				return [
					'field' => $field,
					'op' => $op,
					'value' => $values,
				];
				break;
			case 'account':
				return
					[
						'field' => $field,
						'op' => $op,
						'value' => $values
				];
				break;
			case 'service':
				return

					[
						'field' => $field,
						'op' => $op,
						'value' => $values,
				];

				break;
			case 'min_subscribers':
			case 'max_subscribers':
			case 'cycles':
				return $values;
				break;
		}
	}

}
