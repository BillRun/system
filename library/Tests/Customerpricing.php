<?php

/*
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for the config module
 *
 * @package         Tests
 * @subpackage      Config
 * @since           4.4
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');
define('UNIT_TESTING', 'true');
class Tests_Customerpricing extends UnitTestCase {
	protected $calculator;
	protected $reflectionCalculator;
	
	protected $sidsQueuedForRebalance = array(
		1,2,3,4,5,6,7,8,9,10
	);
	
	protected $lineLegitimateTests = array(
			// Simple negative tests
			array("msg" => "Empty array", 'line' => array(), 'expected' => false),
			array("msg" => "Null value", 'line' => array(null), 'expected' => false),		
			array("msg" => "Zero value", 'line' => array(0), 'expected' => false),		
			array("msg" => "Integer value", 'line' => array(100000), 'expected' => false),		
			array("msg" => "String value", 'line' => array("Test String"), 'expected' => false),		
		
			array("msg" => "Empty array", 'line' => array('arate'), 'expected' => false),
			array("msg" => "Null value", 'line' => array('arate' => null), 'expected' => false),		
			array("msg" => "Zero value", 'line' => array('arate' => 0), 'expected' => false),		
			array("msg" => "Integer value", 'line' => array('arate' => 100000), 'expected' => false),		
			array("msg" => "String value", 'line' => array('arate' => "Test String"), 'expected' => false),		
		);
	protected $updateRowTests = array(
			// Simple negative tests
//			array("msg" => "Non existing SID", 'line' => array('sid' => 100), 'expected' => false),		
//			array("msg" => "Non integer SID", 'line' => array('sid' => "100"), 'expected' => false),		
//			array("msg" => "Existing SID", 'line' => array('sid' => 1), 'expected' => false),		
//			array("msg" => "Non integer existing SID", 'line' => array('sid' => "1"), 'expected' => false),
//			array("msg" => "Billable false", 'line' => array('sid' => 1, 'billable' => false), 'expected' => false),
//			array("msg" => "Billable true", 'line' => array('sid' => 1, 'billable' => true), 'expected' => false)
		);
	
	public function __construct($label = false) {
		parent::__construct($label);
		$this->calculator = new Billrun_Calculator_CustomerPricing();
		$this->reflectionCalculator = new ReflectionClass('Billrun_Calculator_CustomerPricing');
		
		$reflectionInternalSIDs = $this->reflectionCalculator->getProperty('sidsQueuedForRebalance');
		$reflectionInternalSIDs->setAccessible(true);
		$reflectionInternalSIDs->setValue($this->calculator, $this->sidsQueuedForRebalance);
	}
	
	public function testLineLegitimate() {
		$passed = 0;
		foreach ($this->lineLegitimateTests as $test) {
			$line = new Billrun_AnObj($test['line']);
			$result = $this->calculator->isLineLegitimate($line);
			if($this->assertEqual($result, $test['expected'], $test['msg'])) {
				$passed++;
			}
		}
		print_r("Finished " . __FUNCTION__ . " passed " . $passed . " out of " . count($this->lineLegitimateTests) . " cases.<br>");
    }
	
	public function testUpdateRow() {
		$passed = 0;
		foreach ($this->updateRowTests as $test) {
			$line = new Billrun_AnObj($test['line']);
			$result = $this->calculator->updateRow($line);
			if($this->assertEqual($result, $test['expected'], $test['msg'])) {
				$passed++;
			}
		}
		print_r("Finished " . __FUNCTION__ . " passed " . $passed . " out of " . count($this->updateRowTests) . " cases.<br>");
    }
}
