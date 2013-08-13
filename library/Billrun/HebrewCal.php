<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generic time utils class
 * @TODO extract this class to from the billrun to a seperate library
 * @package  Billrun_HebrewTimeUtils
 * @since    0.5
 */
class Billrun_HebrewCal {

	public static $STANDARD_YEAR_HOLIDAYS = array(
		// Rosh Hashanah
		'13/29' => 'shortday', // rush ahshanna evening
		'01/01' => 'holiday', //rush ahshanna
		'01/02' => 'holiday', //rush ahshanna
		//Yum kippur
		'01/09' => 'shortday', //yum kipur evening
		'01/10' => 'holiday', //yum kipur
		//Soukott
		'01/14' => 'shortday', //sukott evening
		'01/15' => 'holiday', //sukott
		'01/16' => 'holiday', //sukott
		'01/17' => 'holiday', //sukott
		'01/18' => 'holiday', //sukott
		'01/19' => 'holiday', //sukott
		'01/20' => 'holiday', //sukott
		'01/21' => 'holiday', //sukott
		'01/22' => 'workday', //Simchat Torah
		//Hanukka
		'03/25' => 'workday', //Hanukka
		'03/26' => 'workday', //Hanukka
		'03/27' => 'workday', //Hanukka
		'03/28' => 'workday', //Hanukka
		'03/29' => 'workday', //Hanukka
		'03/30' => 'workday', //Hanukka
		'04/01' => 'workday', //Hanukka
		'04/02' => 'workday', //Hanukka
		//Tu besvaht
		'04/15' => 'workday', //Tu besvaht
		//Purim
		'06/13' => 'workday', //Purim evening
		'06/14' => 'holiday', //Purim or...
		//Pesaach
		'08/14' => 'shortday', //Pesaach evening
		'08/15' => 'holiday', //Pesaach
		'08/16' => 'holiday', //Pesaach
		'08/17' => 'holiday', //Pesaach
		'08/18' => 'holiday', //Pesaach
		'08/19' => 'holiday', //Pesaach
		'08/20' => 'holiday', //Pesaach
		'08/21' => 'holiday', //Pesaach
		// Yom HaShoah
		'08/26' => 'workday', // Yom HaShoah evening
		'08/27' => 'workday', // Yom HaShoah
		// Yom Hazikaron
		'09/03' => 'workday', // Yom Hazikaron evening
		'09/04' => 'workday', // Yom Hazikaron
		// Yom Ha'atzmaut
		'09/05' => 'workday', // Yom Ha'atzmaut
		//Lag Baommer
		'09/17' => 'workday', // Lag Baommer evening
		'09/18' => 'workday', // Lag Baommer
		// Jerusalem day
		'09/28' => 'workday', // Jerusalem day
		//Shavoout
		'10/05' => 'shortday', //shavoout evening
		'10/06' => 'holiday', //shavoout
		//Tu Beahav
		'12/15' => 'workday', //Tu Beahav
	);

	/**
	 * 
	 * @param type $unixtime
	 * @param type $isLeapYear
	 * @param type $walledCity
	 * @param type $isAbroad used to calculate Hag Sheni
	 * @return string
	 */
	public static function getHolidaysForYear($unixtime, $walledCity = false, $isAbroad = false) {
		$retArr = self::$STANDARD_YEAR_HOLIDAYS;
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
	 * @param type $unixtime the  date to check for
	 * @return boolean true if the  date is a weekday false otherwise.
	 */
	public static function isRegularWorkday($unixtime) {
		$dayType = self::getDayType($unixtime);
		return 'weekday' == $dayType || 'workday' == $dayType;
	}

	/**
	 * Get the type  of the day a given time  is in.
	 * (defaults to jewish calander)
	 * @param type $unixtime the date to check.
	 * @return string 
	 * 			'weekday' => a regular weekday.
	 * 			'shortday' =>  a short week day.
	 * 			'holiday' => and holiday.
	 * 			'weekend' => well weekend.
	 */
	public static function getDayType($unixtime, $weekends = false, $holidays = false) {
		$weekends = $weekends ? $weekends : array('6' => 'weekend');
		$holidays = $holidays ? $holidays : self::getHolidaysForYear($unixtime);
		$jewishDate = preg_replace("/\/\d+$/", "", preg_replace("/(?=\b)([1-9])(?=\b)/", "0$1", self::getHebrewDate($unixtime)));
//		print_r($jewishDate . PHP_EOL);
		$ret = 'weekday';
		if (isset($holidays[$jewishDate])) {
			$ret = $holidays[$jewishDate];
		}
		if ($ret != 'holiday' && isset($weekends[date('w', $unixtime)])) {
			$ret = $weekends[date('w', $unixtime)];
		}

		return $ret;
	}

	/**
	 * Get the hebrew date for a given unixtime.
	 * @param type $unixtime
	 * @param type $asArray ((optional) default to false)
	 * @return type The jewish date as a string in the form "month/day/year"
	 */
	public static function getHebrewDate($unixtime, $asArray = false) {
		$date = jdtojewish(gregoriantojd(date('m', $unixtime), date('d', $unixtime), date('Y', $unixtime)));
		return $asArray ? split("/", $date) : $date;
	}

	public static function isLeapYear($unixtime) {
		$year = self::getHebrewDate($unixtime, true)[2];
		$yearmod19 = $year % 19;
		return in_array($yearmod19, array(0, 3, 6, 8, 11, 14, 17));
	}

}