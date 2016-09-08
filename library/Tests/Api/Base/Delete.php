<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class deleting from the API
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

abstract class Tests_Api_Base_Delete extends Tests_Api_Base_Action {
	
	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $coll;
	
	/**
	 * The record to remove
	 * @var Mongodloid_Entity
	 */
	protected $toRemove;
	
	public function __construct($collection, $testCases, $inputParameters, $internalTestInstance = null, $label = false) {
		parent::__construct($testCases, $inputParameters, $internalTestInstance, $label);
		$this->coll = $collection;
	}
	
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
	protected abstract function onRecordExists($case);
	
	/**
	 * Get the query action to validate the delete process.
	 * @return Billrun_ActionManagers_IAPIAction
	 */
	protected abstract function getQueryAction($case);
	
	/**
	 * This function handles the results of the query action.
	 * It should return a not found code, a different code for every API.
	 * Override this function to add handling of the query action result.
	 * @param array $results - Query action results.
	 * @return boolean, if true continue with test case run, if false stop the 
	 * test case run.
	 */
	protected abstract function onQueryAction($results);
	
	/**
	 * Return the paramters to initialize the query action with
	 * @param array $case
	 * @return array
	 */
	protected function getQueryParams($case) {
		return $this->getQuery($case);
	}
	
	protected function createRecord($case) {
		$dataForDB = $this->getDataForDB($case);
		$this->coll->insert($dataForDB);
	}
	
	protected function preRun($case) {
		// Get the record to remove from the db
		$query = $this->getQuery($case);
		
		// Check if it exists.
		$this->toRemove = $this->coll->find($query)->current();
		
		// If the record exists execute custom logic
		if(!$this->toRemove->isEmpty()) {
			return $this->onRecordExists($case);
		}
		
		$this->createRecord($case);
		return true;
	}
	
	protected function postRun($case) {
		// Check that the record does not exist.
		$queryAction = $this->getQueryAction($case);
		
		$queryParams = $this->getQueryParams($case);
		
		if(!$queryAction->parse($queryParams)) {
			$this->assertTrue(false, "Query action failed parsing " . $case['msg']);
			return false;
		}
		
		$result = $queryAction->execute();
		return $this->onQueryAction($result);
	}
}
