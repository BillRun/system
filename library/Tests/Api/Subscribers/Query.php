<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test class for the subscribers API
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');
//require_once(APPLICATION_PATH . '/library/Billrun/ActionManagers/Services/Create.php');

class Tests_Api_Subscribers_Query extends Tests_Api_Base_Query {

	public function __construct($testCases, $intenalTestInstance = null, $label = false) {		
		$collection = Billrun_Factory::db()->subscribersCollection();
		$inputParameters = array('query');
		parent::__construct($collection, $testCases, $inputParameters, $intenalTestInstance, $label);
	}

	/**
	 * Get an instance of the action.
	 * @return Billrun_ActionManagers_APIAction
	 */
	protected function getAction($param = array()) {
		return new Billrun_ActionManagers_Subscribers_Query();
	}

	/**
	 * Return the array of data that should be added to the DB for the current
	 * case.
	 * When the test tries to delete a record that does not exist, this function
	 * introduces the record to the DB to be removed after.
	 * @param array $case - Current case being proccessed
	 * @return array Array to store in the data base.
	 */
	protected function getDataForDB($case) {
		$data = $case['query'];
		
		foreach ($data as $key => &$value) {
			if(in_array($key, array('to', 'from'))) {
				$value = new Mongodloid_Date(strtotime($value));
			}
		}
		
 		$type = $case['type'];
		$data['type'] = $type;
		
		return $data;
	}

	/**
	 * Get the query 
	 * @return array query for the action.
	 */
	protected function getQuery($case) {
		$query = $case['query'];
		
		// Remove unnecessary fields
		$query['to'] = new Mongodloid_Date(strtotime($query['to']));
		$query['from'] = new Mongodloid_Date(strtotime($query['from']));
		unset($query['description']);
		
		return $query;
	}

	protected function extractFromResults($results) {
		$details = parent::extractFromResults($results);
		foreach ($details as $key => &$value) {
			foreach ($value as $fieldName => &$fieldValue) {				
				if(in_array($fieldName, array('to', 'from'))) {
					$fieldValue = new Mongodloid_Date(strtotime($fieldValue));
				}
			}
		}
		return Billrun_Util::getFieldVal($details[0], array());
	}
	
	protected function constructExpectedClause($translatedCases, $expectedField) { 
		if(!isset($translatedCases['query'])) {
			return $translatedCases;
		}
		
		$testData = parent::constructExpectedClause($translatedCases, $expectedField);
		return $this->constructExpectedStep($testData);
	}
	
	protected function constructExpectedStep($caseData) {
		$type = $caseData['type'];
		$caseData[self::EXPECTED_IDENTIFIER]['type'] = $type;
		return $caseData;
	}
	
	protected function getExpected() {
		$expected = parent::getExpected();
		$expected['from'] = new Mongodloid_Date(strtotime($expected['from']));
		$expected['to'] = new Mongodloid_Date(strtotime($expected['to']));
		return $expected;
	}
	
	protected function translateSingleCase($key, $value) {
		if(in_array($key, array("from", "to"))) {
//			return new Mongodloid_Date(strtotime($value));
		}
		return $value;
	}
}
