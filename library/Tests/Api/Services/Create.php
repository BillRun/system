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

class Tests_Api_Services_Create extends Tests_Api_Base_Create {

	public function __construct($testCases, $intenalTestInstance = null, $label = false) {		
		$collection = Billrun_Factory::db()->servicesCollection();
		$inputParameters = array('query');
		parent::__construct($collection, $testCases, $inputParameters, $intenalTestInstance, $label);
	}
	
	protected function getAction($param = array()) {
		return new Billrun_ActionManagers_Services_Create($param);
	}

	protected function getQuery($case) {
		$query = $case['query'];
		
		// Remove unnecessary fields
//		unset($query['to']);
//		unset($query['from']);
		unset($query['description']);
		
		return $query;
	}

	protected function getDataForDB($case) {
		$data = $case['query'];
		
		// Translate the dates.
		
		return $data;
	}

}
