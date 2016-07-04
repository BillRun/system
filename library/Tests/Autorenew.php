<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Test controller class
 *
 * @package         Tests
 * @subpackage      Auto-renew
 * @since           4.4
 */
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');

class Tests_Autorenew extends UnitTestCase {
	
	protected $tests = array(
			array('date1' => '2016-02-10 00:00:00', 'date2' => '2016-03-09 23:59:59', 'countMonths' => 1,  'specific_date' => '2016-01-29 00:00:00', 'next_renew_date' => '2016-02-10 00:00:00'),
			array('date1' => '2016-02-10 00:00:00', 'date2' => '2016-03-09 23:59:59', 'countMonths' => 1,  'specific_date' => '2016-02-29 00:00:00', 'next_renew_date' => '2016-03-10 00:00:00'),
			array('date1' => '2014-01-31 00:00:00', 'date2' => '2016-12-30 23:59:59', 'countMonths' => 35, 'specific_date' => '2016-04-08 00:00:00', 'next_renew_date' => '2016-04-30 00:00:00'),
			array('date1' => '2014-01-31 00:00:00', 'date2' => '2016-12-30 23:59:59', 'countMonths' => 35, 'specific_date' => '2016-05-08 00:00:00', 'next_renew_date' => '2016-05-31 00:00:00'),
			array('date1' => '2014-01-31 00:00:00', 'date2' => '2016-12-30 23:59:59', 'countMonths' => 35, 'specific_date' => '2016-04-30 00:00:00', 'next_renew_date' => '2016-05-31 00:00:00'),
			array('date1' => '2014-01-31 00:00:00', 'date2' => '2016-12-30 23:59:59', 'countMonths' => 35, 'specific_date' => '2016-05-30 00:00:00', 'next_renew_date' => '2016-05-31 00:00:00'),
			array('date1' => '2015-04-30 00:00:00', 'date2' => '2017-03-29 23:59:59', 'countMonths' => 23, 'specific_date' => '2016-05-31 00:00:00', 'next_renew_date' => '2016-06-30 00:00:00'),
			array('date1' => '2015-04-30 00:00:00', 'date2' => '2017-03-29 23:59:59', 'countMonths' => 23, 'specific_date' => '2016-05-30 00:00:00', 'next_renew_date' => '2016-06-30 00:00:00'),
			array('date1' => '2015-04-30 00:00:00', 'date2' => '2017-03-29 23:59:59', 'countMonths' => 23, 'specific_date' => '2016-05-29 00:00:00', 'next_renew_date' => '2016-05-30 00:00:00'),
			array('date1' => '2015-02-28 00:00:00', 'date2' => '2016-02-27 23:59:59', 'countMonths' => 12, 'specific_date' => '2016-03-28 00:00:00', 'next_renew_date' => '2016-04-28 00:00:00'),
			array('date1' => '2015-02-28 00:00:00', 'date2' => '2016-02-27 23:59:59', 'countMonths' => 12, 'specific_date' => '2016-03-27 00:00:00', 'next_renew_date' => '2016-03-28 00:00:00'),
			array('date1' => '2016-02-29 00:00:00', 'date2' => '2017-02-28 23:59:59', 'countMonths' => 13, 'specific_date' => '2016-03-28 00:00:00', 'next_renew_date' => '2016-03-29 00:00:00'),
			array('date1' => '2016-02-29 00:00:00', 'date2' => '2017-02-27 23:59:59', 'countMonths' => 12, 'specific_date' => '2016-03-28 00:00:00', 'next_renew_date' => '2016-03-29 00:00:00'),
			array('date1' => '2016-02-28 00:00:00', 'date2' => '2017-02-27 23:59:59', 'countMonths' => 12, 'specific_date' => '2016-03-28 00:00:00', 'next_renew_date' => '2016-04-28 00:00:00'),
			array('date1' => '2016-07-31 00:00:00', 'date2' => '2017-07-30 23:59:59', 'countMonths' => 12, 'specific_date' => '2016-07-05 00:00:00', 'next_renew_date' => '2016-07-31 00:00:00'),
			array('date1' => '2016-07-31 00:00:00', 'date2' => '2017-06-30 23:59:59', 'countMonths' => 12, 'specific_date' => '2016-07-05 00:00:00', 'next_renew_date' => '2016-07-31 00:00:00'),
			array('date1' => '2016-07-05 00:00:00', 'date2' => '2017-06-04 23:59:59', 'countMonths' => 11, 'specific_date' => '2016-09-05 00:00:01', 'next_renew_date' => '2016-10-05 00:00:00'),
            array('date1' => '2016-02-10 00:00:00', 'date2' => '2016-03-09 23:59:59', 'countMonths' => 1, 'specific_date' => '2016-02-29 00:00:00', 'next_renew_date' => '2016-03-10 00:00:00'),
            array('date1' => '2016-02-10 00:00:00', 'date2' => '2016-04-09 23:59:59', 'countMonths' => 2, 'specific_date' => '2016-02-29 00:00:00', 'next_renew_date' => '2016-03-10 00:00:00'),
            array('date1' => '2014-01-31 00:00:00', 'date2' => '2016-12-30 23:59:59', 'countMonths' => 35, 'specific_date' => '2016-04-30 00:00:00', 'next_renew_date' => '2016-05-31 00:00:00'),
            array('date1' => '2015-07-30 00:00:00', 'date2' => '2017-06-29 23:59:59', 'countMonths' => 23, 'specific_date' => '2017-02-28 00:00:00', 'next_renew_date' => '2017-03-30 00:00:00'),
	);

    function testCountMonths() {
		foreach ($this->tests as $test) {
			$result = Billrun_Utils_Autorenew::countMonths(strtotime($test['date1']), strtotime($test['date2']));
			$this->assertEqual($result, $test['countMonths']);
		}
    }
	
    function testNextRenewDate() {
		foreach ($this->tests as $test) {
//			$result = Billrun_Utils_Autorenew::countMonths(strtotime($test['date1']), strtotime($test['date2']));
			$result = Billrun_Utils_Autorenew::getNextRenewDate(strtotime($test['date1']), false, strtotime($test['specific_date']));
			$this->assertEqual($result, strtotime($test['next_renew_date']));
		}
    }


}
