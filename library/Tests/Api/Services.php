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
	
    protected $tests = array(
		array('valid' => true, 'msg' => 'Valid Rate Base', 'rate' => array("type" => "rate","key" => "012_OMAN_MOBILE", "rates" => array("calls" => "BASE"))),
		array('valid' => true, 'msg' => 'Valid Rate No Base', 'rate' => array("type" => "rate","key" => "012_OMAN_MOBILE", "rates" => array("calls" => "Bamba"))),
		array('valid' => true, 'msg' => 'Valid Rate no rates', 'rate' => array("type" => "rate","key" => "012_OMAN_MOBILE")),
		array('valid' => true, 'msg' => 'Valid Rate empty rates', 'rate' => array("type" => "rate","key" => "012_OMAN_MOBILE", "rates" => array())),
		array('valid' => false, 'msg' => 'Invalid Rate-Unrated', 'rate' => array("type" => "service","key" => "012_OMAN_MOBILE", "rates" => array("UNRATED" => array()))),
		array('valid' => false, 'msg' => 'Invalid Rate-Service', 'rate' => array("type" => "service","key" => "012_OMAN_MOBILE")) 
    );
	protected static function getMethod($name) {
		$class = new ReflectionClass('Billrun_Calculator_Rate_Usage');
		$method = $class->getMethod($name);
		$method->setAccessible(true);
		return $method;	
	}
	
    function testUsageRates() {
		phpinfo();
    }
}
