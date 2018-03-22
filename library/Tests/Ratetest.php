<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * Billing calculator for  pricing  billing lines with customer price.
 *
 * @package  calculator
 * @since    0.5
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Ratetest extends UnitTestCase {

	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $calculator;
	protected $config;
	protected $servicesToUse = ["SERVICE1", "SERVICE2"];
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span>';
	protected $rows = [
		//Test num 1 a1 rate by rate filed and rate key
		array('row' => array('stamp' => 'a1', 'aid' => 8880, 'sid' => 800, 'type' => 'Preprice_Dynamic', 'plan' => 'NEW-PLAN-A2', 'rate' => 'CALL', 'usaget' => 'call', 'usagev' => 10,),
			'expected' => array('CALL' => 'retail')),
		//Test num 2 b1 test multi tariff category
		array('row' => array('stamp' => 'b1', 'aid' => 27, 'sid' => 30, 'type' => 'Wholesale', 'plan' => 'WITH_NOTHING', 'RetailRate' => 'CALL', 'WholesaleRate' => 'CALL_wholesale', 'usaget' => 'call', 'usagev' => 60, 'urt' => '2017-08-14 11:00:00+03:00'),
			'expected' => array('CALL_wholesale' => 'wholesale', 'CALL' => 'retail')),
		//Test num 3 c1 test computed (the rate filed need to be equel to test filed)
		array('row' => array('stamp' => 'c1', 'aid' => 27, 'sid' => 30, 'type' => 'computed_regex', 'plan' => 'WITH_NOTHING', 'test' => 'CALL', 'rate' => 'CALL', 'usaget' => 'call', 'usagev' => 60, 'urt' => '2017-08-14 11:00:00+03:00'),
			'expected' => array('CALL' => 'retail')),
		//Test num 4 d1 test prefix
		array('row' => array('stamp' => 'd1', 'aid' => 27, 'sid' => 30, 'type' => 'prefix', 'plan' => 'WITH_NOTHING', 'prefix' => '770', 'usaget' => 'call', 'usagev' => 60, 'urt' => '2017-08-14 11:00:00+03:00'),
			'expected' => array('CALL' => 'retail')),
		//Test num 5 d2 test tow fildes with longest prefix 
		array('row' => array('stamp' => 'd2', 'aid' => 27, 'sid' => 31, 'type' => 'longest_prefix', 'plan' => 'WITH_NOTHING', 'phone' => '972533406999', 'code' => '1234', 'usaget' => 'call', 'usagev' => 60, 'urt' => '2017-08-14 11:00:00+03:00'),
			'expected' => array('CALL_A' => 'retail')),
		//Test num 6 d3 test Long prefix exists but it's in an expired revision: Take the shorter prefix from an active revision
		array('row' => array('stamp' => 'd3', 'aid' => 27, 'sid' => 31, 'type' => 'old_revision', 'plan' => 'WITH_NOTHING', 'code' => '033060985', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('SMS' => 'retail')),
		//Test num 7 d4 test No product found although an expired matching revision exists
		array('row' => array('stamp' => 'd4', 'aid' => 27, 'sid' => 31, 'type' => 'old_revision', 'plan' => 'WITH_NOTHING', 'code' => '456789', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 8 e1  Must met
		array('row' => array('stamp' => 'e1', 'aid' => 27, 'sid' => 31, 'type' => 'computed', 'plan' => 'WITH_NOTHING', 'called'=>'123456','calling'=>'123456', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('SELF_SMS' => 'retail')),
		//Test num 9 e2 false "Must met" fails the whole priority
		array('row' => array('stamp' => 'e2', 'aid' => 27, 'sid' => 31, 'type' => 'computed', 'plan' => 'WITH_NOTHING', 'called'=>'123456','calling'=>'789', 'usaget' => 'call', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
	];

	public function __construct($label = false) {
		parent::__construct("test Rate");
	}

	public function testUpdateRow() {
		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'Rate_Usage', 'autoload' => false));
		$init = new Tests_UpdateRowSetUp();
		$init->setColletions();

		foreach ($this->rows as $key => $row) {
			Billrun_Config::getInstance()->loadDbConfig();
			$fixrow = $this->fixRow($row['row'], $key);
			$this->linesCol->insert($fixrow);
			$updatedRow = $this->runT($fixrow['stamp']);
			$result = $this->compareExpected($key, $updatedRow, $row);
			$this->assertTrue($result[0]);
			print ($result[1]);
			print('<p style="border-top: 1px dashed black;"></p>');
		}
		$init->restoreColletions();
	}

	protected function runT($stamp) {
		$entity = $this->linesCol->query(array('stamp' => $stamp))->cursor()->current();
		$ret = $this->calculator->updateRow($entity);
		$entityAfter = $entity->getRawData();
		return ($entityAfter);
	}

	protected function compareExpected($key, $returnRow, $row) {
		$retunrRates = !empty($returnRow['rates']) ? $returnRow['rates'] : '';
		$message = '<span style="font: 14px arial; color: rgb(0, 0, 80);"> ' . ($key + 1) . '(#' . $returnRow['stamp'] . ')</br> Input Processors : ' . $row['row']['type'] . ' </br><b> Expected: </b> ';
		$error = '<span style="font: 14px arial; color: red;"> ';
		foreach ($row['expected'] as $key => $value) {
			$message .= "</br>rate_key: $key => Tariff_Category  :  $value ";
		}
		$message .= '<b></br> Result:</b> </br> ';
		$passed = True;
		if (!empty($retunrRates)) {
			foreach ($row['expected'] as $rate => $tariff) {
				$message .= (array_keys($row['expected'])[0] != $rate) ? '</br>' : '';
				$checkRate = current(array_filter($retunrRates, function(array $cat) use ($tariff) {
						return $cat['tariff_category'] === $tariff;
					}));

				if (!empty($checkRate)) {
					if ($checkRate['tariff_category'] === $tariff && $checkRate['key'] === $rate) {
						$message .= "rate_key: {$checkRate['key']} => Tariff_Category  :  {$checkRate['tariff_category']}  $this->pass";
					} else {
						$message .= $error . "rate_key: {$checkRate['key']} => Tariff_Category  :  {$checkRate['tariff_category']} </span> $this->fail ";
						$passed = false;
					}
				} else {
					$passed = false;
					$message .= $error . "No found any product and category";
				}
			}
			$message .= ' </span></br>';
		} elseif ($row['expected']['result'] == 'Rate not found') {
			$message .= "rate_key: <u><i>not found</i></u> $this->pass";
		} else {
			$passed = false;
			$message .= $error . "No found any product and category  ";
		}
		return [$passed, $message];
	}

	protected function fixRow($row, $key) {

		if (!array_key_exists('urt', $row)) {
			$row['urt'] = new MongoDate(time() + $key);
		} else {
			$row['urt'] = new MongoDate(strtotime($row['urt']));
		}
		if (!isset($row['aid'])) {
			$row['aid'] = 1234;
		}
		if (!isset($row['sid'])) {
			$row['sid'] = 1234;
		}

		if (isset($row['services_data'])) {
			foreach ($row['services_data'] as $key => $service) {
				if (!is_array($service)) {
					$row['services_data'][$key] = array(
						'name' => $service,
						'service_id' => 0,
					);
				}
				if (isset($service['from'])) {
					$row['services_data'][$key]['from'] = new MongoDate(strtotime($service['from']));
				}
				if (isset($service['to'])) {
					$row['services_data'][$key]['to'] = new MongoDate(strtotime($service['to']));
				}
			}
		}

		$plan = $this->plansCol->query(array('name' => $row['plan']))->cursor()->current();
		$row['plan_ref'] = MongoDBRef::create('plans', (new MongoId((string) $plan['_id'])));
		return $row;
	}

}
