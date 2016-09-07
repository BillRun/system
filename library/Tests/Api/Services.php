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

class Tests_Api_Services extends UnitTestCase {
	
    protected $createTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'service' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory future', 'service' => array("from" => "+1 month","to" => "+1 year", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory past', 'service' => array("from" => "-1 year","to" => "-1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		
		array('valid' => true, 'msg' => 'With include', 
			  'service' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test", 
				'include' => array("value" => -20,
								   'period' => array(
										"unit" => "month",
										"duration" => 1
								),
				"pp_includes_name" => "Monthly Bonus",
				"pp_includes_external_id" => "9"
		))),
		array('valid' => true, 'msg' => 'With include core balance', 
			  'service' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test", 
				'include' => array("value" => -20,
								   'period' => array(
										"unit" => "month",
										"duration" => 1
								),
				"pp_includes_name" => "CORE BALANCE",
				"pp_includes_external_id" => "1"
		))),

    );
		
    function testCreate() {
		$tester = new Tests_Api_Services_Create($this->createTests, $this);
		$tester->run();
    }
}
