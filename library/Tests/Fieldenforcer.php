<?php

/*
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for the field enforcer
 *
 * @package         Tests
 * @subpackage      Config
 * @since           5.3
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

class Tests_Fieldenforcer extends UnitTestCase {
	use Billrun_Traits_FieldValidator;
	protected $mandatoryTests = array(
		array('data' => array(), 'conf' => array(array('field_name' => 'Bla', 'mandatory' => 0)), "valid" => true, "msg" => "Non mandatory"),
		array('data' => array('Bla' => 9), 'conf' => array(array('field_name' => 'Bla', 'mandatory' => 1)), "valid" => true, "msg" => "Mandatory exists"),
		array('data' => array('_Bla' => 9), 'conf' => array(array('field_name' => 'Bla', 'mandatory' => 1)), "valid" => false, "msg" => "Mandatory doesn't exists"),
		array('data' => array('A' => 1, 'B' => 2, 'C' => 3), 'conf' => array(
																			array('field_name' => 'A', 'mandatory' => 1),
																			array('field_name' => 'B', 'mandatory' => 1)), 
			"valid" => true, "msg" => "Double mandatory exists"),
		array('data' => array('A' => 1, 'B' => 2, 'C' => 3), 'conf' => array(
																			array('field_name' => 'A', 'mandatory' => 1),
																			array('field_name' => 'C', 'mandatory' => 1), 
																			array('field_name' => 'B', 'mandatory' => 1)), 
			"valid" => true, "msg" => "Triple mandatory exists"),
		array('data' => array('A' => 1, '_B' => 2, 'C' => 3), 'conf' => array(
																			array('field_name' => 'A', 'mandatory' => 1),
																			array('field_name' => 'C', 'mandatory' => 1), 
																			array('field_name' => 'B', 'mandatory' => 1)), 
			"valid" => false, "msg" => "One out of two doesn't exist 1"),
		array('data' => array('A' => 1, 'B' => 2, '_C' => 3), 'conf' => array(
																			array('field_name' => 'A', 'mandatory' => 1),
																			array('field_name' => 'C', 'mandatory' => 1), 
																			array('field_name' => 'B', 'mandatory' => 1)), 
			"valid" => false, "msg" => "One out of two doesn't exist 2"),
		array('data' => array('A' => 1, '_B' => 2, '_C' => 3), 'conf' => array(
																			array('field_name' => 'A', 'mandatory' => 1),
																			array('field_name' => 'C', 'mandatory' => 1), 
																			array('field_name' => 'B', 'mandatory' => 1)), 
			"valid" => false, "msg" => "Two out of three doesn't exist 1"),
		array('data' => array('_A' => 1, '_B' => 2, 'C' => 3), 'conf' => array(
																			array('field_name' => 'A', 'mandatory' => 1),
																			array('field_name' => 'C', 'mandatory' => 1), 
																			array('field_name' => 'B', 'mandatory' => 1)), 
			"valid" => false, "msg" => "Two out of three doesn't exist 2"),
		array('data' => array('_A' => 1, 'B' => 2, '_C' => 3), 'conf' => array(
																			array('field_name' => 'A', 'mandatory' => 1),
																			array('field_name' => 'C', 'mandatory' => 1), 
																			array('field_name' => 'B', 'mandatory' => 1)), 
			"valid" => false, "msg" => "Two out of three doesn't exist 3"),
		array('data' => array('_A' => 1, '_B' => 2, '_C' => 3), 'conf' => array(
																			array('field_name' => 'A', 'mandatory' => 1),
																			array('field_name' => 'C', 'mandatory' => 1), 
																			array('field_name' => 'B', 'mandatory' => 1)), 
			"valid" => false, "msg" => "Three out of three doesn't exist"),

	);
	protected $currentCase;
	
	/**
	 * Test the mandatory field enforcer.
	 */
	public function testMandatory() {
		foreach ($this->mandatoryTests as $test) {
			$result = true;
			try {
				$this->enforce($test['conf'], $test['data']);
			} catch (Billrun_Exceptions_InvalidFields $e) {
				$result = false;
			}
			$this->assertEqual($test['valid'], $result, $test['msg']);
		}
	}
	
	public function complexWrapperTest($testCases) {
		foreach ($testCases as $test) {
			$wrapper = Billrun_Config::getComplexWrapper($test);
			if($wrapper === null) {
				continue;
			}
			$result = $wrapper->validate();
			$this->assertEqual($result, $test['valid'], $test['msg']);
			
			if (isset($test['expected'])) {
				$actualResult = $wrapper->value();
				$this->assertEqual($actualResult, $test['expected'], $test['msg']);
			}
		}
    }

	protected function _getCollection() {
		if($this->currentCase && isset($this->currentCase['collection'])) {
			return $this->currentCase['collection'];
		}
		return null;
	}

//	protected function getWrapper($complex) {
//		$name = "Billrun_DataTypes_Conf_" . ucfirst(strtolower($complex['t']));
//		if(!@class_exists($name)) {
//			return null;
//		}
//		
//		$wrapper = new $name($complex);
//		return $wrapper;
//	}
}
