<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test case class for auto-renew
 *
 * @package         Tests
 * @subpackage      Auto-renew
 * @since           4.4
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

class Tests_Subscriberservice extends UnitTestCase {
	
    protected $tests = array(
            array('msg' => 'Invalid null name', 'valid' => false, 'options' => array('name' => null,  'to' => '2016-01-29 00:00:01', 'from' => '2016-02-10 00:00:00')),
            array('msg' => 'Invalid integer name', 'valid' => false, 'options' => array('name' => 100,  'to' => '2016-01-29 00:00:01', 'from' => '2016-02-10 00:00:00')),
            array('msg' => 'Invalid empty name', 'valid' => false, 'options' => array('name' => "",  'to' => '2016-01-29 00:00:01', 'from' => '2016-02-10 00:00:00')),
		
            array('msg' => 'Invalid null from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => '2016-01-29 00:00:01', 'from' => null)),
            array('msg' => 'Invalid integer from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => '2016-01-29 00:00:01', 'from' => 150)),
		    array('msg' => 'Invalid empty from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => '2016-01-29 00:00:01', 'from' => '')),
            array('msg' => 'Invalid non date from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => '2016-01-29 00:00:01', 'from' => 'This is not a date')),

		    array('msg' => 'Invalid null to', 'valid' => false, 'options' => array('name' => "Bla",  'to' => null, 'from' => '2016-02-10 00:00:00')),
            array('msg' => 'Invalid integer to', 'valid' => false, 'options' => array('name' => "Bla",  'to' => 150, 'from' => '2016-02-10 00:00:00')),
            array('msg' => 'Invalid empty to', 'valid' => false, 'options' => array('name' => "Bla",  'to' => '', 'from' => '2016-02-10 00:00:00')),
            array('msg' => 'Invalid non date to', 'valid' => false, 'options' => array('name' => "Bla",  'to' => 'This is not a date', 'from' => '2016-02-10 00:00:00')),
		
            array('msg' => 'Invalid non date to and from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => 'This is not a date', 'from' => 'This is not a date')),
            array('msg' => 'Invalid integer to and from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => 150, 'from' => 150)),
            array('msg' => 'Invalid null to and from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => null, 'from' => null)),
            array('msg' => 'Invalid empty to and from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => '', 'from' => '')),
		
            array('msg' => 'Invalid to before from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => '+1 day', 'from' => '+1 month')),
		
            array('msg' => 'valid to equals from', 'valid' => false, 'options' => array('name' => "Bla",  'to' => '+1 day', 'from' => '+1 day')),
		
            array('msg' => '1. Valid simple', 'valid' => true, 'options' => array('name' => "Bla",  'to' => '2016-01-29 00:00:01', 'from' => '2016-02-10 00:00:00')),
            array('msg' => '2. Valid simple', 'valid' => true, 'options' => array('name' => "Bla",  'to' => '2016-01-29 00:00:01', 'from' => '+1 month')),
            array('msg' => '3. Valid simple', 'valid' => true, 'options' => array('name' => "Bla",  'to' => '+1 year', 'from' => '2016-02-10 00:00:00')),
            array('msg' => '4. Valid simple', 'valid' => true, 'options' => array('name' => "Bla",  'to' => '+1 year', 'from' => '+1 month')),
    );

    function testCountMonths() {
		foreach ($this->tests as $key => $test) {
			$result = Billrun_Utils_Autorenew::countMonths(strtotime($test['date1']), strtotime($test['date2']));
			$this->assertEqual($result, $test['countMonths'], '[' . $key . '] %s');
		}
    }
	
    function testNextRenewDate() {
		foreach ($this->tests as $key => $test) {
			$result = Billrun_Utils_Autorenew::getNextRenewDate(strtotime($test['date1']), false, strtotime($test['specific_date']));
			$this->assertEqual($result, strtotime($test['next_renew_date']), '[' . $key . '] %s');
		}
    }

    function testRemain() {
		foreach ($this->tests as $key => $test) {
			$date1 = strtotime($test['date1']);
			$date2 = strtotime($test['date2']);
			$specific_time = strtotime($test['specific_date']);
			$months = Billrun_Utils_Autorenew::countMonths($date1, $date2);
			$doneMonths = Billrun_Utils_Autorenew::countMonths($date1, $specific_time);
			if ($date1 > $specific_time) {
				$doneMonths -= 1;
			}

			$remainingMonths = $months - $doneMonths;

			$this->assertEqual($remainingMonths, $test['remain'], '[' . $key . '] %s');
		}
    }
	
	protected function getBaseTime($to, $from) {
		$baseTime = time();
		if ($baseTime < $from) {
			$baseTime = $from;
		}

		if ($baseTime > $to) {
			$baseTime = $to;
		}

		return $baseTime;
	}


}
