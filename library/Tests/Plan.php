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

class Tests_Plan extends UnitTestCase {
	
    protected $getPriceTests = array(
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

	// Tests to check for positive logic
	protected $fractionOfMonthTests = array(
            array('year' => '2015', 'month' => '02', 'days' => 28, 'nextYear' => '2015', 'nextMonth' => '03', 'nextMonthDays' => 31), // 28 days
            array('year' => '2016', 'month' => '02', 'days' => 29, 'nextYear' => '2016', 'nextMonth' => '03', 'nextMonthDays' => 31), // 29 days
            array('year' => '2016', 'month' => '03', 'days' => 31, 'nextYear' => '2016', 'nextMonth' => '04', 'nextMonthDays' => 30), // 31 days
            array('year' => '2016', 'month' => '04', 'days' => 30, 'nextYear' => '2016', 'nextMonth' => '05', 'nextMonthDays' => 31), // 30 day
            array('year' => '2015', 'month' => '01', 'days' => 31, 'nextYear' => '2015', 'nextMonth' => '02', 'nextMonthDays' => 28), // 30 day
            array('year' => '2016', 'month' => '01', 'days' => 31, 'nextYear' => '2016', 'nextMonth' => '02', 'nextMonthDays' => 29), // 30 day
            array('year' => '2015', 'month' => '12', 'days' => 31, 'nextYear' => '2016', 'nextMonth' => '01', 'nextMonthDays' => 31), // 30 day
    );
	
	// Tests to check for positive logic
	protected $monthsDiffTests = array(
            array('msg' => "Months diff start after end", 'start' => '02-02-2015', 'end' => '01-02-2015', 'expected' => 0),
		
            array('msg' => "1.Months diff same month", 'start' => '01-02-2015', 'end' => '10-02-2015', 'expected' => 10/28),
            array('msg' => "2.Months diff same month", 'start' => '01-02-2015', 'end' => '15-02-2015', 'expected' => 15/28),
            array('msg' => "3.Months diff same month", 'start' => '01-02-2016', 'end' => '10-02-2016', 'expected' => 10/29),
            array('msg' => "4.Months diff same month", 'start' => '01-02-2016', 'end' => '15-02-2016', 'expected' => 15/29),
            array('msg' => "5.Months diff same month", 'start' => '01-03-2016', 'end' => '10-03-2016', 'expected' => 10/31),
            array('msg' => "6.Months diff same month", 'start' => '01-03-2016', 'end' => '15-03-2016', 'expected' => 15/31),
            array('msg' => "7.Months diff same month", 'start' => '01-04-2016', 'end' => '10-04-2016', 'expected' => 10/30),
            array('msg' => "8.Months diff same month", 'start' => '01-04-2016', 'end' => '15-04-2016', 'expected' => 15/30),
		
			array('msg' => "1.Months diff same month and day", 'start' => '15-02-2016', 'end' => '15-02-2016', 'expected' => 1/29),
		
			array('msg' => "1.Months diff next month", 'start' => '01-02-2015', 'end' => '10-03-2015', 'expected' => 1 + (10/31)),
            array('msg' => "2.Months diff next month", 'start' => '01-02-2015', 'end' => '15-03-2015', 'expected' => 1 + (15/31)),
			array('msg' => "3.Months diff next month", 'start' => '06-02-2015', 'end' => '10-03-2015', 'expected' => (23/28) + (10/31)),
            array('msg' => "4.Months diff next month", 'start' => '06-02-2015', 'end' => '15-03-2015', 'expected' => (23/28) + (15/31)),
		
            array('msg' => "5.Months diff next month", 'start' => '01-02-2016', 'end' => '10-03-2016', 'expected' => 1 + (10/31)),
            array('msg' => "6.Months diff next month", 'start' => '01-02-2016', 'end' => '15-03-2016', 'expected' => 1 + (15/31)),
			array('msg' => "7.Months diff next month", 'start' => '06-02-2016', 'end' => '10-03-2016', 'expected' => (24/29) + (10/31)),
            array('msg' => "8.Months diff next month", 'start' => '06-02-2016', 'end' => '15-03-2016', 'expected' => (24/29) + (15/31)),
		
            array('msg' => "9.Months diff next month", 'start' => '01-03-2016', 'end' => '10-04-2016', 'expected' => 1 + (10/30)),
            array('msg' => "10.Months diff next month", 'start' => '01-03-2016', 'end' => '15-04-2016', 'expected' => 1+ (15/30)),
            array('msg' => "11.Months diff next month", 'start' => '06-03-2016', 'end' => '10-04-2016', 'expected' => (26/31) + (10/30)),
            array('msg' => "12.Months diff next month", 'start' => '06-03-2016', 'end' => '15-04-2016', 'expected' => (26/31) + (15/30)),
		
            array('msg' => "1.Months diff next month next year", 'start' => '01-12-2015', 'end' => '10-01-2016', 'expected' => 1 + (10/31)),
            array('msg' => "2.Months diff next month next year", 'start' => '01-12-2015', 'end' => '15-01-2016', 'expected' => 1 + (15/31)),
            array('msg' => "3.Months diff next month next year", 'start' => '06-12-2015', 'end' => '15-01-2016', 'expected' => (26/31) + (15/31)),
            array('msg' => "4.Months diff next month next year", 'start' => '06-12-2015', 'end' => '15-01-2016', 'expected' => (26/31) + (15/31)),
            array('msg' => "5.Months diff next month next year", 'start' => '01-11-2015', 'end' => '10-01-2016', 'expected' => 2 + (10/31)),
            array('msg' => "6.Months diff next month next year", 'start' => '01-11-2015', 'end' => '15-01-2016', 'expected' => 2 + (15/31)),
            array('msg' => "7.Months diff next month next year", 'start' => '06-11-2015', 'end' => '15-01-2016', 'expected' => (25/30) + 1 + (15/31)),
            array('msg' => "8.Months diff next month next year", 'start' => '06-11-2015', 'end' => '15-01-2016', 'expected' => (25/30) + 1 + (15/31)),
			array('msg' => "9.Months diff next month next year", 'start' => '01-12-2015', 'end' => '10-02-2016', 'expected' => 2 + (10/29)),
            array('msg' => "10.Months diff next month next year", 'start' => '01-12-2015', 'end' => '15-02-2016', 'expected' => 2 + (15/29)),
            array('msg' => "11.Months diff next month next year", 'start' => '06-12-2015', 'end' => '15-02-2016', 'expected' => (26/31) + 1 + (15/29)),
            array('msg' => "12.Months diff next month next year", 'start' => '06-12-2015', 'end' => '15-02-2016', 'expected' => (26/31) + 1 + (15/29)),
			array('msg' => "13.Months diff next month next year", 'start' => '01-12-2014', 'end' => '10-02-2015', 'expected' => 2 + (10/28)),
            array('msg' => "14.Months diff next month next year", 'start' => '01-12-2014', 'end' => '15-02-2015', 'expected' => 2 + (15/28)),
            array('msg' => "15.Months diff next month next year", 'start' => '06-12-2014', 'end' => '15-02-2015', 'expected' => (26/31) + 1 + (15/28)),
            array('msg' => "16.Months diff next month next year", 'start' => '06-12-2014', 'end' => '15-02-2015', 'expected' => (26/31) + 1 + (15/28)),
		
		    array('msg' => "1.Months diff three months", 'start' => '01-02-2015', 'end' => '10-04-2015', 'expected' => 2 + (10/30)),
            array('msg' => "2.Months diff three months", 'start' => '01-02-2015', 'end' => '15-04-2015', 'expected' => 2 + (15/30)),
		    array('msg' => "3.Months diff three months", 'start' => '06-02-2015', 'end' => '10-04-2015', 'expected' => (23/28) + 1 + (10/30)),
            array('msg' => "4.Months diff three months", 'start' => '06-02-2015', 'end' => '15-04-2015', 'expected' => (23/28) + 1 + (15/30)),
		    array('msg' => "5.Months diff three months", 'start' => '01-02-2016', 'end' => '10-04-2016', 'expected' => 2 + (10/30)),
            array('msg' => "6.Months diff three months", 'start' => '01-02-2016', 'end' => '15-04-2016', 'expected' => 2 + (15/30)),
		    array('msg' => "7.Months diff three months", 'start' => '06-02-2016', 'end' => '10-04-2016', 'expected' => (24/29) + 1 + (10/30)),
            array('msg' => "8.Months diff three months", 'start' => '06-02-2016', 'end' => '15-04-2016', 'expected' => (24/29) + 1 + (15/30)),
		
		    array('msg' => "1.Months diff four months", 'start' => '01-02-2015', 'end' => '10-05-2015', 'expected' => 3 + (10/31)),
            array('msg' => "2.Months diff four months", 'start' => '01-02-2015', 'end' => '15-05-2015', 'expected' => 3 + (15/31)),
		    array('msg' => "3.Months diff four months", 'start' => '06-02-2015', 'end' => '10-05-2015', 'expected' => (23/28) + 2 + (10/31)),
            array('msg' => "4.Months diff four months", 'start' => '06-02-2015', 'end' => '15-05-2015', 'expected' => (23/28) + 2 + (15/31)),
		    array('msg' => "5.Months diff four months", 'start' => '01-02-2016', 'end' => '10-05-2016', 'expected' => 3 + (10/31)),
            array('msg' => "6.Months diff four months", 'start' => '01-02-2016', 'end' => '15-05-2016', 'expected' => 3 + (15/31)),
		    array('msg' => "7.Months diff four months", 'start' => '06-02-2016', 'end' => '10-05-2016', 'expected' => (24/29) + 2 + (10/31)),
            array('msg' => "8.Months diff four months", 'start' => '06-02-2016', 'end' => '15-05-2016', 'expected' => (24/29) + 2 + (15/31)),

    );
	
	// Tests to check for negative logic
	protected $negativeTests = array(
            array('key' => '201502', 'start' => 28, 'end' => 10), // 28 days
            array('key' => '201602', 'days' => 29), //00
            array('key' => '201603', 'days' => 31), //31 days
            array('key' => '201604', 'days' => 30), //30 day
    );
	
	protected $isPlanExistsTests = array(
		array('msg' => "Base plan CAPITAL", 'exists' => true, 'name' => 'BASE'),
		array('msg' => "Base plan mixed case", 'exists' => false, 'name' => 'Base'),
		array('msg' => "Base plan lower case", 'exists' => false, 'name' => 'base'),
		
		// Creating test plans
		array('msg' => "Test plan not created", 'create' => false, 'exists' => false, 'name' => 'UnitTestPlan'),
		array('msg' => "Test plan created", 'create' => true, 'exists' => true, 'name' => 'UnitTestPlan', 'to' => '+1 month', 'from' => '-1 month'),
		array('msg' => "Test plan created in the future", 'create' => true, 'exists' => false, 'name' => 'UnitTestPlan', 'to' => '+1 year', 'from' => '+1 month'),
		array('msg' => "Test plan created in the past", 'create' => true, 'exists' => false, 'name' => 'UnitTestPlan', 'to' => '-1 month', 'from' => '-1 yaer'),
		
	);
	
	/**
	 * Testing for positive logic
	 */
	function testCalcFractionOfMonthPositiveTest() {
		$cycleStart = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 1);
		$cycleEnd = $cycleStart - 1;
		
		foreach ($this->fractionOfMonthTests as $testCase) {
			$startMonth = $testCase['month'];
			$days = $testCase['days'];
			$daysNextMonth = $testCase['nextMonthDays'];
			$startYear = $testCase['year'];
			
			$nextYear = $testCase['nextYear'];
			$nextMonth = $testCase['nextMonth'];
			
			$key = $nextYear . $nextMonth;
			
			$totalDays = $days;
			
			$isNextMonthStart = false;
			
			$dayIndex = $cycleStart;
			do {
				if($dayIndex == 0) {
					$dayIndex = 1;
				}
				
				// Check if we moved to the next month
				if(!$isNextMonthStart && ($dayIndex < $cycleStart)) {
					$isNextMonthStart = true;
					$startMonth = $nextMonth;
					$startYear = $nextYear;
					$days = $daysNextMonth;
				}
				
				$endMonth = $startMonth;
				$endYear = $startYear;
			
				$isNextMonthEnd = false;
				
				// Advance the day index
				for ($endDayIndex = $dayIndex; 
				     (!(($endMonth == $nextMonth) && ($endDayIndex == $cycleStart))); 
					 $endDayIndex = (($endDayIndex + 1) % $days)) {
						 
					// Check if we moved to the next month
					if(!$isNextMonthEnd && (($endDayIndex < $cycleEnd) || (($cycleEnd == 0) && ($endDayIndex == 0)))) {
						$isNextMonthEnd = true;
						$endMonth = $nextMonth;
						$endYear = $nextYear;
					}
					
					if($endDayIndex == 0) {
						$endDayIndex = 1;
					}
					
					$startDate = $startYear . '-' . $startMonth . '-' . str_pad($dayIndex, 2, "0", STR_PAD_LEFT);
					$endDate = $endYear . '-' . $endMonth . '-' . str_pad($endDayIndex, 2, "0", STR_PAD_LEFT);
				
					// Counting the last day as well
					$daysPassed = $endDayIndex - $dayIndex + 1;
					
					// Spreading over more than a month.
					if($startMonth != $endMonth) {
						$daysPassed = $totalDays - $dayIndex + 1;
						$daysPassed += $endDayIndex;
					}
					$expectation = $daysPassed / $totalDays;
					
					$fraction = Billrun_Plan::calcFractionOfMonth($key, $startDate, $endDate);
					$message = "Start: " . $startDate . " End: " . $endDate . " Expected: " . $expectation . " Received: " . $fraction . " TestCase: " . print_r($testCase, 1);
					$this->assertEqual($expectation, $fraction, $message);
					
					if(($endMonth == $nextMonth) && ($cycleStart == 1) && ($endDayIndex == $cycleStart)) {
						break;
					}
				} 
					
				// Advance the day index
				$dayIndex = ($dayIndex + 1) % $days;
				
				if($dayIndex == 0) {
					$dayIndex = 1;
				}
			} while ($dayIndex != $cycleStart);
		}
	}
	
