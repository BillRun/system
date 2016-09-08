<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class updating from the API
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

abstract class Tests_Api_Base_Update extends Tests_Api_Base_Delete {
	
	/**
	 * Current test case
	 * @var array
	 */
	protected $current;
	
	/**
	 * Get the update query for the record after being updated.
	 * @param array $case - Current test case.
	 * @return array Update query.
	 */
	protected abstract function getUpdateQuery($case);
	
	/**
	 * Get the data from the query action results.
	 * @param array $results - Return array from the API action execute function.
	 * @return boolean - True if continue with executing the test case, false 
	 * to terminate running the test case.
	 */
	protected abstract function getResultData($results);
	
	protected function getClearCaseQuery($case) {
		return $this->getUpdateQuery($case);
	}
	
	/**
	 * Get the expaction for the current test case
	 * @return array Expected data after the update.
	 */
	protected function getExpectation() {
		return $this->getUpdateQuery($this->current);
	}
	
	/**
	 * Function to run on the results of the query action execute function.
	 * @param type $results
	 * @return type
	 */
	protected function onQueryAction($results) {
		$resultData = $this->getResultData($results);
		$expectation = $this->getExpectation();
		return $this->assert($expectation, $resultData);
	}
	
	protected function preRun($case) {
		if(!parent::preRun($case)) {
			return false;
		}
		$this->current = $case;
		return true;
	}
}
