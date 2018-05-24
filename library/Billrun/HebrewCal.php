<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
define('HEBCAL_WEEKDAY', 'weekday');
define('HEBCAL_WEEKEND', 'weekend');
define('HEBCAL_WORKDAY', 'workday');
define('HEBCAL_HOLIDAY', 'holiday');
define('HEBCAL_SHORTDAY', 'shortday');

/**
 * Billing generic time utils class
 * 
 * @TODO extract this class to from the billrun to a seperate library
 * 
 * @package  Billrun Hebrew Calendar
 * @since    0.5
 */
class Billrun_HebrewCal {

	public static $STANDARD_YEAR_HEBCAL_HOLIDAYS = array(
		// Rosh Hashanah
		'13/29' => HEBCAL_SHORTDAY, // Rosh ahshanna evening
		'01/01' => HEBCAL_HOLIDAY, // Rosh ahshanna
		'01/02' => HEBCAL_HOLIDAY, // Rosh ahshanna
		//Yum Kippur
		'01/09' => HEBCAL_SHORTDAY, // yum kipur evening
		'01/10' => HEBCAL_HOLIDAY, // yum kipur
		// Soukott
		'01/14' => HEBCAL_SHORTDAY, // sukott evening
		'01/15' => HEBCAL_HOLIDAY, // sukott
//		'01/16' => HEBCAL_HOLIDAY, // sukott
//		'01/17' => HEBCAL_HOLIDAY, // sukott
//		'01/18' => HEBCAL_HOLIDAY, // sukott
//		'01/19' => HEBCAL_HOLIDAY, // sukott
//		'01/20' => HEBCAL_HOLIDAY, // sukott
		'01/21' => HEBCAL_SHORTDAY, // sukott
		'01/22' => HEBCAL_HOLIDAY, // Simchat Torah
		// Hanukka
		'03/25' => HEBCAL_WORKDAY, // Hanukka
		'03/26' => HEBCAL_WORKDAY, // Hanukka
		'03/27' => HEBCAL_WORKDAY, // Hanukka
		'03/28' => HEBCAL_WORKDAY, // Hanukka
		'03/29' => HEBCAL_WORKDAY, // Hanukka
		'03/30' => HEBCAL_WORKDAY, // Hanukka
		'04/01' => HEBCAL_WORKDAY, // Hanukka
		'04/02' => HEBCAL_WORKDAY, // Hanukka
		// Tu Besvaht
		'04/15' => HEBCAL_WORKDAY, // Tu besvaht
		// Purim
		'06/13' => HEBCAL_WORKDAY, // Purim evening
		'06/14' => HEBCAL_WORKDAY, // Purim or...
		// Pesaach
		'08/14' => HEBCAL_SHORTDAY, // Pesaach evening
		'08/15' => HEBCAL_HOLIDAY, // Pesaach
//		'08/16' => HEBCAL_HOLIDAY, // Pesaach
//		'08/17' => HEBCAL_HOLIDAY, // Pesaach
//		'08/18' => HEBCAL_HOLIDAY, // Pesaach
//		'08/19' => HEBCAL_HOLIDAY, // Pesaach
		'08/20' => HEBCAL_SHORTDAY, // Pesaach
		'08/21' => HEBCAL_HOLIDAY, // Pesaach Sheni (Second)
		// Yom HaShoah
		'08/26' => HEBCAL_WORKDAY, // Yom HaShoah evening
		'08/27' => HEBCAL_WORKDAY, // Yom HaShoah
		// Yom Hazikaron
		'09/03' => HEBCAL_WORKDAY, // Yom Hazikaron evening
		'09/04' => HEBCAL_SHORTDAY, // Yom Hazikaron
		// Yom Ha'atzmaut
		'09/05' => HEBCAL_HOLIDAY, // Yom Ha'atzmaut
		//Lag Baommer
		'09/17' => HEBCAL_WORKDAY, // Lag Baommer evening
		'09/18' => HEBCAL_WORKDAY, // Lag Baommer
		// Jerusalem day
		'09/28' => HEBCAL_WORKDAY, // Jerusalem day
		// Shavoout
		'10/05' => HEBCAL_SHORTDAY, // Shavoout evening
		'10/06' => HEBCAL_HOLIDAY, // Shavoout
		// Tu Beahav
		'12/15' => HEBCAL_WORKDAY, // Tu Beahav
	);

