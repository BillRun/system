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

class Tests_Api_Subscribers extends UnitTestCase {
	
	// TODO: These test cases are faulty
    protected $createTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'type'=>'subscriber', 'subscriber' => array("plan" => "PLAN-X3", "address" => "Somewhere", "aid" => "109", "firstname" => "Bob","lastname" => "Marv", "from" => "-1 month","to" => "+1 month", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory future', 'type'=>'subscriber','subscriber' => array("plan" => "PLAN-X3", "address" => "Somewhere", "aid" => "109", "firstname" => "Bob","lastname" => "Marv", "from" => "+1 month","to" => "+1 year", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory past', 'type'=>'subscriber','subscriber' => array("plan" => "PLAN-X3", "address" => "Somewhere", "aid" => "109", "firstname" => "Bob","lastname" => "Marv", "from" => "-1 year","to" => "-1 month", "sid" => 99887711)),
    );
		
	protected $deleteTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'type'=>'subscriber','query' => array("plan" => "PLAN-X1", "address" => "There", "aid" => "109", "firstname" => "Gog","lastname" => "Spiegel", "from" => "-1 month","to" => "+1 month", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory future', 'type'=>'subscriber','query' => array("plan" => "PLAN-X2", "address" => "Right there", "aid" => "109", "firstname" => "George","lastname" => "Plarv", "from" => "+1 month","to" => "+1 year", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory past', 'type'=>'subscriber', 'query' => array("plan" => "PLAN-X3", "address" => "Left There", "aid" => "109", "firstname" => "Geff","lastname" => "Aharon", "from" => "-1 year","to" => "-1 month", "sid" => 99887711)),
	);
	
	protected $queryTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'type'=>'subscriber', 'query' => array("plan" => "PLAN-X1", "address" => "Somewhere", "aid" => "109", "firstname" => "Bob","lastname" => "Boom", "from" => "-1 month","to" => "+1 month", "sid" => "99887711")),
		array('valid' => true, 'msg' => 'Only mendatory future', 'type'=>'subscriber', 'query' => array("plan" => "PLAN-X2", "address" => "Somewhere", "aid" => "109", "firstname" => "Bob","lastname" => "Ding","from" => "+1 month","to" => "+1 year", "sid" => "99887711")),
		array('valid' => true, 'msg' => 'Only mendatory past', 'type'=>'subscriber', 'query' => array("plan" => "PLAN-X3", "address" => "Somewhere", "aid" => "109", "firstname" => "Bob","lastname" => "Shlag", "from" => "-1 year","to" => "-1 month", "sid" => "99887711")),
	);
	
	protected $updateTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active updating from',  'type'=>'subscriber', 'update' => array("firstname" => "Updated"), 'query' => array("plan" => "PLAN-X3", "address" => "Ohio", "aid" => "109", "firstname" => "Bob","lastname" => "Shlag", "from" => "-1 month","to" => "+1 month", "sid" => "99887712")),
		array('valid' => true, 'msg' => 'Only mendatory future updating from', 'type'=>'subscriber', 'update' => array("lastname" => "Updated", "firstname" => "Updated"), 'query' => array("plan" => "PLAN-X3", "address" => "Ohio", "aid" => "109", "firstname" => "Bob","lastname" => "Shlag", "from" => "+1 month","to" => "+1 year", "sid" => "99887713")),
		array('valid' => true, 'msg' => 'Only mendatory past updating from', 'type'=>'subscriber', 'update' => array("lastname" => "Updated", "from" => "-2 years"), 'query' => array("plan" => "PLAN-X3", "address" => "Ohio", "aid" => "109", "firstname" => "Bob","lastname" => "Shlag","from" => "-1 year","to" => "-1 month", "sid" => "99887714")),
	);
	
	function testInit() {
		// Initialize the config file.
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/subscribers/conf.ini');
	}
	
    function testCreate() {
//		$create = new Tests_Api_Subscribers_Create($this->createTests, $this);
//		$create->run();
    }
	
	function testDelete() {
//		$delete = new Tests_Api_Subscribers_Delete($this->deleteTests, $this);
//		$delete->run();
	}
	
	function testQuery() {
//		$query = new Tests_Api_Subscribers_Query($this->queryTests, $this);
//		$query->run();
	}
	
	function testUpdate() {
		$update = new Tests_Api_Subscribers_Update($this->updateTests, $this);
		$update->run();
	}
}
