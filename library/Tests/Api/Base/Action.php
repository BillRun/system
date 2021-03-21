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
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

abstract class Tests_Api_Base_Action extends Tests_TestWrapper {

	/**
	 * Boolean indication for is the case cleared
	 * @var boolean
	 */
	private $caseCleared = false;
	
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
	 * Current test case
	 * @var array
	 */
	protected $current;
	
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
		$this->cases = $this->translateCases($testCases);
		$this->coll = $collection;
	}
	
	/**
	 * Get an instance of the action.
	 * @return Billrun_ActionManagers_APIAction
	 */
	protected abstract function getAction($param = array());
	
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
	 * @param array $case - Current case being proccessed
	 * @return array Array to store in the data base.
	 */
	protected abstract function getDataForDB($case);

	/**
	 * Translate the date fields
	 * Override this function for special case translation.
	 * @param type $cases
	 * @return type
	 */
	protected function translateCases($cases) {
		$translated = array();
		foreach ($cases as $caseIndex => $case) {
			if(Billrun_Util::isMultidimentionalArray($case)) {
				$translated[$caseIndex] = $this->translateCases($case);
			} else if(is_array($case)) {
				$translated[$caseIndex] = $this->translateCaseArray($case);
			} else {
				$translated[$caseIndex] = $case;
			}
		}
		return $translated;
	}
	
	protected function translateCaseArray($caseArray) {
		$translated = array();
		foreach ($caseArray as $key => $value) {
			$translated[$key] = $this->translateSingleCase($key, $value);
		}
		return $translated;
	}
	
	protected function translateSingleCase($key, $value) {
		if(in_array($key, array("from", "to"))) {
			return new MongoDate(strtotime($value));
		}
		return $value;
	}
	
	/**
	 * 
	 * @param type $result
	 */
	protected function onAssert($result) {
		if(!$result) {
			$this->clearCase($this->current);
		}
	}
	
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
	 * Function to execute on case run failure.
	 * @param array $case - Failed case.
	 */
	protected function caseFail($case) {
		$this->dump($case, "Case failed:");
	}
	
	/**
	 * Run a case on an action.
	 * @param type $action
	 * @param type $case
	 * @return type
	 */
	protected function runCase($action, $case) {
		$this->caseCleared = false;
		if(!$this->preRun($case)) {
			$this->caseFail($case);
			return;
		}
		
		// Run the parse and execute functions.
		if(!$this->internalRun($action, $case)) {
			$this->caseFail($case);
			return;
		}

		if(!$this->postRun($case)) {
			$this->caseFail($case);
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
		if($this->caseCleared) {
			return true;
		}
		
		// Check if it exists.
		$query = $this->getClearCaseQuery($case);
		
		$removed = $this->coll->remove($query);
		
		$this->caseCleared = true;
		
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
		
		$cloned = array_merge($dataForDB);
		
		$this->coll->insert($cloned);
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
	 * @param array $case - Current case.
	 */
	protected function internalRun(Billrun_ActionManagers_IAPIAction $action, array $case) {
		$test = $this->buildInput($case);
		if(!$test) {
			$this->assertTrue(false, "Received null input data!");
			return false;
		}
		
		try{
			// Test parsing.
			$parseResult = $action->parse($test);
		} catch(Billrun_Exceptions_Base $ex) {
			$parseResult = false;
			Billrun_Factory::log()->logCrash($ex, Zend_Log::DEBUG);
		}
		
		if(!$parseResult) {
			$this->assertFalse($case['valid'], "Faild parsing " . $case['msg']);
			return false;
		}
		
		try {
			$result = $action->execute();
		} catch (Billrun_Exceptions_Base $ex) {
			Billrun_Factory::log()->logCrash($ex, Zend_Log::DEBUG);
			$result = json_decode($ex->output(), 1);
		}
		
		if(!$this->handleResult($result)) {
			$this->assertFalse($case['valid'], "Faild executing " . $case['msg']);
			return false;
		}
		
		return true;
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
	
	/**
	 * Extract the queried data from the results of the API action execute function.
	 * Override this function if you need special handling of the results array.
	 * @param arary $results - Results array from the execute function of the 
	 * API action.
	 * @return array - Data to compare to the expected field.
	 */
	protected function extractFromResults($results) {
		if(!isset($results['details'])) {
			return array();
		}
		
		$details = $results['details'];
		foreach ($details as &$value) {
			unset($value['_id']);
		}
		
		return $details;
	}
	
	protected function preRun($case) {
		$this->current = $case;
		
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