	/**
	 * Method to get the holidays off all year
	 * 
	 * @param int $unixtime the date to check.
	 * @param type $walledCity
	 * @param type $isAbroad used to calculate Hag Sheni. Not implemented yet
	 * 
	 * @return string
	 */
	public static function getHolidaysForYear($unixtime, $walledCity = false, $isAbroad = false) {
		$retArr = self::$STANDARD_YEAR_HEBCAL_HOLIDAYS;
		list($month, $day, $year) = self::getHebrewDate($unixtime, true);
		$isLeapYear = self::isLeapYear($unixtime);
		if ($isLeapYear) {
			//TODO add leap year data
			// handle purim month
			$retArr['07/13'] = $retArr['06/13']; //Purim evening
			$retArr['07/14'] = $retArr['06/14']; //Purim is in AdarII
			unset($retArr['06/13']);
			unset($retArr['06/14']);
		}

		//handled short Kislev 
		if (cal_days_in_month(CAL_JEWISH, 3, (int) $year) == 29) {
			$retArr['04/03'] = $retArr['04/02']; //Hanukka
		}

		//Purim when you have a wall around you
		if ($walledCity) {
			$retArr['06/15'] = $retArr['06/14'];
			$retArr['06/14'] = $retArr['06/13'];
			unset($retArr['06/13']);
		}

		if (cal_from_jd(jewishtojd(8, 27, $year), CAL_JEWISH)['dow'] == 5) { // prepone Yom HaShoah
			$retArr['08/25'] = $retArr['08/26'];
			$retArr['08/26'] = $retArr['08/27'];
			unset($retArr['08/27']);
		} else if (cal_from_jd(jewishtojd(8, 27, $year), CAL_JEWISH)['dow'] == 0) { // postpone
			$retArr['08/28'] = $retArr['08/27'];
			$retArr['08/27'] = $retArr['08/26'];
			unset($retArr['08/26']);
		}

		if (cal_from_jd(jewishtojd(9, 5, $year), CAL_JEWISH)['dow'] == 1) { // postpone Yom Hazikaron & Atzma'ut from Monday
			$retArr['09/06'] = $retArr['09/05'];
			$retArr['09/05'] = $retArr['09/04'];
			$retArr['09/04'] = $retArr['09/03'];
			unset($retArr['09/03']);
		} else if (cal_from_jd(jewishtojd(9, 5, $year), CAL_JEWISH)['dow'] == 6) { // prepone from Saturday
			$retArr['09/01'] = $retArr['09/03'];
			$retArr['09/02'] = $retArr['09/04'];
			$retArr['09/03'] = $retArr['09/05'];
			unset($retArr['09/04']);
			unset($retArr['09/05']);
		} else if (cal_from_jd(jewishtojd(9, 5, $year), CAL_JEWISH)['dow'] == 5) { // prepone from Friday
			$retArr['09/02'] = $retArr['09/03'];
			$retArr['09/03'] = $retArr['09/04'];
			$retArr['09/04'] = $retArr['09/05'];
			unset($retArr['09/05']);
		}

		if (cal_from_jd(jewishtojd(9, 28, $year), CAL_JEWISH)['dow'] == 5) { // prepone Jerusalem day
			$retArr['08/27'] = $retArr['08/28'];
			unset($retArr['08/28']);
		}

		return $retArr;
	}

	/**
	 * Check if a certain date is a regular weekday or a weekend/holiday
	 * 
	 * @param type $unixtime the  date to check for
	 * 
	 * @return boolean true if the  date is a weekday false otherwise.
	 */
	public static function isRegularWorkday($unixtime) {
		$dayType = self::getDayType($unixtime);
		return HEBCAL_WEEKDAY == $dayType || HEBCAL_WORKDAY == $dayType;
	}

	/**
	 * Get the type  of the day a given time  is in.
	 * (defaults to jewish calander)
	 * 
	 * @param int $unixtime the date to check.
	 * @param boolean $weekends 
	 * @param boolean $holidays 
	 * 
	 * @return string 
	 * 			HEBCAL_WEEKDAY => a regular weekday.
	 * 			HEBCAL_SHORTDAY =>  a short week day.
	 * 			HEBCAL_HOLIDAY => and holiday.
	 * 			HEBCAL_WEEKEND => well weekend.
	 */
	public static function getDayType($unixtime, $weekends = false, $holidays = false) {
		$weekends = $weekends ? $weekends : array('6' => HEBCAL_WEEKEND);
		$holidays = $holidays ? $holidays : self::getHolidaysForYear($unixtime);
		$jewishDate = preg_replace("/\/\d+$/", "", preg_replace("/(?=\b)([1-9])(?=\b)/", "0$1", self::getHebrewDate($unixtime)));

		if (isset($holidays[$jewishDate])) {
			return $holidays[$jewishDate];
		}
		if (isset($weekends[date('w', $unixtime)])) {
			return $weekends[date('w', $unixtime)];
		}

		return HEBCAL_WEEKDAY;
	}

	/**
	 * Get the hebrew date for a given unixtime.
	 * 
	 * @param type $unixtime
	 * @param type $asArray ((optional) default to false)
	 * 
	 * @return type The jewish date as a string in the form "month/day/year"
	 */
	public static function getHebrewDate($unixtime, $asArray = false) {
		$date = jdtojewish(gregoriantojd(date('m', $unixtime), date('d', $unixtime), date('Y', $unixtime)));
		return $asArray ? explode("/", $date) : $date;
	}

	public static function isLeapYear($unixtime) {
		$year = self::getHebrewDate($unixtime, true)[2];
		$yearmod19 = $year % 19;
		return in_array($yearmod19, array(0, 3, 6, 8, 11, 14, 17));
	}

}
