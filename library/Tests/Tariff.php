<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for tariff logic
 *
 * @package         Tests
 * @subpackage      
 * @since           5.2
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

class Tests_Tariff extends UnitTestCase {
	protected $getAccessPriceTests = array(
		array("msg" => "Positive access price", "expected" => 10, "tariff" => array("access" => 10)),
		array("msg" => "Negative access price", "expected" => -10, "tariff" => array("access" => -10)),
		array("msg" => "Zero access price", "expected" => 0, "tariff" => array("access" => 0)),
		
		// Validation checks
		array("msg" => "Empty array", "expected" => 0, "tariff" => array()),
		array("msg" => "Null tariff array", "expected" => 0, "tariff" => null),
		array("msg" => "String tariff array", "expected" => 0, "tariff" => "Hello World"),
		array("msg" => "Integer tariff array", "expected" => 0, "tariff" => 979),
		
		// Edge cases
		array("msg" => "Access capital letters", "expected" => 0, "tariff" => array("ACCESS" => 10)),
		array("msg" => "Access mixed letters", "expected" => 0, "tariff" => array("Access" => 10)),
		
	);
	
	/**
	 * Test the get access price function
	 */
	function testGetAccessPrice() {
		foreach ($this->getAccessPriceTests as $test) {
			$tariff = $test['tariff'];
			$result = Billrun_Tariff_Util::getAccessPrice($tariff);
			$expected = $test['expected'];
			$this->assertEqual($result, $expected, $test['msg']);
		}
	}
}
