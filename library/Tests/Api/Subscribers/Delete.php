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

class Tests_Api_Subscribers_Delete extends Tests_Api_Base_Delete {

	public function __construct($testCases, $intenalTestInstance = null, $label = false) {		
		$collection = Billrun_Factory::db()->subscribersCollection();
		$inputParameters = array('query');
		parent::__construct($collection, $testCases, $inputParameters, $intenalTestInstance, $label);
	}
	
	protected function getAction($param = array()) {
		return new Billrun_ActionManagers_Subscribers_Delete();
	}

	protected function getQuery($case) {
		$query = $case['query'];
		
		// Remove unnecessary fields
		$query['to'] = new MongoDate(strtotime($query['to']));
		$query['from'] = new MongoDate(strtotime($query['from']));
		unset($query['description']);
		
		return $query;
	}

	protected function getDataForDB($case) {
		$data = $case['query'];
		
		foreach ($data as $key => &$value) {
			if(in_array($key, array('to', 'from'))) {
				$value = new MongoDate(strtotime($value));
			}
		}
		
		$type = $case['type'];
		$data['type'] = $type;
		
		return $data;
	}

	protected function getQueryAction($case) {
		return new Billrun_ActionManagers_Subscribers_Query();
	}

	protected function onQueryAction($results) {
		$error_code = Billrun_Util::getFieldVal($results['code'], null);
		if($results['status'] && $error_code === null) {
			return true;
		}
		
		$apiCode = Billrun_Util::getFieldVal($results['display']['code'], null);
		$assertResult = $this->assertEqual(1023, $apiCode, $this->current['msg']);
		if(!$assertResult) {
			return $this->onExecuteFailed($results);
		}
		return true;
	}

	protected function translateSingleCase($key, $value) {
		if(in_array($key, array("from", "to"))) {
//			return new MongoDate(strtotime($value));
		}
		return $value;
	}
}
