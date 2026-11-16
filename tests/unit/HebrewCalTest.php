<?php

/**
 * Unit tests for Billrun_HebrewCal
 *
 * Note: PHP's calendar extension numbers Hebrew months 1 (Tishrei) .. 13 (Elul),
 * where 6 is Adar I and 7 is Adar / Adar II, 8 is Nisan, 9 is Iyar etc.
 */
class HebrewCalTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * Convert a Hebrew date to a unix timestamp at local noon (avoids any timezone day-shifting).
     */
    protected function hebrewToUnix($month, $day, $year)
    {
        list($gMonth, $gDay, $gYear) = explode('/', jdtogregorian(jewishtojd($month, $day, $year)));
        return mktime(12, 0, 0, $gMonth, $gDay, $gYear);
    }

    protected function holidaysFor($year, $walledCity = false)
    {
        // any date inside the Hebrew year will do; use 1 Heshvan
        return Billrun_HebrewCal::getHolidaysForYear($this->hebrewToUnix(2, 1, $year), $walledCity);
    }

    protected function hebrewDow($month, $day, $year)
    {
        return (int) date('w', $this->hebrewToUnix($month, $day, $year));
    }

    // ---------------------------------------------------------------------
    // getHebrewDate
    // ---------------------------------------------------------------------

    public function testGetHebrewDateAsString()
    {
        $this->assertEquals('2/11/5784', Billrun_HebrewCal::getHebrewDate(strtotime('2023-10-26 12:00')));
    }

    public function testGetHebrewDateAsArray()
    {
        $this->assertEquals(['2', '11', '5784'], Billrun_HebrewCal::getHebrewDate(strtotime('2023-10-26 12:00'), true));
    }

    public function testGetHebrewDateRoshHashana()
    {
        $this->assertEquals('1/1/5785', Billrun_HebrewCal::getHebrewDate(strtotime('2024-10-03 12:00')));
    }

    // ---------------------------------------------------------------------
    // isLeapYear
    // ---------------------------------------------------------------------

    public function testIsLeapYear()
    {
        $this->assertTrue(Billrun_HebrewCal::isLeapYear($this->hebrewToUnix(2, 1, 5784)));
        $this->assertTrue(Billrun_HebrewCal::isLeapYear($this->hebrewToUnix(2, 1, 5787)));
        $this->assertTrue(Billrun_HebrewCal::isLeapYear($this->hebrewToUnix(2, 1, 5790)));
    }

    public function testIsNotLeapYear()
    {
        $this->assertFalse(Billrun_HebrewCal::isLeapYear($this->hebrewToUnix(2, 1, 5785)));
        $this->assertFalse(Billrun_HebrewCal::isLeapYear($this->hebrewToUnix(2, 1, 5786)));
        $this->assertFalse(Billrun_HebrewCal::isLeapYear($this->hebrewToUnix(2, 1, 5788)));
    }

    public function testIsLeapYearAgreesWithCalendarExtension()
    {
        // a leap year has Adar I (month 6) with 30 days, a regular year has none
        for ($year = 5780; $year < 5800; $year++) {
            $hasAdarI = cal_days_in_month(CAL_JEWISH, 6, $year) == 30;
            $this->assertSame($hasAdarI, Billrun_HebrewCal::isLeapYear($this->hebrewToUnix(2, 1, $year)), "year $year");
        }
    }

    // ---------------------------------------------------------------------
    // getHolidaysForYear
    // ---------------------------------------------------------------------

    public function testFixedHolidaysAlwaysPresent()
    {
        $holidays = $this->holidaysFor(5786);
        $this->assertEquals(HEBCAL_SHORTDAY, $holidays['13/29']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['01/01']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['01/02']);
        $this->assertEquals(HEBCAL_SHORTDAY, $holidays['01/09']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['01/10']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['01/15']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['01/22']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['08/15']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['08/21']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['10/06']);
    }

    public function testLeapYearMovesPurimToAdarII()
    {
        $holidays = $this->holidaysFor(5784);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['07/13']);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['07/14']);
        $this->assertArrayNotHasKey('06/13', $holidays);
        $this->assertArrayNotHasKey('06/14', $holidays);
    }

    public function testShortKislevExtendsHanukkaIntoTevet()
    {
        $this->assertEquals(29, cal_days_in_month(CAL_JEWISH, 3, 5784));
        $holidays = $this->holidaysFor(5784);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['04/03']);
    }

    public function testFullKislevDoesNotExtendHanukka()
    {
        $this->assertEquals(30, cal_days_in_month(CAL_JEWISH, 3, 5785));
        $holidays = $this->holidaysFor(5785);
        $this->assertArrayNotHasKey('04/03', $holidays);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['04/02']);
    }

    public function testWalledCityShiftsPurim()
    {
        $holidays = $this->holidaysFor(5785, true);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['06/14']);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['06/15']);
        $this->assertArrayNotHasKey('06/13', $holidays);
    }

    public function testYomHashoahOnTuesdayIsNotMoved()
    {
        $this->assertEquals(2, $this->hebrewDow(8, 27, 5783));
        $holidays = $this->holidaysFor(5783);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['08/26']);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['08/27']);
        $this->assertArrayNotHasKey('08/25', $holidays);
        $this->assertArrayNotHasKey('08/28', $holidays);
    }

    public function testYomHashoahOnFridayIsPreponed()
    {
        $this->assertEquals(5, $this->hebrewDow(8, 27, 5785));
        $holidays = $this->holidaysFor(5785);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['08/25']);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['08/26']);
        $this->assertArrayNotHasKey('08/27', $holidays);
    }

    public function testYomHashoahOnSundayIsPostponed()
    {
        $this->assertEquals(0, $this->hebrewDow(8, 27, 5784));
        $holidays = $this->holidaysFor(5784);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['08/27']);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['08/28']);
        $this->assertArrayNotHasKey('08/26', $holidays);
    }

    public function testYomHaatzmautOnWednesdayIsNotMoved()
    {
        $this->assertEquals(3, $this->hebrewDow(9, 5, 5786));
        $holidays = $this->holidaysFor(5786);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['09/03']);
        $this->assertEquals(HEBCAL_SHORTDAY, $holidays['09/04']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['09/05']);
        $this->assertArrayNotHasKey('09/06', $holidays);
    }

    public function testYomHaatzmautOnMondayIsPostponed()
    {
        $this->assertEquals(1, $this->hebrewDow(9, 5, 5784));
        $holidays = $this->holidaysFor(5784);
        $this->assertArrayNotHasKey('09/03', $holidays);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['09/04']);
        $this->assertEquals(HEBCAL_SHORTDAY, $holidays['09/05']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['09/06']);
    }

    public function testYomHaatzmautOnSaturdayIsPreponedToThursday()
    {
        $this->assertEquals(6, $this->hebrewDow(9, 5, 5785));
        $holidays = $this->holidaysFor(5785);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['09/01']);
        $this->assertEquals(HEBCAL_SHORTDAY, $holidays['09/02']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['09/03']);
        $this->assertArrayNotHasKey('09/04', $holidays);
        $this->assertArrayNotHasKey('09/05', $holidays);
    }

    public function testYomHaatzmautOnFridayIsPreponedToThursday()
    {
        $this->assertEquals(5, $this->hebrewDow(9, 5, 5789));
        $holidays = $this->holidaysFor(5789);
        $this->assertArrayNotHasKey('09/01', $holidays);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['09/02']);
        $this->assertEquals(HEBCAL_SHORTDAY, $holidays['09/03']);
        $this->assertEquals(HEBCAL_HOLIDAY, $holidays['09/04']);
        $this->assertArrayNotHasKey('09/05', $holidays);
    }

    public function testJerusalemDayOnFridayIsPreponed()
    {
        $this->assertEquals(5, $this->hebrewDow(9, 28, 5786));
        $holidays = $this->holidaysFor(5786);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['09/27']);
        $this->assertArrayNotHasKey('09/28', $holidays);
    }

    public function testJerusalemDayNotOnFridayIsNotMoved()
    {
        $this->assertEquals(1, $this->hebrewDow(9, 28, 5785));
        $holidays = $this->holidaysFor(5785);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['09/28']);
        $this->assertArrayNotHasKey('09/27', $holidays);
    }

    /**
     * Regression (BRCD-5531): preponing Jerusalem day used to write into Nisan (08/27, 08/28),
     * clobbering Yom HaShoah.
     */
    public function testJerusalemDayPreponeDoesNotTouchYomHashoah()
    {
        $this->assertEquals(5, $this->hebrewDow(9, 28, 5786));
        $this->assertEquals(2, $this->hebrewDow(8, 27, 5786));
        $holidays = $this->holidaysFor(5786);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['08/26']);
        $this->assertEquals(HEBCAL_WORKDAY, $holidays['08/27']);
        $this->assertArrayNotHasKey('08/28', $holidays);
    }

    public function testNoNullEntriesAcrossManyYears()
    {
        for ($year = 5780; $year < 5800; $year++) {
            foreach ($this->holidaysFor($year) as $key => $type) {
                $this->assertNotNull($type, "year $year, key $key");
            }
        }
    }

    // ---------------------------------------------------------------------
    // getDayType
    // ---------------------------------------------------------------------

    public function testRegularWeekday()
    {
        $this->assertEquals(HEBCAL_WEEKDAY, Billrun_HebrewCal::getDayType(strtotime('2023-10-26 12:00'))); // Thursday
    }

    public function testSaturdayIsWeekend()
    {
        $this->assertEquals(HEBCAL_WEEKEND, Billrun_HebrewCal::getDayType(strtotime('2023-10-28 12:00')));
    }

    public function testFridayIsNotWeekendByDefault()
    {
        $this->assertEquals(HEBCAL_WEEKDAY, Billrun_HebrewCal::getDayType(strtotime('2023-10-27 12:00')));
    }

    public function testRoshHashanaIsHoliday()
    {
        $this->assertEquals(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(1, 1, 5785)));
        $this->assertEquals(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(1, 2, 5785)));
    }

    public function testHolidayOnSaturdayIsHoliday()
    {
        $unixtime = $this->hebrewToUnix(1, 10, 5785); // Yom Kippur 5785
        $this->assertEquals(6, (int) date('w', $unixtime));
        $this->assertEquals(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($unixtime));
    }

    public function testHolidayEveIsShortday()
    {
        $unixtime = $this->hebrewToUnix(1, 9, 5786); // Erev Yom Kippur 5786
        $this->assertEquals(3, (int) date('w', $unixtime));
        $this->assertEquals(HEBCAL_SHORTDAY, Billrun_HebrewCal::getDayType($unixtime));
    }

    public function testShortdayOnSaturdayIsWeekend()
    {
        $unixtime = $this->hebrewToUnix(8, 14, 5785); // Erev Pesach 5785
        $this->assertEquals(6, (int) date('w', $unixtime));
        $this->assertEquals(HEBCAL_WEEKEND, Billrun_HebrewCal::getDayType($unixtime));
    }

    public function testHanukkaIsWorkday()
    {
        $unixtime = $this->hebrewToUnix(3, 25, 5786);
        $this->assertNotEquals(6, (int) date('w', $unixtime));
        $this->assertEquals(HEBCAL_WORKDAY, Billrun_HebrewCal::getDayType($unixtime));
    }

    public function testElulEveOfRoshHashanaIsShortday()
    {
        $unixtime = $this->hebrewToUnix(13, 29, 5784); // Erev Rosh Hashana 5785 (two-digit month key)
        $this->assertNotEquals(6, (int) date('w', $unixtime));
        $this->assertEquals(HEBCAL_SHORTDAY, Billrun_HebrewCal::getDayType($unixtime));
    }

    public function testPreponedYomHaatzmautIsHoliday()
    {
        // 5785: 5 Iyar is Saturday, celebrated on Thursday 3 Iyar
        $this->assertEquals(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(9, 3, 5785)));
        $this->assertEquals(HEBCAL_SHORTDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(9, 2, 5785)));
    }

    public function testPostponedYomHaatzmautIsHoliday()
    {
        // 5784: 5 Iyar is Monday, celebrated on Tuesday 6 Iyar
        $this->assertEquals(HEBCAL_SHORTDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(9, 5, 5784)));
        $this->assertEquals(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(9, 6, 5784)));
    }

    public function testYomHashoahIsWorkdayWhenJerusalemDayIsPreponed()
    {
        // BRCD-5531 regression, end to end
        $this->assertEquals(HEBCAL_WORKDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(8, 27, 5786)));
        $this->assertEquals(HEBCAL_WORKDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(9, 27, 5786)));
        $this->assertEquals(HEBCAL_WEEKDAY, Billrun_HebrewCal::getDayType($this->hebrewToUnix(9, 28, 5786)));
    }

    public function testCustomWeekends()
    {
        $friday = strtotime('2023-10-27 12:00');
        $weekends = ['5' => HEBCAL_WEEKEND, '6' => HEBCAL_WEEKEND];
        $this->assertEquals(HEBCAL_WEEKEND, Billrun_HebrewCal::getDayType($friday, $weekends));
    }

    public function testCustomWeekendsCanDefineShortday()
    {
        $friday = strtotime('2023-10-27 12:00');
        $weekends = ['5' => HEBCAL_SHORTDAY, '6' => HEBCAL_WEEKEND];
        $this->assertEquals(HEBCAL_SHORTDAY, Billrun_HebrewCal::getDayType($friday, $weekends));
    }

    public function testCustomHolidays()
    {
        $unixtime = strtotime('2023-10-26 12:00'); // 11 Heshvan 5784
        $this->assertEquals(HEBCAL_HOLIDAY, Billrun_HebrewCal::getDayType($unixtime, false, ['02/11' => HEBCAL_HOLIDAY]));
    }

    public function testCustomHolidaysReplaceDefaults()
    {
        $roshHashana = $this->hebrewToUnix(1, 1, 5785); // Thursday
        $this->assertEquals(HEBCAL_WEEKDAY, Billrun_HebrewCal::getDayType($roshHashana, false, ['02/11' => HEBCAL_HOLIDAY]));
    }

    // ---------------------------------------------------------------------
    // isRegularWorkday
    // ---------------------------------------------------------------------

    public function testIsRegularWorkday()
    {
        $this->assertTrue(Billrun_HebrewCal::isRegularWorkday(strtotime('2023-10-26 12:00'))); // weekday
        $this->assertTrue(Billrun_HebrewCal::isRegularWorkday($this->hebrewToUnix(3, 25, 5786))); // Hanukka (workday)
        $this->assertFalse(Billrun_HebrewCal::isRegularWorkday(strtotime('2023-10-28 12:00'))); // Saturday
        $this->assertFalse(Billrun_HebrewCal::isRegularWorkday($this->hebrewToUnix(1, 1, 5785))); // holiday
        $this->assertFalse(Billrun_HebrewCal::isRegularWorkday($this->hebrewToUnix(1, 9, 5786))); // shortday
    }
}
