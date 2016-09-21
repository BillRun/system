<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapping a unit test instance to report through
 *
 * @package         Tests
 * @subpackage      API
 * @since           5.1
 */
abstract class Tests_TestWrapper extends UnitTestCase {
	/**
	 * The unit test instance to report with
	 * @var UnitTestCase
	 */
	private $unitTestInstance;
	
	public function __construct($intenalTestInstance, $label = false) {
		parent::__construct($label);
		
		$this->unitTestInstance = $intenalTestInstance;
	}
	
	/**
	 * Abstract function to run on each assert.
	 * @param boolean $result - The assert result.
	 */
	protected abstract function onAssert($result);
	
	public function assert($expectation, $compare, $message = '%s') {
		$assertResult = $this->unitTestInstance->assert($expectation, $compare, $message);
		$this->onAssert($assertResult);
		return $assertResult;
	}
	
	/**
     *    Sends a formatted dump of a variable to the
     *    test suite for those emergency debugging
     *    situations.
     *    @param mixed $variable    Variable to display.
     *    @param string $message    Message to display.
     *    @return mixed             The original variable.
     *    @access public
     */
    public function dump($variable, $message = false) {
		$this->unitTestInstance->dump($variable, $message);
	}
}
