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
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

class Tests_Api_Services extends UnitTestCase {
	
    protected $createTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'query' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory future', 'query' => array("from" => "+1 month","to" => "+1 year", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory past', 'query' => array("from" => "-1 year","to" => "-1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		
		array('valid' => true, 'msg' => 'With include', 
			  'query' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test", 
				'include' => array("value" => -20,
								   'period' => array(
										"unit" => "month",
										"duration" => 1
								),
				"pp_includes_name" => "Monthly Bonus",
				"pp_includes_external_id" => "9"
		))),
		array('valid' => true, 'msg' => 'With include core balance', 
			  'query' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test", 
				'include' => array("value" => -20,
								   'period' => array(
										"unit" => "month",
										"duration" => 1
								),
				"pp_includes_name" => "CORE BALANCE",
				"pp_includes_external_id" => "1"
		))),

    );
		
	protected $deleteTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'query' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory future', 'query' => array("from" => "+1 month","to" => "+1 year", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory past', 'query' => array("from" => "-1 year","to" => "-1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
	);
	
	protected $queryTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'query' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory future', 'query' => array("from" => "+1 month","to" => "+1 year", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory past', 'query' => array("from" => "-1 year","to" => "-1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
	);
	
	protected $updateTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active updating from',  'update' => array("from" => "-2 months"), 'query' => array("from" => "-1 month","to" => "+1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory future updating from', 'update' => array("from" => "+2 months"), 'query' => array("from" => "+1 month","to" => "+1 year", "name" => "TestService", "price" => 100, "description" => "This is a test")),
		array('valid' => true, 'msg' => 'Only mendatory past updating from', 'update' => array("from" => "-2 years"), 'query' => array("from" => "-1 year","to" => "-1 month", "name" => "TestService", "price" => 100, "description" => "This is a test")),
	);
	
	function testInit() {
		// Initialize the config file.
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/services/conf.ini');
	}
	
    function testCreate() {
		$create = new Tests_Api_Services_Create($this->createTests, $this);
		$create->run();
    }
	
	function testDelete() {
		$delete = new Tests_Api_Services_Delete($this->deleteTests, $this);
		$delete->run();
	}
	
	function testQuery() {
		$query = new Tests_Api_Services_Query($this->queryTests, $this);
		$query->run();
	}
	
	function testUpdate() {
		$update = new Tests_Api_Services_Update($this->updateTests, $this);
		$update->run();
	}
}
