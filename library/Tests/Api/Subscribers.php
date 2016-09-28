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

class Tests_Api_Subscribers extends UnitTestCase {
	
	// TODO: These test cases are faulty
    protected $createTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'subscriber' => array("from" => "-1 month","to" => "+1 month", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory future', 'subscriber' => array("from" => "+1 month","to" => "+1 year", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory past', 'subscriber' => array("from" => "-1 year","to" => "-1 month", "sid" => 99887711)),
    );
		
	protected $deleteTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'query' => array("from" => "-1 month","to" => "+1 month", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory future', 'query' => array("from" => "+1 month","to" => "+1 year", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory past', 'query' => array("from" => "-1 year","to" => "-1 month", "sid" => 99887711)),
	);
	
	protected $queryTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active', 'query' => array("from" => "-1 month","to" => "+1 month", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory future', 'query' => array("from" => "+1 month","to" => "+1 year", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory past', 'query' => array("from" => "-1 year","to" => "-1 month", "sid" => 99887711)),
	);
	
	protected $updateTests = array(
		array('valid' => true, 'msg' => 'Only mendatory active updating from',  'update' => array("from" => "-2 months"), 'query' => array("from" => "-1 month","to" => "+1 month", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory future updating from', 'update' => array("from" => "+2 months"), 'query' => array("from" => "+1 month","to" => "+1 year", "sid" => 99887711)),
		array('valid' => true, 'msg' => 'Only mendatory past updating from', 'update' => array("from" => "-2 years"), 'query' => array("from" => "-1 year","to" => "-1 month", "sid" => 99887711)),
	);
	
    function testCreate() {
		$create = new Tests_Api_Subscribers_Create($this->createTests, $this);
		$create->run();
    }
	
	function testDelete() {
		$delete = new Tests_Api_Subscribers_Delete($this->deleteTests, $this);
		$delete->run();
	}
	
	function testQuery() {
		$query = new Tests_Api_Subscribers_Query($this->queryTests, $this);
		$query->run();
	}
	
	function testUpdate() {
		$update = new Tests_Api_Subscribers_Update($this->updateTests, $this);
		$update->run();
	}
}
