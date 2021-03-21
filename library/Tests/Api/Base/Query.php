<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class querying from the API
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

abstract class Tests_Api_Base_Query extends Tests_Api_Base_Action {
	
	const EXPECTED_IDENTIFIER = 'expected';

	protected function getExpected() {
		return $this->current['expected'];
	}
	
	/**
	 * Compare the expected value with the queried value
	 * @param type $result
	 * @return type
	 */
	protected function checkExpected($result) {
		$expected = $this->getExpected();
		$resultData = $this->extractFromResults($result);
		$message = $this->current['msg'];
		if(!$this->assertEqual($resultData, $expected, $message . ": Comparing expected and result.")) {
			$this->dump($expected, "Expected:");
			$this->dump($resultData, "Result:");
			return false;
		}
		
		return true; 
	}
	
	protected function handleResult($result) {
		if(!parent::handleResult($result)) {
			return false;
		}
		
		return $this->checkExpected($result);
	}
	
	/**
	 * Overriding the translate cases, if there is only one special parameter,
	 * copy that content to the expected field.
	 * @param array $cases - Current cases to be ran
	 * @return array translated cases.
	 */
	protected function translateCases($cases) {
		$parentResult = parent::translateCases($cases);
		if(count($this->parameters) != 1) {
			return $parentResult;
		}
		
		$expectedField = $this->parameters[0];
		
		// Validate the expected field.
		if(!$this->assertIsA($expectedField, "string", "Invalid input parameters")) {
			return $parentResult;
		}
		
		return $this->constructExpectedClause($parentResult, $expectedField);
	}
	
	/**
	 * Get the array of cases with the expected clause extracted from the parameters.
	 * @param array $translatedCases - The array of generaly translated cases.
	 * @param string $expectedField - The name of the field to copy the value of
	 * to the expected field
	 * @return array of translated cases with the expected clause.
	 */
	protected function constructExpectedClause($translatedCases, $expectedField) {
		// Construct the expected
		foreach ($translatedCases as $key => $value) {
			if($key == $expectedField) {
				$queryCases[self::EXPECTED_IDENTIFIER] = $value;
			}
			$queryCases[$key] = $value;
		}
		
		return $queryCases;
	}
}
