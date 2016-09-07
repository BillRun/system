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

abstract class Tests_Api_Base_Query extends Tests_Api_Base_Action {
	
	/**
	 * Current test case
	 * @var array
	 */
	protected $current;
	
	protected function preRun($case) {
		$this->current = $case;
	}
	
	protected function checkExpected($result) {
		$expected = $this->current['expected'];
		$message = $this->current['msg'];
		return $this->assertEqual($result, $expected, $message);
	}
	
	protected function handleResult($result) {
		if(!parent::handleResult($result)) {
			return false;
		}
		
		return $this->checkExpected($result);
	}
}
