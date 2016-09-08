<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Base test case class for testing an action to the API
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

abstract class Tests_Api_Base_Action extends Tests_TestWrapper {

	/**
	 * Array of tests.
	 * @var array
	 */
	protected $cases;
	
	/**
	 * Array of the API parameter names
	 * @var array
	 */
	protected $parameters;
	
	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $coll;
	
	/**
	 * Create a new instance of the API Action test object.
	 * @param Mongodloid_Collection $collection
	 * @param type $testCases
	 * @param type $inputParameters
	 * @param type $internalTestInstance
	 * @param type $label
	 */
	public function __construct($collection, $testCases, $inputParameters, $internalTestInstance = null, $label = false) {
		if($internalTestInstance == null) {
			$internalTestInstance = $this;
		}
		parent::__construct($internalTestInstance, $label);
		
		$this->parameters = $inputParameters;
		$this->cases = $testCases;
		$this->coll = $collection;
	}
	
	/**
	 * Get an instance of the action.
	 */
	protected abstract function getAction($param);
	
	/**
	 * Get the query 
	 * @return array query for the action.
	 */
	protected abstract function getQuery($case);
	
	/**
	 * Return the array of data that should be added to the DB for the current
	 * case.
	 * When the test tries to delete a record that does not exist, this function
	 * introduces the record to the DB to be removed after.
	 */
	protected abstract function getDataForDB($case);

	/**
	 * Abstract function to execute when the record to be deleted already exists.
	 * @return boolean, if true continue with the test, if false terminate the 
	 * test case
	 */
	protected function onRecordExists($case) {
		$this->assertTrue(false, 'Record to be created, already exists! ' . $case['msg']);
		return false;
	}
	
	/**
	 * Run the internal logic
	 */
    public function run() {
		$action = $this->getAction();

		// Go through the cases.
		foreach ($this->cases as $case) {
			$this->runCase($action, $case);
		}
    }
	
	/**
	 * Run a case on an action.
	 * @param type $action
	 * @param type $case
	 * @return type
	 */
	protected function runCase($action, $case) {
		if(!$this->preRun($case)) {
			return;
		}
		$actionTest = $this->buildInput($case);
		if(!$this->internalRun($action, $actionTest)) {
			$this->assertFalse($case['valid'], "Faild parsing " . $case['msg']);
			// Break the logic
			return;
		}

		if(!$this->postRun($case)) {
			return;
		}
		
		$this->assertTrue($case['valid'], $case['msg']);
	}
	
	/**
	 * Get the query to be used for clearing the case after the test is done
	 * @param arary $case - Test case to be cleared.
	 * @return array - Query for the data to be cleared from the database.
	 */
	protected function getClearCaseQuery($case) {
		return $this->getQuery($case);
	}
	
	protected function clearCase($case) {
		// Check if it exists.
		$query = $this->getQuery($case);
		
		$removed = $this->coll->remove($query);
		
		if($removed < 1) {
			$this->assertFalse($case['valid'], "Failed to create in DB " . $case['msg']);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Post run logic
	 * @return boolean true if successful.
	 */
	protected function postRun($case) {
		return $this->clearCase($case);
	}
	
	/**
	 * Builds the input for the action.
	 * @param array $test
	 * @return \Billrun_AnObj
	 */
	protected function buildInput($test) {
		$escaped = array();
		foreach ($test as $key => $value) {
			if(in_array($key, $this->parameters)) {
				$value = $this->escape($value);
			}
			$escaped[$key] = $value;
		}
		
		return new Billrun_AnObj($escaped);
	}
	
	protected function createRecord($case) {
		$dataForDB = $this->getDataForDB($case);
		$this->coll->insert($dataForDB);
	}
	
	/**
	 * Escape function to map to
	 * @param type $value
	 * @return type
	 */
	protected function escape($value) {
		return json_encode($value);
	}
	
	/**
	 * Run a single test logic
	 * @param Billrun_ActionManagers_Services_Action $action
	 * @param Billrun_AnObj $test
	 */
	protected function internalRun(Billrun_ActionManagers_IAPIAction $action, Billrun_AnObj $test) {
		// Test parsing.
		if(!$action->parse($test)) {
			return false;
		}
		
		$result = $action->execute();
		return $this->handleResult($result);
	}
	
	/**
	 * Function to run on failure of the execute function.
	 * @param type $result
	 */
	protected function onExecuteFailed($result) {
		$this->dump($result, "Execute failed");
	}
	
	/**
	 * Handle the output message result.
	 * @param type $result
	 * @return boolean
	 */
	protected function handleResult($result) {
		if(!$result['status']) {
			$this->onExecuteFailed($result);
			return false;
		}
		
		return true;
	}
	
	protected function preRun($case) {
		// Get the record to remove from the db
		$query = $this->getQuery($case);
		
		// Check if it exists.
		$this->toRemove = $this->coll->find($query)->current();
		
		// If the record exists execute custom logic
		if($this->toRemove && !$this->toRemove->isEmpty()) {
			return $this->onRecordExists($case);
		}
		
		$this->createRecord($case);
		return true;
	}
}
