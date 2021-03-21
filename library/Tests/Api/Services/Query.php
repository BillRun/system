<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test class for the services API
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');
//require_once(APPLICATION_PATH . '/library/Billrun/ActionManagers/Services/Create.php');

class Tests_Api_Services_Query extends Tests_Api_Base_Query {

	public function __construct($testCases, $intenalTestInstance = null, $label = false) {		
		$collection = Billrun_Factory::db()->servicesCollection();
		$inputParameters = array('query');
		parent::__construct($collection, $testCases, $inputParameters, $intenalTestInstance, $label);
	}

	/**
	 * Get an instance of the action.
	 * @return Billrun_ActionManagers_APIAction
	 */
	protected function getAction($param = array()) {
		return new Billrun_ActionManagers_Services_Query($param);
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
		
		return $data;
	}

	/**
	 * Get the query 
	 * @return array query for the action.
	 */
	protected function getQuery($case) {
		$query = $case['query'];
		
		// Remove unnecessary fields
//		unset($query['to']);
//		unset($query['from']);
		unset($query['description']);
		
		return $query;
	}

}
