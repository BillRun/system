<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class creating from the API
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

abstract class Tests_Api_Base_Create extends Tests_Api_Base_Action {

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $coll;
	
	/**
	 * Get the query 
	 * @return array query for the action.
	 */
	protected abstract function getQuery($case);
	
	/**
	 * 
	 * @param type $collection
	 * @param type $testCases
	 * @param type $inputParameters
	 * @param type $internalTestInstance
	 * @param type $label
	 */
	public function __construct($collection, $testCases, $inputParameters, $internalTestInstance = null, $label = false) {
		parent::__construct($testCases, $inputParameters, $internalTestInstance, $label);
		
		$this->coll = $collection;
	}
	
	/**
	 * Post run logic
	 * @return boolean true if successful.
	 */
	protected function postRun($case) {
		// Check if it exists.
		$query = $this->getQuery($case);
		
		$removed = $this->coll->remove($query);
		
		if($removed < 1) {
			$this->assertFalse($case['valid'], "Failed to create in DB " . $case['msg']);
			return false;
		}
		
		return true;
	}
	
	/**
	 * 
	 */
	protected function preRun($case) {return true;}
}
