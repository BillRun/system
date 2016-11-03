<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test class for the subscribers API
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');
//require_once(APPLICATION_PATH . '/library/Billrun/ActionManagers/Services/Create.php');

class Tests_Api_Subscribers_Update extends Tests_Api_Base_Update {

	public function __construct($testCases, $intenalTestInstance = null, $label = false) {		
		$collection = Billrun_Factory::db()->subscribersCollection();
		$inputParameters = array('query', 'update');
		parent::__construct($collection, $testCases, $inputParameters, $intenalTestInstance, $label);
	}

	protected function getAction($param = array()) {
		return new Billrun_ActionManagers_Subscribers_Update();
	}

	protected function getDataForDB($case) {
		$query = $case['query'];
		$update = $case['update'];
		
		$data = array_merge($query, $update);
		
		return $data;
	}
	
	protected function getQuery($case) {
		$data = $case['query'];
		unset($data['description']);
		return $data;
	}

	protected function getQueryAction($case) {
		return new Billrun_ActionManagers_Subscribers_Query();
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
		$data = $this->getDataForDB($case);
//		unset($data['description']);
		return $data;
	}

	protected function preRun($case) {
		if(!parent::preRun($case)) {
			Billrun_Factory::log("Failed: " . print_r($this->current,1));
			return false;
		}
		
		// Get the query again.
		$query = $this->getQuery($case);
		
		// Check if it exists.
		$created = $this->coll->find($query)->current();
		
		if($created->isEmpty()) {
			return false;
		}
		
		// Get the ID.
		$id = $created->getRawData()['_id'];
		
		// Put the id to our case.
		$this->current['query']['_id'] = $id;
		return true;
	}
}
