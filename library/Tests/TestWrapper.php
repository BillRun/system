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
class Tests_TestWrapper extends UnitTestCase {
	/**
	 * The unit test instance to report with
	 * @var UnitTestCase
	 */
	private $unitTestInstance;
	
	public function __construct($intenalTestInstance, $label = false) {
		parent::__construct($label);
		
		$this->unitTestInstance = $intenalTestInstance;
	}
	
	public function assert($expectation, $compare, $message = '%s') {
		$this->unitTestInstance->assert($expectation, $compare, $message);
	}
}
