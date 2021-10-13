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
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');
//require_once(APPLICATION_PATH . '/library/Billrun/ActionManagers/Services/Create.php');

class Tests_Api_Subscribers_Create extends Tests_Api_Base_Create {

	public function __construct($testCases, $intenalTestInstance = null, $label = false) {		
		$collection = Billrun_Factory::db()->subscribersCollection();
		$inputParameters = array('subscriber');
		parent::__construct($collection, $testCases, $inputParameters, $intenalTestInstance, $label);
	}
	
	protected function getAction($param = array()) {
		return new Billrun_ActionManagers_Subscribers_Create();
	}

	protected function getQuery($case) {
		$query = $case['subscriber'];
		
		// Remove unnecessary fields
//		unset($query['to']);
//		unset($query['from']);
		unset($query['description']);
		return $query;
	}

	protected function getDataForDB($case) {
		$data = $case['subscriber'];
		$type = $case['type'];
		$data['type'] = $type;
		
		// Translate the dates.
		
		return $data;
	}

	protected function createRecord($case) {
		parent::createRecord($case);
		
		$dataForDB = $this->getDataForDB($case);
		$cloned = array_merge($dataForDB);
		$cloned['type'] = 'account';
		$cloned['to'] = new MongoDate(strtotime('+1 year'));
		$cloned['from'] = new MongoDate(strtotime('-1 year'));
		$cloned['test'] = 1;
		$this->coll->insert($cloned);
	}
	
	protected function getClearCaseQuery($case) {
		$query = parent::getClearCaseQuery($case);
		unset($query['to']);
		unset($query['from']);
		unset($query['sid']);
		return $query;
	}
	
	protected function clearCase($case) {
		if(!parent::clearCase($case)) {
			return false;
		}
		
		// Remove the account case as well.
		$query = $this->getClearCaseQuery($case);
		$query['type'] = 'account';
		$removed = $this->coll->remove($query);
		return $removed > 0;
	}
	
}
