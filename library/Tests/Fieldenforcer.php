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
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

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
	
	// TODO: Move the unique tests from the subscribers collection to some test collection,
	// Currently this test will not be validated if executed by jenkins, need to make
	// sure that it works.
	protected $uniqueTests = array(
		array('data' => array(), 'conf' => array(array('field_name' => 'Bla', 'unique' => 1)), "valid" => true, "msg" => "Non value supplied"),
		array('data' => array('Bla' => 9), 'conf' => array(array('field_name' => 'Bla', 'unique' => 1)), "valid" => true, "msg" => "Unique and doesn't exist 1"),
		array('data' => array('firstname' => "Test"), 'conf' => array(array('field_name' => 'firstname', 'unique' => 1)), "valid" => true, "msg" => "Unique and doesn't exist 2"),
		array('data' => array('firstname' => "A"), 'conf' => array(array('field_name' => 'firstname', 'unique' => 1)), "valid" => false, "msg" => "Unique exists"),
		array('data' => array('Bla' => "test", 'firstname' => "A"), 'conf' => array(
																				array('field_name' => 'firstname', 'unique' => 1),
																				array('field_name' => 'Bla', 'unique' => 1)),
																				"valid" => false, "msg" => "One unique exists and one doesn't"),
		array('data' => array('lastname' => "AGD", 'firstname' => "A"), 'conf' => array(
																				array('field_name' => 'firstname', 'unique' => 1),
																				array('field_name' => 'lastname', 'unique' => 1)),
																				"valid" => false, "msg" => "Both unique exists"),
		array('data' => array('lastname' => "_TEST", 'firstname' => "_TEST"), 'conf' => array(
																				array('field_name' => 'firstname', 'unique' => 1),
																				array('field_name' => 'lastname', 'unique' => 1)),
																				"valid" => true, "msg" => "Both unique doesn't exists"),
		
		// Validate mandatory AND unique
		array('data' => array('lastname' => "_TEST", 'firstname' => "_TEST"), 'conf' => array(
																				array('field_name' => 'firstname', 'unique' => 1, 'mandatory' => 1)),
																				"valid" => true, "msg" => "Unique and mandatory"),
		array('data' => array('lastname' => "_TEST", 'firstname' => "A"), 'conf' => array(
																				array('field_name' => 'firstname', 'unique' => 1, 'mandatory' => 1)),
																				"valid" => false, "msg" => "Non-Unique and mandatory"),
		// Validate unique AND type
		array('data' => array('aid' => 343), 'conf' => array(
															array('field_name' => 'aid', 'type' => "Integer", 'unique' => 1)),
															"valid" => false, "msg" => "Unique and integer type"),
		array('data' => array('aid' => 9696), 'conf' => array(
															array('field_name' => 'aid', 'type' => "Integer", 'unique' => 1)),
															"valid" => true, "msg" => "Non-Unique and integer type"),

	);
	
	protected $typeTests = array(
		// Boolean type tests
		array('data' => array('Bla' => true), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Boolean')), "valid" => true, "msg" => "Boolean type 1"),
		array('data' => array('Bla' => false), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Boolean')), "valid" => true, "msg" => "Boolean type 2"),
		array('data' => array('Bla' => 10), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Boolean')), "valid" => false, "msg" => "Int for boolean type"),
		array('data' => array('Bla' => "10"), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Boolean')), "valid" => false, "msg" => "String for boolean type"),
		
		// Integer type tests
		array('data' => array('Bla' => true), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Integer')), "valid" => false, "msg" => "Boolean for integer type 1"),
		array('data' => array('Bla' => false), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Integer')), "valid" => false, "msg" => "Boolean for integer type 2"),
		array('data' => array('Bla' => 10), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Integer')), "valid" => true, "msg" => "Int type 10"),
		array('data' => array('Bla' => 0), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Integer')), "valid" => true, "msg" => "Int type 0"),
		array('data' => array('Bla' => -10), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Integer')), "valid" => true, "msg" => "Int type -10"),
		array('data' => array('Bla' => "10"), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Integer')), "valid" => true, "msg" => "Integer in string format"),
		array('data' => array('Bla' => "a1x0"), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Integer')), "valid" => false, "msg" => "String for integer"),
		
		array('data' => array('A' => true, 'B' => 9), 'conf' => array(
																	array('field_name' => 'A', 'type' => 'Boolean'),
																	array('field_name' => 'B', 'type' => 'Integer')),
			"valid" => true, "msg" => "Boolean and Integer"),
		array('data' => array('A' => -10, 'B' => false), 'conf' => array(
																	array('field_name' => 'A', 'type' => 'Integer'),
																	array('field_name' => 'B', 'type' => 'Boolean')),
			"valid" => true, "msg" => "Integer and Boolean"),
		array('data' => array('A' => "None", 'B' => false), 'conf' => array(
																	array('field_name' => 'A', 'type' => 'Integer'),
																	array('field_name' => 'B', 'type' => 'Boolean')),
			"valid" => false, "msg" => "Integer invalid and Boolean valid"),
		array('data' => array('A' => 88, 'B' => 44), 'conf' => array(
																	array('field_name' => 'A', 'type' => 'Integer'),
																	array('field_name' => 'B', 'type' => 'Boolean')),
			"valid" => false, "msg" => "Integer valid and Boolean invalid"),
		
		// Date type tests
		array('data' => array('Bla' => '2016-10-10'), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Date')), "valid" => true, "msg" => "Valid date 1"),
		array('data' => array('Bla' => '2016-32-10'), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Date')), "valid" => false, "msg" => "Invalid date format"),
		array('data' => array('Bla' => '+1 month'), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Date')), "valid" => true, "msg" => "Date in spoken words"),
		array('data' => array('Bla' => 2015), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Date')), "valid" => false, "msg" => "Integer for date"),
		array('data' => array('Bla' => "Just a string"), 'conf' => array(array('field_name' => 'Bla', 'type' => 'Date')), "valid" => false, "msg" => "Invalid string"),
	);
	
	public function testUnique() {
		$this->runTest($this->uniqueTests);
	}
	
	/**
	 * Test the mandatory field enforcer.
	 */
	public function testMandatory() {
		$this->runTest($this->mandatoryTests);
	}
	
	/**
	 * Test the type field enforcer.
	 */
	public function testTypeEnforcer() {
		$this->runTest($this->typeTests);
	}
	
	public function runTest($testCases) {
		foreach ($testCases as $test) {
			$this->internalTestRun($test);
		}
	}
	
	public function internalTestRun($test) {
		$result = true;
		try {
			$this->enforce($test['conf'], $test['data']);
		} catch (Billrun_Exceptions_InvalidFields $e) {
			$result = false;
		}
		$this->assertEqual($test['valid'], $result, $test['msg']);
	}
	
	protected function _getCollection() {
		// TODO: This is just for the test!!!
		return Billrun_Factory::db()->subscribersCollection();
	}
}
