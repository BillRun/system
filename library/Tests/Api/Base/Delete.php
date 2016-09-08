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
	
	protected function postRun($case) {
		// Check that the record does not exist.
		$queryAction = $this->getQueryAction($case);
		
		$queryParams = $this->getQueryParams($case);
		$queryInput = $this->buildInput($queryParams);
		
		if(!$queryAction->parse($queryInput)) {
			$this->assertTrue(false, "Query action failed parsing " . $case['msg']);
			return false;
		}
		
		$result = $queryAction->execute();
		$queryActionResult = $this->onQueryAction($result);
		
		// Clear the new test case
		return $this->clearCase($case) && $queryActionResult;
	}
}
