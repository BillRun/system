<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for rate usage
 *
 * @package         Tests
 * @subpackage      Auto-renew
 * @since           4.4
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

abstract class Tests_Api_Base_Action extends Tests_TestWrapper {

	/**
	 * Array of tests.
	 * @var array
	 */
	protected $cases;
	
	/**
	 * Array of the API parameter names
	 * @var array
	 */
	protected $parameters;
	
	/**
	 * Create a new instance of the API Action test object.
	 * @param type $testCases
	 * @param type $inputParameters
	 * @param type $internalTestInstance
	 * @param type $label
	 */
	public function __construct($testCases, $inputParameters, $internalTestInstance = null, $label = false) {
		if($internalTestInstance == null) {
			$internalTestInstance = $this;
		}
		parent::__construct($internalTestInstance, $label);
		
		$this->parameters = $inputParameters;
		$this->cases = $testCases;
	}
	
	/**
	 * Get an instance of the action.
	 */
	protected abstract function getAction($param);
	
	/**
	 * Run the internal logic
	 */
    public function run() {
		$action = $this->getAction();

		// Go through the cases.
		foreach ($this->cases as $case) {
			$this->runCase($action, $case);
		}
    }
	
	/**
	 * Run a case on an action.
	 * @param type $action
	 * @param type $case
	 * @return type
	 */
	protected function runCase($action, $case) {
		if(!$this->preRun($case)) {
			return;
		}
		$actionTest = $this->buildInput($case);
		if(!$this->internalRun($action, $actionTest)) {
			$this->assertFalse($case['valid'], "Faild parsing " . $case['msg']);
			// Break the logic
			return;
		}

		if(!$this->postRun($case)) {
			return;
		}
		
		$this->assertTrue($case['valid'], $case['msg']);
	}
	
	protected abstract function postRun($case);
	protected abstract function preRun($case);
	
	/**
	 * Builds the input for the action.
	 * @param array $test
	 * @return \Billrun_AnObj
	 */
	protected function buildInput($test) {
		$escaped = array();
		foreach ($test as $key => $value) {
			if(in_array($key, $this->parameters)) {
				$value = $this->escape($value);
			}
			$escaped[$key] = $value;
		}
		
		return new Billrun_AnObj($escaped);
	}
	
	/**
	 * Escape function to map to
	 * @param type $value
	 * @return type
	 */
	protected function escape($value) {
		return json_encode($value);
	}
	
	/**
	 * Run a single test logic
	 * @param Billrun_ActionManagers_Services_Action $action
	 * @param Billrun_AnObj $test
	 */
	protected function internalRun(Billrun_ActionManagers_IAPIAction $action, Billrun_AnObj $test) {
		// Test parsing.
		if(!$action->parse($test)) {
			return false;
		}
		
		$result = $action->execute();
		return $this->handleResult($result);
	}
	
	/**
	 * Function to run on failure of the execute function.
	 * @param type $result
	 */
	protected function onExecuteFailed($result) {
		$this->dump($result, "Execute failed");
	}
	
	/**
	 * Handle the output message result.
	 * @param type $result
	 * @return boolean
	 */
	protected function handleResult($result) {
		if(!$result['status']) {
			$this->onExecuteFailed($result);
			return false;
		}
		
		return true;
	}
}
