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

class Tests_Api_Services_Update extends Tests_Api_Base_Update {

	public function __construct($testCases, $intenalTestInstance = null, $label = false) {		
		$collection = Billrun_Factory::db()->servicesCollection();
		$inputParameters = array('query', 'update');
		parent::__construct($collection, $testCases, $inputParameters, $intenalTestInstance, $label);
	}

	protected function getAction($param = array()) {
		return new Billrun_ActionManagers_Services_Update($param);
	}

	protected function getDataForDB($case, $order = true) {
		$query = $case['query'];
		$update = $case['update'];
		
		if($order) {
			$data = array_merge($update, $query);
		} else {
			$data = array_merge($query, $update);			
		}
		
		return $data;
	}
	
	protected function getQuery($case) {
		$data = $case['query'];
		unset($data['description']);
		return $data;
	}

	protected function getQueryAction($case) {
		return new Billrun_ActionManagers_Services_Query();
	}

	protected function getResultData($results) {
		return $this->extractFromResults($results);
	}

	/**
	 * Get the update query for the record after being updated.
	 * @param array $case - Current test case.
	 * @return array Update query.
	 */
	protected function getUpdateQuery($case) {
		$data = $this->getDataForDB($case, 0);
//		unset($data['description']);
		return $data;
	}

}