	function testGetMonthsDiff() {
		foreach ($this->monthsDiffTests as $test) {
			$start = $test['start'];
			$end = $test['end'];
			$expected = $test['expected'];
			$result = Billrun_Utils_Time::getMonthsDiff($start, $end);
			$roundedResult = round($result, 8, PHP_ROUND_HALF_UP);
			$roundedExpected = round($expected, 8, PHP_ROUND_HALF_UP);
			$this->assertEqual($roundedResult, $roundedExpected, $test['msg'] . " expected: " . print_r($expected,1) . " result: " . print_r($result,1));
		}
	}
	
    function testGetPriceByTariff() {
		$testCase = 0;
		foreach ($this->getPriceTests as $test) {
			$tariff = $test['tariff'];
			$startOffset = $test['start'];
			$endOffset = $test['end'];
			$step = new Billrun_Plans_Step($tariff);
			$result = $step->getRelativePrice($startOffset, $endOffset);
			$expected = $test['expected'];
			$this->assertEqual($result['price'], $expected, $test['msg'] . " expected: " . $expected . " result: " . $result);
			$testCase++;
		}
    }
	
	function testIsPlanExists() {
		$plansColl = Billrun_Factory::db()->plansCollection();
		foreach ($this->isPlanExistsTests as $test) {
			$created = false;
			$planName = $test['name'];
			
			// Create the plan
			if(isset($test['create']) && $test['create']) {
				$plan['name'] = $planName;
				$plan['from'] = new Mongodloid_Date(strtotime($test['from']));
				$plan['to'] = new Mongodloid_Date(strtotime($test['to']));
				$plansColl->insert($plan);
				$created = true;
			}
			
			$result = Billrun_Plans_Util::isPlanExists($planName);
			$this->assertEqual($result, $test['exists'], $test['msg']);
			
			if($created) {
				$plansColl->remove($plan);
			}
		}
	}
	
}
