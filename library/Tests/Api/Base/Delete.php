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
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

abstract class Tests_Api_Base_Delete extends Tests_Api_Base_Action {
	
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
		return $case;
	}
	
		
	/**
	 * Handle the output message result.
	 * @param type $result
	 * @return boolean
	 */
	protected function handleResult($result) {
		if(!parent::handleResult($result)) {
			return false;
		}

//		if(!isset($result['details']) || !isset($result['details']['to'])) {
//			$this->assertTrue(false, "Invalid API action results " . $this->current['msg']);
//			$this->dump($result);
//			return false;
//		}
//		
//		// Deleting the record updates the to field, take the new 'to' field.
//		$this->current['query']['to'] = $result['details']['to'];
		return true;
	}
	
	protected function postRun($case) {
		// Check that the record does not exist.
		$queryAction = $this->getQueryAction($this->current);
		
		$queryParams = $this->getQueryParams($this->current);
		$queryInput = $this->buildInput($queryParams);
		
		if(!$queryAction->parse($queryInput)) {
			$this->assertTrue(false, "Query action failed parsing " . $case['msg']);
			return false;
		}
		
		try {
			$result = $queryAction->execute();
		} catch (Billrun_Exceptions_Base $e) {
			$result = json_decode($e->output(), 1);
		}
		$queryActionResult = $this->onQueryAction($result);
		
		// Clear the new test case
		return $this->clearCase($this->current) && $queryActionResult;
	}
}
