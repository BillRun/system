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

class Tests_Updaterowt extends UnitTestCase {

	protected $ratesCol;
	protected $plansCol;
	protected $linesCol;
	protected $calculator;
	protected $servicesToUse = ["SERVICE1", "SERVICE2"];
	protected $fail = ' <span style="color:#ff3385; font-size: 80%;"> failed </span> <br>';
	protected $pass = ' <span style="color:#00cc99; font-size: 80%;"> passed </span> <br>';
	protected $rows = [
		//case A: PLAN-X3+SERVICE1+SERVICE2
		array('stamp' => 'a1', 'sid' => 51, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 60, 'services' => ["SERVICE1", "SERVICE2"]),
		array('stamp' => 'a2', 'sid' => 51, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 50, 'services' => ["SERVICE1", "SERVICE2"]),
		array('stamp' => 'a3', 'sid' => 51, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 50, 'services' => ["SERVICE1", "SERVICE2"]),
		array('stamp' => 'a4', 'sid' => 51, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 280, 'services' => ["SERVICE1", "SERVICE2"]),
		array('stamp' => 'a5', 'sid' => 51, 'arate_key' => 'CALL-EUROPE', 'plan' => 'PLAN-X3', 'usagev' => 180, 'services' => ["SERVICE1", "SERVICE2"]),
		//case B: PLAN-X3+SERVICE3
		array('stamp' => 'b1', 'sid' => 52, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 120, 'services' => ["SERVICE3"]),
		array('stamp' => 'b2', 'sid' => 52, 'arate_key' => 'CALL-EUROPE', 'plan' => 'PLAN-X3', 'usagev' => 110.5, 'services' => ["SERVICE3"]),
		array('stamp' => 'b3', 'sid' => 52, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 20, 'services' => ["SERVICE3"]),
		array('stamp' => 'b4', 'sid' => 52, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-X3', 'usagev' => 75.4, 'services' => ["SERVICE3"]),
		array('stamp' => 'b5', 'sid' => 52, 'arate_key' => 'CALL-EUROPE', 'plan' => 'PLAN-X3', 'usagev' => 8, 'services' => ["SERVICE3"]),
		//case C: PLAN-A0 (without groups)+SERVICE1+SERVICE4  
		array('stamp' => 'c1', 'sid' => 53, 'arate_key' => 'VEG', 'plan' => 'PLAN-A0', 'usaget' => 'gr', 'usagev' => 35, 'services' => ["SERVICE4"]),
		array('stamp' => 'c2', 'sid' => 53, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 35.5, 'services' => ["SERVICE1"]),
		array('stamp' => 'c3', 'sid' => 53, 'arate_key' => 'VEG', 'plan' => 'PLAN-A0', 'usaget' => 'gr', 'usagev' => 180, 'services' => ["SERVICE4"]),
		array('stamp' => 'c4', 'sid' => 53, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 4.5, 'services' => ["SERVICE1"]),
		array('stamp' => 'c5', 'sid' => 53, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A0', 'usaget' => 'call', 'usagev' => 12, 'services' => ["SERVICE1"]),
		//case D PLAN-A1 (with two groups) no services
		array('stamp' => 'd1', 'sid' => 54, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 24),
		array('stamp' => 'd2', 'sid' => 54, 'arate_key' => 'VEG', 'plan' => 'PLAN-A1', 'usaget' => 'gr', 'usagev' => 12),
		array('stamp' => 'd3', 'sid' => 54, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 50),
		array('stamp' => 'd4', 'sid' => 54, 'arate_key' => 'VEG', 'plan' => 'PLAN-A1', 'usaget' => 'gr', 'usagev' => 80),
		array('stamp' => 'd5', 'sid' => 54, 'arate_key' => 'CALL-EUROPE', 'plan' => 'PLAN-A1', 'usaget' => 'call', 'usagev' => 50.5),
		//case E PLAN-A2 multiple groups with same name
		array('stamp' => 'e1', 'sid' => 55, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services' => ["SERVICE1"]),
		array('stamp' => 'e2', 'sid' => 55, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 75, 'services' => ["SERVICE1"]),
		array('stamp' => 'e3', 'sid' => 55, 'arate_key' => 'CALL-USA', 'plan' => 'PLAN-A2', 'usaget' => 'call', 'usagev' => 30, 'services' => ["SERVICE1"]),
//		array('stamp' => 'e4', 'sid' => 55, 'arate_key' => 'VEG', 'plan' => 'PLAN-A2', 'usaget' => 'gr', 'usagev' => 30, 'services' => ["SERVICE1"])
	];
	protected $expected = [
		//case A expected
		array('in_group' => 60, 'over_group' => 0),
		array('in_group' => 50, 'over_group' => 0),
		array('in_group' => 50, 'over_group' => 0),
		array('in_group' => 55, 'over_group' => 225),
		array('in_group' => 0, 'over_group' => 180),
		//case B expected
		array('in_group' => 120, 'over_group' => 0),
		array('in_group' => 0, 'over_group' => 110.5),
		array('in_group' => 20, 'over_group' => 0),
		array('in_group' => 75, 'over_group' => 0.4),
		array('in_group' => 0, 'over_group' => 8),
		//case C expected
		array('in_group' => 35, 'over_group' => 0), //gr from service 4, remain 165
		array('in_group' => 35.5, 'over_group' => 0), //call from service 1, remain 165
		array('in_group' => 165, 'over_group' => 15), //gr from service 4, over
		array('in_group' => 4.5, 'over_group' => 0), //call from service 1, over
		array('in_group' => 0, 'over_group' => 12), //call over group
		//case D expected
		array('in_group' => 24, 'over_group' => 0), //call from plan
		array('in_group' => 12, 'over_group' => 0), //gr from plan
		array('in_group' => 26, 'over_group' => 24), //call from plan + over
		array('in_group' => 38, 'over_group' => 42), //gr from plan + over
		array('in_group' => 0, 'over_group' => 50.5), // over calls
		//case E expected
		array('in_group' => 30, 'over_group' => 0), //in groups
		array('in_group' => 50, 'over_group' => 25), //move group and over
		array('in_group' => 0, 'over_group' => 30), //over group
		array('in_group' => 0, 'over_group' => 30), //out group
	];

	public function __construct($label = false) {
		parent::__construct('test UpdateRow');
	}

	public function testUpdateRow() {
		
		$this->ratesCol = Billrun_Factory::db()->ratesCollection();
		$this->plansCol = Billrun_Factory::db()->plansCollection();
		$this->linesCol = Billrun_Factory::db()->linesCollection();
		$this->calculator = Billrun_Calculator::getInstance(array('type' => 'customerPricing', 'autoload' => false));
		$init = new Tests_UpdateRowSetUp();
		$init->setColletions();
		//Billrun_Factory::db()->subscribersCollection()->update(array('type' => 'subscriber'),array('$set' =>array('services'=>$this->servicesToUse)),array("multiple" => true));
		//running test
		foreach ($this->rows as $key => $row) {
			$row = $this->fixRow($row, $key);
			$this->linesCol->insert($row);
			$updatedRow = $this->runT($row['stamp']);
			$result = $this->compareExpected($key, $updatedRow);

			$this->assertTrue($result[0]);
			print ($result[1]);
			print('<p style="border-top: 1px dashed black;"></p>');
		}
//		$init->restoreColletions();
		//$this->assertTrue(True);
	}

	protected function runT($stamp) {
		$entity = $this->linesCol->query(array('stamp' => $stamp))->cursor()->current();
		$ret = $this->calculator->updateRow($entity);
		$this->calculator->writeLine($entity, '123');
		$this->calculator->removeBalanceTx($entity);
		$entityAfter = $entity->getRawData();
		return ($entityAfter);
	}

	//checks return data
	protected function compareExpected($key, $returnRow) {
		$passed = True;
		$epsilon = 0.000001;
		$inGroupE= $this -> expected[$key]['in_group'];
		$overGroupE= $this -> expected[$key]['over_group'];
		$message = '<p style="font: 14px arial; color: rgb(0, 0, 80);"> ' . ($key + 1) . '. <b> Expected: </b> <br> — in_group:'. $inGroupE .'<br> — over_group:' . $overGroupE . '<br> <b> &nbsp;&nbsp;&nbsp; Result: </b> <br>';
		
		if ($inGroupE == 0) {
			if ((!isset($returnRow['in_group'])) || Billrun_Util::isEqual($returnRow['in_group'],0,$epsilon)) {
				$message = $message . '— in_group: 0' . $this->pass;
			} else {
				$message = $message . '— in_group: ' . $returnRow['in_group'] . $this->fail;
				$passed = False;
			}
		} else {
			if (!isset($returnRow['in_group'])) {
				$message = $message . '— in_group: 0' . $this->fail;
				$passed = False;
			} else if (!Billrun_Util::isEqual($returnRow['in_group'],$inGroupE,$epsilon)) {
				$message = $message . '— in_group: ' . $returnRow['in_group'] . $this->fail;
				$passed = False;
			} else {
				$message = $message . '— in_group: ' . $returnRow['in_group'] . $this->pass;
			}
		}
		if ($overGroupE == 0) {
			if (((!isset($returnRow['over_group'])) || (Billrun_Util::isEqual($returnRow['over_group'],0,$epsilon))) && ((!isset($returnRow['out_plan'])) || (Billrun_Util::isEqual($returnRow['out_plan'],0,$epsilon)))) {
				$message = $message . '— over_group and out_plan: doesnt set' . $this->pass;
			} else {
				if (isset($returnRow['over_group'])) {
					$message = $message . '— over_group: ' . $returnRow['over_group'] . $this->fail;
					$passed = False;
				} else {
					$message = $message . '— out_plan: ' . $returnRow['out_plan'] . $this->fail;
					$passed = False;
				}
				$passed = False;
			}
		} else {
			if ((!isset($returnRow['over_group'])) && (!isset($returnRow['out_plan']))) {
				$message = $message . '— over_group and out_plan: dont set' . $this->fail;
				$passed = False;
			} else if (isset($returnRow['over_group'])) {
				if (!Billrun_Util::isEqual($returnRow['over_group'],$overGroupE,$epsilon)) {
					$message = $message . '— over_group: ' . $returnRow['over_group'] . $this->fail;
					$passed = False;
				} else {
					$message = $message . '— over_group: ' . $returnRow['over_group'] . $this->pass;
				}
			} else if (isset($returnRow['out_plan'])) {
				if (!Billrun_Util::isEqual($returnRow['out_plan'],$overGroupE,$epsilon)) {
					$message = $message . '— out_plan: ' . $returnRow['out_plan'] . $this->fail;
					$passed = False;
				} else {
					$message = $message . '— out_plan: ' . $returnRow['out_plan'] . $this->pass;
				}
			}
		}
		$message = $message . ' </p>';
		return [$passed, $message];
	}

	protected function fixRow($row, $key) {
		if (!isset($row['urt'])) {
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
		if (!isset($row['type'])) {
			$row['type'] = 'mytype';
		}
		if (!isset($row['usaget'])) {
			$row['usaget'] = 'call';
		}
		$rate = $this->ratesCol->query(array('key' => $row['arate_key']))->cursor()->current();
		$row['arate'] = MongoDBRef::create('rates', (new MongoId((string) $rate['_id'])));
		$plan = $this->plansCol->query(array('name' => $row['plan']))->cursor()->current();
		$row['plan_ref'] = MongoDBRef::create('plans', (new MongoId((string) $plan['_id'])));
		return $row;
	}

}
