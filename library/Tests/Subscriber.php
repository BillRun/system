<?php

/**
 * @package         Tests
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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

class Tests_Subscriber extends UnitTestCase {
	
	protected $subscriber;
	
    protected $getFlatEntriesTests = array(
            array('msg' => 'Tariff after end', 'expected' => 0,'tariff' => array('price' => 1, 'to' => 110, 'from' => 100), 'start' => 15, 'end' => 50), //00
            array('msg' => 'Tariff before start', 'expected' => 0,'tariff' => array('price' => 1, 'to' => 10, 'from' => 1), 'start' => 15, 'end' => 50), //00
            array('msg' => 'Tariff invalid dates', 'expected' => 0,'tariff' => array('price' => 1, 'to' => 7, 'from' => 100), 'start' => 15, 'end' => 50), //00
            array('msg' => 'Offset invalid dates', 'expected' => 0,'tariff' => array('price' => 1, 'to' => 100, 'from' => 7), 'start' => 55, 'end' => 50), //00
		
            array('msg' => '1.Valid simple tariff', 'expected' => 40,'tariff' => array('price' => 1, 'from' => 0, 'to' => 100), 'start' => 10, 'end' => 50), //00
            array('msg' => '2.Valid simple tariff', 'expected' => 30,'tariff' => array('price' => 1, 'from' => 0, 'to' => 100), 'start' => 20, 'end' => 50), //00
            array('msg' => '3.Valid simple tariff', 'expected' => 20,'tariff' => array('price' => 1, 'from' => 0, 'to' => 100), 'start' => 30, 'end' => 50), //00
            array('msg' => '4.Valid simple tariff', 'expected' => 10,'tariff' => array('price' => 1, 'from' => 0, 'to' => 100), 'start' => 40, 'end' => 50), //00
            array('msg' => '5.Valid simple tariff', 'expected' => 5,'tariff' => array('price' => 1, 'from' => 0, 'to' => 100), 'start' => 45, 'end' => 50), //00
            array('msg' => '6.Valid simple tariff', 'expected' => 1,'tariff' => array('price' => 1, 'from' => 0, 'to' => 100), 'start' => 49, 'end' => 50), //00
		
            array('msg' => '1.Valid int price tariff', 'expected' => 80,'tariff' => array('price' => 2, 'from' => 0, 'to' => 100), 'start' => 10, 'end' => 50), //00
            array('msg' => '2.Valid int price tariff', 'expected' => 60,'tariff' => array('price' => 2, 'from' => 0, 'to' => 100), 'start' => 20, 'end' => 50), //00
            array('msg' => '3.Valid int price tariff', 'expected' => 40,'tariff' => array('price' => 2, 'from' => 0, 'to' => 100), 'start' => 30, 'end' => 50), //00
            array('msg' => '4.Valid int price tariff', 'expected' => 20,'tariff' => array('price' => 2, 'from' => 0, 'to' => 100), 'start' => 40, 'end' => 50), //00
            array('msg' => '5.Valid int price tariff', 'expected' => 10,'tariff' => array('price' => 2, 'from' => 0, 'to' => 100), 'start' => 45, 'end' => 50), //00
            array('msg' => '6.Valid int price tariff', 'expected' => 2,'tariff' => array('price' => 2, 'from' => 0, 'to' => 100), 'start' => 49, 'end' => 50), //00
            array('msg' => '7.Valid int price tariff', 'expected' => 120,'tariff' => array('price' => 3, 'from' => 0, 'to' => 100), 'start' => 10, 'end' => 50), //00
            array('msg' => '8.Valid int price tariff', 'expected' => 90,'tariff' => array('price' => 3, 'from' => 0, 'to' => 100), 'start' => 20, 'end' => 50), //00
            array('msg' => '9.Valid int price tariff', 'expected' => 60,'tariff' => array('price' => 3, 'from' => 0, 'to' => 100), 'start' => 30, 'end' => 50), //00
            array('msg' => '10.Valid int price tariff', 'expected' => 30,'tariff' => array('price' => 3, 'from' => 0, 'to' => 100), 'start' => 40, 'end' => 50), //00
            array('msg' => '11.Valid int price tariff', 'expected' => 15,'tariff' => array('price' => 3, 'from' => 0, 'to' => 100), 'start' => 45, 'end' => 50), //00
            array('msg' => '12.Valid int price tariff', 'expected' => 3,'tariff' => array('price' => 3, 'from' => 0, 'to' => 100), 'start' => 49, 'end' => 50), //00
		
            array('msg' => '1.Valid float price tariff', 'expected' => 10,'tariff' => array('price' => 0.25, 'from' => 0, 'to' => 100), 'start' => 10, 'end' => 50), //00
            array('msg' => '2.Valid float price tariff', 'expected' => 7.5,'tariff' => array('price' => 0.25, 'from' => 0, 'to' => 100), 'start' => 20, 'end' => 50), //00
            array('msg' => '3.Valid float price tariff', 'expected' => 5,'tariff' => array('price' => 0.25, 'from' => 0, 'to' => 100), 'start' => 30, 'end' => 50), //00
            array('msg' => '4.Valid float price tariff', 'expected' => 2.5,'tariff' => array('price' => 0.25, 'from' => 0, 'to' => 100), 'start' => 40, 'end' => 50), //00
            array('msg' => '5.Valid float price tariff', 'expected' => 1.25,'tariff' => array('price' => 0.25, 'from' => 0, 'to' => 100), 'start' => 45, 'end' => 50), //00
            array('msg' => '6.Valid float price tariff', 'expected' => 0.25,'tariff' => array('price' => 0.25, 'from' => 0, 'to' => 100), 'start' => 49, 'end' => 50), //00
            array('msg' => '7.Valid float price tariff', 'expected' => 60,'tariff' => array('price' => 1.5, 'from' => 0, 'to' => 100), 'start' => 10, 'end' => 50), //00
            array('msg' => '8.Valid float price tariff', 'expected' => 45,'tariff' => array('price' => 1.5, 'from' => 0, 'to' => 100), 'start' => 20, 'end' => 50), //00
            array('msg' => '9.Valid float price tariff', 'expected' => 30,'tariff' => array('price' => 1.5, 'from' => 0, 'to' => 100), 'start' => 30, 'end' => 50), //00
            array('msg' => '10.Valid float price tariff', 'expected' => 15,'tariff' => array('price' => 1.5, 'from' => 0, 'to' => 100), 'start' => 40, 'end' => 50), //00
            array('msg' => '11.Valid float price tariff', 'expected' => 7.5,'tariff' => array('price' => 1.5, 'from' => 0, 'to' => 100), 'start' => 45, 'end' => 50), //00
            array('msg' => '12.Valid float price tariff', 'expected' => 1.5,'tariff' => array('price' => 1.5, 'from' => 0, 'to' => 100), 'start' => 49, 'end' => 50), //00
		
			array('msg' => '1.Valid take tariff from', 'expected' => 80,'tariff' => array('price' => 2, 'from' => 10, 'to' => 100), 'start' => 1, 'end' => 50), //00
            array('msg' => '2.Valid take tariff from', 'expected' => 60,'tariff' => array('price' => 2, 'from' => 20, 'to' => 100), 'start' => 2, 'end' => 50), //00
            array('msg' => '3.Valid take tariff from', 'expected' => 40,'tariff' => array('price' => 2, 'from' => 30, 'to' => 100), 'start' => 3, 'end' => 50), //00
            array('msg' => '4.Valid take tariff from', 'expected' => 20,'tariff' => array('price' => 2, 'from' => 40, 'to' => 100), 'start' => 4, 'end' => 50), //00
            array('msg' => '5.Valid take tariff from', 'expected' => 10,'tariff' => array('price' => 2, 'from' => 45, 'to' => 100), 'start' => 5, 'end' => 50), //00
            array('msg' => '6.Valid take tariff from', 'expected' => 2,'tariff' => array('price' => 2, 'from' => 49, 'to' => 100), 'start' => 6, 'end' => 50), //00
		
            array('msg' => '1.Valid take tariff to', 'expected' => 120,'tariff' => array('price' => 3, 'from' => 0, 'to' => 50), 'start' => 10, 'end' => 51), //00
            array('msg' => '2.Valid take tariff to', 'expected' => 90,'tariff' => array('price' => 3, 'from' => 0, 'to' => 50), 'start' => 20, 'end' => 52), //00
            array('msg' => '3.Valid take tariff to', 'expected' => 60,'tariff' => array('price' => 3, 'from' => 0, 'to' => 50), 'start' => 30, 'end' => 53), //00
            array('msg' => '4.Valid take tariff to', 'expected' => 30,'tariff' => array('price' => 3, 'from' => 0, 'to' => 50), 'start' => 40, 'end' => 54), //00
            array('msg' => '5.Valid take tariff to', 'expected' => 15,'tariff' => array('price' => 3, 'from' => 0, 'to' => 50), 'start' => 45, 'end' => 55), //00
            array('msg' => '6.Valid take tariff to', 'expected' => 3,'tariff' => array('price' => 3, 'from' => 0, 'to' => 50), 'start' => 49, 'end' => 56), //00
    );

	public function __construct($label = false) {
		parent::__construct($label);
		$this->subscriber = new Billrun_Subscriber_Db();
	}
		
	function testGetFlatEntires() {
		$protectedMethod = new ReflectionMethod("Billrun_Subscriber_Db", 'getMonthlyFractionOnChargeFlatEntriesForUpfrontPay');
		$protectedMethod->setAccessible(true);
		foreach ($this->getFlatEntriesTests as $test) {
//			$protectedMethod->invokeArgs($this->subscriber, array()); TODO change to test the actual billing cycle
//			$start = $test['start'];
//			$end = $test['end'];
//			$expected = $test['expected'];
//			$result = Billrun_Utils_Time::getMonthsDiff($start, $end);
//			$roundedResult = round($result, 8, PHP_ROUND_HALF_UP);
//			$roundedExpected = round($expected, 8, PHP_ROUND_HALF_UP);
//			$this->assertEqual($roundedResult, $roundedExpected, $test['msg'] . " expected: " . print_r($expected,1) . " result: " . print_r($result,1));
		}
	}
	
}
