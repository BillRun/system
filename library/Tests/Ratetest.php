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
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

define('UNIT_TESTING', 'true');

class Tests_Ratetest extends UnitTestCase {

	use Tests_SetUp;

	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $calculator;
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
		array('row' => array('stamp' => 'd3', 'aid' => 27, 'sid' => 31, 'type' => 'old_revision', 'plan' => 'WITH_NOTHING', 'code' => '033060985', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-04-14 11:00:00+03:00'),
			'expected' => array('SMS' => 'retail')),
		//Test num 7 d4 test No product found although an expired matching revision exists
		array('row' => array('stamp' => 'd4', 'aid' => 27, 'sid' => 31, 'type' => 'old_revision', 'plan' => 'WITH_NOTHING', 'code' => '456789', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 8 e1  Must met
		array('row' => array('stamp' => 'e1', 'aid' => 27, 'sid' => 31, 'type' => 'computed', 'plan' => 'WITH_NOTHING', 'called' => '123456', 'calling' => '123456', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('SELF_SMS' => 'retail')),
		//Test num 9 e2 false "Must met" fails the whole priority
		array('row' => array('stamp' => 'e2', 'aid' => 27, 'sid' => 31, 'type' => 'computed', 'plan' => 'WITH_NOTHING', 'called' => '123456', 'calling' => '789', 'usaget' => 'call', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 10 f1  3 rates with 2 prefixes fildes the sms_c have longest prefix_a but it's prefix_b is incorrectly ,prefix_a of sms_b biger then sms_a
		//(sms_a:prefix_a = 1 prefix_b = 56 ,sms_b:prefix_a = 12 prefix_b = 5 ,sms_c:prefix_a = 123 prefix_b = 8 )
		array('row' => array('stamp' => 'f1', 'aid' => 27, 'sid' => 31, 'type' => 'many_prefix', 'plan' => 'WITH_NOTHING', 'prefix_a' => '123456', 'prefix_b' => '56789', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('SMS_B' => 'retail')),
		//Test num 11 g1 condition: a equel to b ,Value when True: rate filed  with regx"123"
		array('row' => array('stamp' => 'g1', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'SMS', 'a' => 1, 'b' => 1, 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('SMS' => 'retail')),
		//Test num 12 g2 ***negative test*** condition: a equel to b ,Value when True: rate filed , actuali a is greater then b 
		array('row' => array('stamp' => 'g2', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'SMS', 'a' => 2, 'b' => 1, 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 13 g3 ***negative test*** condition: a equel to b ,Value when True: rate filed , actuali b is then a 
		array('row' => array('stamp' => 'g3', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'SMS', 'a' => 1, 'b' => 2, 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 14 h1 condition:a Is Less Than Or Equal b ,Value when True: rate filed ,a less
		array('row' => array('stamp' => 'h1', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'DATA', 'a' => 1, 'b' => 2, 'usaget' => 'data', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('DATA' => 'retail')),
		//Test num 15 h2 condition:a Is Less Than Or Equal b ,Value when True: rate filed , a equel to b 
		array('row' => array('stamp' => 'h2', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'DATA', 'a' => 1, 'b' => 1, 'usaget' => 'data', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('DATA' => 'retail')),
		//Test num 16 h3 ***negative test*** condition:a Is Less Than Or Equal b ,Value when True: rate filed ,a is greater then b
		array('row' => array('stamp' => 'h3', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'DATA', 'a' => 2, 'b' => 1, 'usaget' => 'data', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 17  i1 condition:a Is Greater Than b ,Value when True: rate filed 
		array('row' => array('stamp' => 'i1', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'C_USA', 'a' => 2, 'b' => 1, 'usaget' => 'call_usa', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('C_USA' => 'retail')),
		//Test num 18  i2 ***negative test*** condition:a Is Greater Than b ,Value when True: rate filed actuali b Is Greater Than a
		array('row' => array('stamp' => 'i2', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'C_USA', 'a' => 1, 'b' => 2, 'usaget' => 'call_usa', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 19  i3 ***negative test*** condition:a Is Greater Than b ,Value when True: rate filed actuali b Is equel to a
		array('row' => array('stamp' => 'i3', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'C_USA', 'a' => 1, 'b' => 1, 'usaget' => 'call_usa', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 20  l1  condition:a Is Greater Than Or Equal b ,Value when True: rate filed 
		array('row' => array('stamp' => 'l1', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'C_UK', 'a' => 2, 'b' => 1, 'usaget' => 'call_uk', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('C_UK' => 'retail')),
		//Test num 21  l2  condition:a Is Greater Than Or Equal b ,Value when True: rate filed 
		array('row' => array('stamp' => 'l2', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'C_UK', 'a' => 1, 'b' => 1, 'usaget' => 'call_uk', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('C_UK' => 'retail')),
		//Test num 22  l3  ***negative test*** condition:a Is Greater Than Or Equal b ,Value when True: rate filed 
		array('row' => array('stamp' => 'l3', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'C_UK', 'a' => 1, 'b' => 2, 'usaget' => 'call_uk', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 23  m1   condition:a Matches regular expression Second Field is emptey,Value when True: rate filed 
		array('row' => array('stamp' => 'm1', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'C_GAZA', 'a' => 1, 'b' => 2, 'usaget' => 'call_to_gaza', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('C_GAZA' => 'retail')),
		//Test num 24  m2   condition:a Matches regular expression Second Field has //,Value when True: rate filed 
		array('row' => array('stamp' => 'm2', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'C_LA', 'a' => 1, 'b' => 2, 'usaget' => 'call_to_la', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('C_LA' => 'retail')),
		//Test num 25  n1   Computation Type Regex ,Condition Field rate× Regex/123/,Value when True: rate filed 
		array('row' => array('stamp' => 'n1', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => '123MMS', 'a' => 1, 'b' => 2, 'usaget' => 'mms', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('MMS' => 'retail')),
		//Test num 26  n2   Computation Type Regex ,Condition Field rate× Regex/123/,Value when True: rate filed 
		array('row' => array('stamp' => 'n2', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', 'rate' => 'MMS', 'a' => 1, 'b' => 2, 'usaget' => 'mms', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('MMS' => 'retail')),
		//Test num 27 o1  file_name vs rate_key
		array('row' => array('stamp' => 'o1', 'aid' => 27, 'sid' => 31, 'type' => 'conditions', 'plan' => 'WITH_NOTHING', "file" => 'USA_DATA', 'rate' => 'MMS', 'a' => 1, 'b' => 2, 'usaget' => 'roming_data', 'usagev' => 20, 'urt' => '2018-05-14 11:00:00+03:00'),
			'expected' => array('USA_DATA' => 'retail')),
		//Test num 28 p1 Duplicate mapping 2 different Computed ,but both return phone number field and And compare it to longest prefix
		array('row' => array('stamp' => 'p1', 'aid' => 27, 'sid' => 31, 'type' => 'Duplicate_mapping', "uf" => array("sid" => "31", "phone" => "0511234567"), 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 20, 'urt' => '2018-01-14 11:00:00+03:00'),
			'expected' => array('I_CALL' => 'retail')),
		//Test num 29 p1 Computed: if field d.m Exsist then   rate field vs productKay
		array('row' => array('stamp' => 'q1', 'aid' => 27, 'sid' => 31, 'type' => 'r', "uf" => array("d" => array("m" => 12345678)), "rate" => "CALL", 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 20, 'urt' => '2018-01-14 11:00:00+03:00'),
			'expected' => array('CALL' => 'retail')),
		//Test num 30 p2 ***negative test*** Computed:if field d.m Exist  then rate field vs productKay
		array('row' => array('stamp' => 'q2', 'aid' => 27, 'sid' => 31, 'type' => 'r', "uf" => array(), "rate" => "CALL", 'plan' => 'WITH_NOTHING', 'usaget' => 'call', 'usagev' => 20, 'urt' => '2018-01-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
		//Test num 31 p3 Computed:if field d.m Does not Exist  then rate field vs productKay
		array('row' => array('stamp' => 'q3', 'aid' => 27, 'sid' => 31, 'type' => 'r', "uf" => array(), "rate" => "SMS", 'plan' => 'WITH_NOTHING', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('SMS' => 'retail')),
		//Test num 32 p4 ***negative test*** Computed:if field d.m Does not Exist  then rate field vs productKay
		array('row' => array('stamp' => 'q4', 'aid' => 27, 'sid' => 31, 'type' => 'r', "uf" => array("d" => array("m" => 12345678)), "rate" => "SMS", 'plan' => 'WITH_NOTHING', 'usaget' => 'sms', 'usagev' => 20, 'urt' => '2018-03-14 11:00:00+03:00'),
			'expected' => array('result' => 'Rate not found')),
	];

	public function __construct($label = false) {
		parent::__construct("test Rate");
		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'Rate_Usage', 'autoload' => false));
		$this->construct(null, ['lines', 'balances']);
		$this->setColletions();
		$this->loadDbConfig();
	}

	public function loadDbConfig() {
		Billrun_Config::getInstance()->loadDbConfig();
	}

	public function TestPerform() {
		foreach ($this->rows as $key => $row) {
			$fixrow = $this->fixRow($row['row'], $key);
			$this->linesCol->insert($fixrow);
			$updatedRow = $this->runT($fixrow['stamp']);
			$result = $this->compareExpected($key, $updatedRow, $row);
			$this->assertTrue($result[0]);
			print ($result[1]);
			print('<p style="border-top: 1px dashed black;"></p>');
		}
		$this->restoreColletions();
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
		if (in_array('Rate not found', $row['expected'])) {
			$message .= "</br> Should not find a rate";
		} else {
			foreach ($row['expected'] as $key => $value) {
				$message .= "</br>rate_key: $key => Tariff_Category  :  $value ";
			}
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
						$message .= "rate_key:" . $error . " {$checkRate['key']} </span>=> Tariff_Category  :  {$checkRate['tariff_category']}  $this->fail ";
						$passed = false;
					}
				} else {
					$passed = false;
					$message .= $error . "1No found any product and category";
				}
			}
			$message .= ' </span></br>';
		} elseif (!empty($row['expected']['result'])) {
			$message .= "rate_key: <u><i>not found</i></u> $this->pass";
		} else {
			$passed = false;
			$message .= $error . "2No found any product and category  ";
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
