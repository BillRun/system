<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 *  unit test for Billrun_HebrewCal for Hebrew calendar
 *
 * @package  Billrun_HebrewCal
 * @since    0.5
 */

define('UNIT_TESTING', 'true');
require_once(APPLICATION_PATH.'/vendor/simpletest/simpletest/autorun.php');
require_once(APPLICATION_PATH.'/library/Billrun/HebrewCal.php');

class Tests_Billrunhebrewcaltets extends UnitTestCase {

    function testIsLeapYearLeapYear() {
        $unixtime = strtotime('2023-10-26'); // 5784, a leap year
        $this->assertTrue(Billrun_HebrewCal::isLeapYear($unixtime), 'Leap year should be correctly identified.');
    }

    function testIsLeapYearNonLeapYear() {
        $unixtime = strtotime('2024-10-26'); // 5785, a non-leap year
        $this->assertFalse(Billrun_HebrewCal::isLeapYear($unixtime), 'Non-leap year should be correctly identified.');
    }

    function testGetHebrewDateString() {
        $unixtime = strtotime('2023-10-26');
        $this->assertEqual('2/11/5784', Billrun_HebrewCal::getHebrewDate($unixtime), 'Hebrew date should be correctly converted to string.');
    }

    function testGetHebrewDateArray() {
        $unixtime = strtotime('2023-10-26');
        $this->assertEqual(array('2', '11', '5784'), Billrun_HebrewCal::getHebrewDate($unixtime, true), 'Hebrew date should be correctly converted to an array.');
    }

    function testGetDayTypeWeekday() {
        $unixtime = strtotime('2023-10-26'); // Thursday, a regular weekday
        $this->assertEqual(HEBCAL_WEEKDAY, Billrun_HebrewCal::getDayType($unixtime), 'Regular weekdays should be correctly identified.');
    }

    function testGetDayTypeWeekend() {
        $unixtime = strtotime('2023-10-28'); // Saturday, a weekend
        $this->assertEqual(HEBCAL_WEEKEND, Billrun_HebrewCal::getDayType($unixtime), 'Weekends should be correctly identified.');
    }

    function testGetDayTypeHoliday() {
        $unixtime = strtotime('2024-10-04'); // Friday, Rosh Heshana(holiday)
        $this->assertEqual(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($unixtime), 'Holidays should be correctly identified.');
    }

    function testGetDayTypeWeekendHoliday() {
        $unixtime = strtotime('2024-10-04'); // Friday, Rosh Heshana(holiday)
        $this->assertEqual(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($unixtime), 'Holiday on a weekend should be identified as a holiday.');
    }

    function testWeekendBeforeHoliday() {
        $unixtime = strtotime('2023-09-16'); // Date that is both a weekend and a holiday (if applicable)
        $this->assertEqual(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($unixtime), 'Weekend should be identified before checking for a holiday.');
    }

    function testIsRegularWorkdayWeekday() {
        $unixtime = strtotime('2023-10-26'); // Thursday, a regular weekday
        $this->assertTrue(Billrun_HebrewCal::isRegularWorkday($unixtime), 'Regular weekdays should be correctly identified as workdays.');
    }

    function testIsRegularWorkdayWeekend() {
        $unixtime = strtotime('2023-10-28'); // Saturday, a weekend
        $this->assertFalse(Billrun_HebrewCal::isRegularWorkday($unixtime), 'Weekends should not be identified as regular workdays.');
    }

    function testIsRegularWorkdayHoliday() {
        $unixtime = strtotime('2024-10-04'); // Friday, Rosh Heshana
        $this->assertFalse(Billrun_HebrewCal::isRegularWorkday($unixtime), 'Holidays should not be identified as regular workdays.');
    }

    function testHolHamoedFallsOnShabbat() {
       
        $unixtime = strtotime('2024-04-27'); 
        $this->assertEqual(HEBCAL_WEEKEND, Billrun_HebrewCal::getDayType($unixtime), 'Hol HaMoed on Shabbat should be identified as a weekend.');
    }
    
    function testHanukkahFallsOnShabbat() {
        $unixtime = strtotime('2023-12-09');
        $this->assertEqual(HEBCAL_WEEKEND, Billrun_HebrewCal::getDayType($unixtime), 'Hanukkah on Shabbat should be identified as a weekend.');
    }
    }
