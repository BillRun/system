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
require_once(APPLICATION_PATH . '/library/simpletest/autorun.php');
require_once(APPLICATION_PATH . '/application/helpers/Subscriber/Golan.php');

Mock::generate('Subscriber_Golan');

class Tests_SubscriberGolan extends UnitTestCase {
	
	// Tests to check for positive logic
	protected $positiveTests = array(
            array('year' => '2015', 'month' => '02', 'days' => 28, 'nextYear' => '2015', 'nextMonth' => '03', 'nextMonthDays' => 31), // 28 days
            array('year' => '2016', 'month' => '02', 'days' => 29, 'nextYear' => '2016', 'nextMonth' => '03', 'nextMonthDays' => 31), // 29 days
            array('year' => '2016', 'month' => '03', 'days' => 31, 'nextYear' => '2016', 'nextMonth' => '04', 'nextMonthDays' => 30), // 31 days
            array('year' => '2016', 'month' => '04', 'days' => 30, 'nextYear' => '2016', 'nextMonth' => '05', 'nextMonthDays' => 31), // 30 day
            array('year' => '2015', 'month' => '01', 'days' => 31, 'nextYear' => '2015', 'nextMonth' => '02', 'nextMonthDays' => 28), // 30 day
            array('year' => '2016', 'month' => '01', 'days' => 31, 'nextYear' => '2016', 'nextMonth' => '02', 'nextMonthDays' => 29), // 30 day
            array('year' => '2015', 'month' => '12', 'days' => 31, 'nextYear' => '2016', 'nextMonth' => '01', 'nextMonthDays' => 31), // 30 day
    );
	
	// Tests to check for negative logic
	protected $negativeTests = array(
            array('key' => '201502', 'start' => 28, 'end' => 10), // 28 days
            array('key' => '201602', 'days' => 29), //00
            array('key' => '201603', 'days' => 31), //31 days
            array('key' => '201604', 'days' => 30), //30 day
    );
	
	/**
	 * Testing for positive logic
	 */
	function testCalcFractionOfMonthPositiveTest() {
		$subscriber = new Subscriber_Golan();
		$cycleStart = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 15);
		$cycleEnd = $cycleStart - 1;
		
		foreach ($this->positiveTests as $testCase) {
			$startMonth = $testCase['month'];
			$days = $testCase['days'];
			$daysNextMonth = $testCase['nextMonthDays'];
			$startYear = $testCase['year'];
			
			$nextYear = $testCase['nextYear'];
			$nextMonth = $testCase['nextMonth'];
			
			$key = $nextYear . $nextMonth;
			$subscriber->setBillrunKey($key);
			
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
						 
					if($endDayIndex == 0) {
						$endDayIndex = 1;
					}
						 
					// Check if we moved to the next month
					if(!$isNextMonthEnd && ($endDayIndex < $cycleEnd)) {
						$isNextMonthEnd = true;
						$endMonth = $nextMonth;
						$endYear = $nextYear;
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
					
					$fraction = $subscriber->calcFractionOfMonth($startDate, $endDate);
					$message = "Start: " . $startDate . " End: " . $endDate . " Expected: " . $expectation . " Received: " . $fraction . " TestCase: " . print_r($testCase, 1);
					$this->assertEqual($expectation, $fraction, $message);
				} 
				
				// Advance the day index
				$dayIndex = ($dayIndex + 1) % $days;
			} while ($dayIndex != $cycleStart);
		}
	}
}
