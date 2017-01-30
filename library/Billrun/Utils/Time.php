<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Static functions to calculate milliseconds in different resolutions.
 *
 */
class Billrun_Utils_Time {

	const SECONDS_IN_MINUTE = 60;
	const SECONDS_IN_HOUR = 3600;
	const MILLISEC_IN_MINUTE = 60000;
	const MILLISEC_IN_HOUR = 3600000;
	const UNLIMITED_DATE = "30 December 2099";
	
	/**
	 * Get the number of milliseconds according to selected resolution.
	 * @param type $seconds - How many seconds to calculate.
	 * @return type Int - number of milliseconds
	 */
	public static function secondsToMilli($seconds) {
		return $seconds * 1000;
	}

	/**
	 * Get the number of milliseconds according to selected resolution.
	 * @param type $minutes - How many seconds to calculate.
	 * @return type Int - number of milliseconds
	 */
	public static function minutesToMilli($minutes) {
		return static::minutesToSeconds($minutes) * 1000;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $minutes - How many seconds to calculate.
	 * @return type Int - number of seconds
	 */
	public static function minutesToSeconds($minutes) {
		return $minutes * self::SECONDS_IN_MINUTE;
	}

	/**
	 * Get the number of milliseconds according to selected resolution.
	 * @param type $hours - How many hours to calculate.
	 * @return type Int - number of milliseconds
	 */
	public static function hoursToMilli($hours) {
		return static::hoursToSeconds($hours) * 1000;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $hours - How many hours to calculate.
	 * @return type Int - number of seconds
	 */
	public static function hoursToSeconds($hours) {
		return $hours * self::SECONDS_IN_HOUR;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $days - How many days to calculate.
	 * @return type Int - number of seconds
	 */
	public static function daysToSeconds($days) {
		return $days * 24 * self::SECONDS_IN_HOUR;
	}

	/**
	 * Get the number of milliseconds according to selected resolution.
	 * @param type $days - How many days to calculate.
	 * @return type Int - number of milliseconds
	 */
	public static function daysToMilli($days) {
		return static::daysToSeconds($days) * 1000;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $weeks - How many weeks to calculate.
	 * @return type Int - number of seconds
	 */
	public static function weeksToSeconds($weeks) {
		return $weeks * 24 * 7 * self::SECONDS_IN_HOUR;
	}

	/**
	 * Get the number of milliseconds according to selected resolution.
	 * @param type $weeks - How many weeks to calculate.
	 * @return type Int - number of milliseconds
	 */
	public static function weeksToMilli($weeks) {
		return static::weeksToSeconds($weeks) * 1000;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $months - How many months to calculate.
	 * @return type Int - number of seconds
	 */
	public static function monthsToSeconds($months) {
		return $months * 30 * 24 * 7 * self::SECONDS_IN_HOUR;
	}

	/**
	 * Get the number of milliseconds according to selected resolution.
	 * @param type $months - How many seconds to calculate.
	 * @return type Int - number of milliseconds
	 */
	public static function monthsToMilli($months) {
		return static::monthsToSeconds($months) * 1000;
	}

}
