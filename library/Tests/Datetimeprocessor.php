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
require_once(APPLICATION_PATH . '/vendor/simpletest/simpletest/autorun.php');

class Tests_DatetimeProcessor extends UnitTestCase {
	
    protected $tests = array(
            array('msg' => 'Date without time with format1', 'valid' => true, 'expected' => '2009-02-15 15:16:17', 'row' => array('date' => '2009-02-15 15:16:17'), 'dateField' => 'date', 'dateFormat' => 'Y-m-d H:i:s', 'timeField' => null, 'timeFormat' => null),
            array('msg' => 'Date without time with format2', 'valid' => true, 'expected' => '2009-02-15 15:16:17', 'row' => array('date' => '2009-15-02 15:16:17'), 'dateField' => 'date', 'dateFormat' => 'Y-d-m H:i:s', 'timeField' => null, 'timeFormat' => null),
            array('msg' => 'Date without time with format3', 'valid' => true, 'expected' => '2009-02-15 15:16:17', 'row' => array('date' => '09-15-02 15:16:17'), 'dateField' => 'date', 'dateFormat' => 'y-d-m H:i:s', 'timeField' => null, 'timeFormat' => null),
            array('msg' => 'Date without time with format4', 'valid' => true, 'expected' => '2009-02-15 15:16:17', 'row' => array('date' => '15-Feb-2009 15:16:17'), 'dateField' => 'date', 'dateFormat' => 'j-M-Y H:i:s', 'timeField' => null, 'timeFormat' => null),
            array('msg' => 'Date without time without format1', 'valid' => true, 'expected' => '2009-02-15 15:16:17', 'row' => array('date' => '2009-02-15 15:16:17'), 'dateField' => 'date', 'dateFormat' => null, 'timeField' => null, 'timeFormat' => null),
            array('msg' => 'Date without time without format3', 'valid' => true, 'expected' => '2009-02-15 15:16:17', 'row' => array('date' => '09-02-15 15:16:17'), 'dateField' => 'date', 'dateFormat' => null, 'timeField' => null, 'timeFormat' => null),
			array('msg' => 'Date with time with 2 formats', 'valid' => true, 'expected' => '2009-02-15 15:16:17', 'row' => array('date' => '2009-02-15', 'time' => '15:16:17'), 'dateField' => 'date', 'dateFormat' => 'Y-m-d', 'timeField' => 'time', 'timeFormat' => 'H:i:s'),
			array('msg' => 'Date with time with only date format', 'valid' => false, 'expected' => null, 'row' => array('date' => '2009-02-15', 'time' => '15:16:17'), 'dateField' => 'date', 'dateFormat' => 'Y-m-d H:i:s', 'timeField' => 'time', 'timeFormat' => null),
			array('msg' => 'Date with time with only date format', 'valid' => false, 'expected' => null, 'row' => array('date' => '2009-02-15', 'time' => '15:16:17'), 'dateField' => 'date', 'dateFormat' => 'Y-m-d', 'timeField' => 'time', 'timeFormat' => null),
			array('msg' => 'Date with time without formats', 'valid' => true, 'expected' => '2009-02-15 15:16:17', 'row' => array('date' => '2009-02-15', 'time' => '15:16:17'), 'dateField' => 'date', 'dateFormat' => null, 'timeField' => 'time', 'timeFormat' => null),
    );

    function testValidate() {
		foreach ($this->tests as $test) {
			$row = $test['row'];
			$dateField = $test['dateField'];
			$dateFormat = $test['dateFormat'];
			$timeField = $test['timeField'];
			$timeFormat = $test['timeFormat'];
			$datetime = Billrun_Processor_Util::getRowDateTime($row, $dateField, $dateFormat, $timeField, $timeFormat);
			if (!$test['valid']) {
				$this->assertEqual($datetime, false, $test['msg'] . '. Expected: false. Got: ' . $datetime);
			} else {
				$datetime = $datetime->format('Y-m-d H:i:s');
				$this->assertEqual($datetime, $test['expected'], $test['msg'] . '. Expected: ' . $test['expected'] . '. Got: ' . $datetime);
			}
		}
    }
}
