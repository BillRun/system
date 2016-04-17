<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Static functions to calculate miliseconds in different resolutions.
 *
 */
class Billrun_Utils_Time {

	const SECONDS_IN_MINUTE = 60;
	const SECONDS_IN_HOUR = 3600;
	const MILISEC_IN_MINUTE = 60000;
	const MILISEC_IN_HOUR = 3600000;

	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $seconds - How many seconds to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function secondsToMili($seconds) {
		return $seconds * 1000;
	}

	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $minutes - How many seconds to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function minutesToMili($minutes) {
		return static::minutesToSeconds($minutes) * 1000;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $minutes - How many seconds to calculate.
	 * @return type Int - number of seconds
	 */
	static function minutesToSeconds($minutes) {
		return $minutes * self::SECONDS_IN_MINUTE;
	}

	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $hours - How many hours to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function hoursToMili($hours) {
		return static::hoursToSeconds($hours) * 1000;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $hours - How many hours to calculate.
	 * @return type Int - number of seconds
	 */
	static function hoursToSeconds($hours) {
		return $hours * self::SECONDS_IN_HOUR;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $days - How many days to calculate.
	 * @return type Int - number of seconds
	 */
	static function daysToSeconds($days) {
		return $days * 24 * self::SECONDS_IN_HOUR;
	}

	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $days - How many days to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function daysToMili($days) {
		return static::daysToSeconds($days) * 1000;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $weeks - How many weeks to calculate.
	 * @return type Int - number of seconds
	 */
	static function weeksToSeconds($weeks) {
		return $weeks * 24 * 7 * self::SECONDS_IN_HOUR;
	}

	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $weeks - How many weeks to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function weeksToMili($weeks) {
		return static::weeksToSeconds($weeks) * 1000;
	}

	/**
	 * Get the number of seconds according to selected resolution.
	 * @param type $months - How many months to calculate.
	 * @return type Int - number of seconds
	 */
	static function monthsToSeconds($months) {
		return $months * 30 * 24 * 7 * self::SECONDS_IN_HOUR;
	}

	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $months - How many seconds to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function monthsToMili($months) {
		return static::monthsToSeconds($months) * 1000;
	}

}
