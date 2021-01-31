<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for rate logic
 *
 * @package         Tests
 * @subpackage      
 * @since           5.2
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

class Tests_Rate extends UnitTestCase {
		protected $getChargeByRate = array(
			// Simple negative tests
//			array("msg" => "Non existing SID", 'usage_type' => 'prepaid','volume' => 1, 'time'=> NULL,'offset'=> 0, 'rate' => array('sid' => 100), 'expectedTotals' => false, 'expectedInter' => false),		
		);
		
		protected $interconnectTests = array(
			// Positive tests
			array("msg" => "Interconnect in plan", "expected" => "Test", "type" => "testing", "plan" => "testPlan", 
				  "rate" => array("rates" => array("testing" => array("testPlan" => array("interconnect" => "Test"))))),
			array("msg" => "Interconnect in usage type", "expected" => "Test", "type" => "testing", "plan" => "testPlan",
				  "rate" => array("rates" => array("testing" => array("interconnect" => "Test")))),
			array("msg" => "Interconnect in BASE type", "expected" => "Test", "type" => "testing", "plan" => "testPlan", 
				  "rate" => array("rates" => array("testing" => array("BASE" => array("interconnect" => "Test"))))),
			
			// Negative tests
			array("msg" => "No interconnect in plan", "expected" => false, "type" => "testing", "plan" => "testPlan",
				  "rate" => array("rates" => array("testing" => array("testPlan" => array("_interconnect" => "Test"))))),
			array("msg" => "No interconnect in usage type", "expected" => false, "type" => "testing", "plan" => "testPlan",
				  "rate" => array("rates" => array("testing" => array("_interconnect" => "Test")))),
			array("msg" => "No interconnect in BASE type", "expected" => false, "type" => "testing", "plan" => "testPlan",
				  "rate" => array("rates" => array("testing" => array("BASE" => array("_interconnect" => "Test"))))),
			
			// Edge cases
			array("msg" => "Integer input", "expected" => false, "type" => "testing", "plan" => "testPlan",
				  "rate" => 10),
			array("msg" => "String input", "expected" => false, "type" => "testing", "plan" => "testPlan",
				  "rate" => "Test string"),
			array("msg" => "Null input", "expected" => false, "type" => "testing", "plan" => "testPlan",
				  "rate" => null),
		);
		
		
	public function testGetInterconnect() {
		foreach ($this->interconnectTests as $test) {
			$rate = $test['rate'];
			$plan = $test['plan'];
			$type = $test['type'];
			$result = Billrun_Rates_Util::getInterconnect($rate, $type, $plan);
			$expected = $test['expected'];
			$this->assertEqual($result, $expected, $test['msg']);
		}
	}
	
	public function testGetChargesByRate() {
		$passed = 0;
		$passedInterconnect = 0;
		foreach ($this->getChargeByRate as $test) {
			$rate = new Billrun_AnObj($test['rate']);
			$usageType = $test['usage_type'];
			$volume = $test['volume'];
			$time = $test['time'];
			$offset = $test['offset'];
			$plan = null;
			if(isset($test['plan'])) {
				$plan = new Billrun_AnObj($test['plan']);
			}
			$result = Billrun_Rates_Util::getCharges($rate, $usageType, $volume, $plan, $offset, $time);
			if($this->assertEqual($result['total'], $test['expectedTotals'], 'Assert Totals: ' . $test['msg'])) {
				$passed++;
			}
			if($this->assertEqual($result['interconnect'], $test['expectedInter'], 'Assert Interconnect: ' . $test['msg'])) {
				$passedInterconnect++;
			}
		}
		print_r("Finished " . __FUNCTION__ . " passed [Totals: " . $passed . "],[Interconnect: " . $passedInterconnect . "] out of " . count($this->getChargeByRate) . " cases.<br>");
	}
}
