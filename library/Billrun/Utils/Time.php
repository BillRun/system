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
	const MILLISEC_IN_SECOND = 1000;
	const UNLIMITED_DATE = "30 December 2099";
	
	/**
	 * Get the number of milliseconds according to selected resolution.
	 * @param type $seconds - How many seconds to calculate.
	 * @return type Int - number of milliseconds
	 */
	public static function secondsToMilli($seconds) {
		return $seconds * self::MILLISEC_IN_SECOND;
	}

	/**
	 * Get the number of milliseconds according to selected resolution.
	 * @param type $minutes - How many seconds to calculate.
	 * @return type Int - number of milliseconds
	 */
	public static function minutesToMilli($minutes) {
		return static::minutesToSeconds($minutes) * self::MILLISEC_IN_SECOND;
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
		return static::hoursToSeconds($hours) * self::MILLISEC_IN_SECOND;
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
		return static::daysToSeconds($days) * self::MILLISEC_IN_SECOND;
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
		return static::weeksToSeconds($weeks) * self::MILLISEC_IN_SECOND;
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
		return static::monthsToSeconds($months) * self::MILLISEC_IN_SECOND;
	}
	
	/**
	 * Merge overlapping intervals
	 * 
	 * @param array $intervals - every element must have from and to fields
	 * @return array of intervals (objects with from and to) sorted by from field
	 */
	public static function mergeTimeIntervals($intervals, $fromField = 'from', $toField = 'to') {
 		if (empty($intervals)) {
			return [];
		}

		// Create an empty stack of intervals 
		$intervalsStack = [];

		// sort the intervals in increasing order of start time 
		self::sortTimeIntervals($intervals, $fromField);

		// push the first interval to stack 
		array_unshift($intervalsStack, $intervals[0]);

		// Start from the next interval and merge if necessary 
		for ($i = 1; $i < count($intervals); $i++) {
			// get interval from stack top
			$top = current($intervalsStack);

			if ($top[$toField] < $intervals[$i][$fromField]) { // if current interval is not overlapping with stack top, push it to the stack
				array_unshift($intervalsStack, $intervals[$i]);
			} else if ($top[$toField] < $intervals[$i][$toField]) { // Otherwise update the ending time of top if ending of current interval is more
				$top[$toField] = $intervals[$i][$toField];
				array_shift($intervalsStack);
				array_unshift($intervalsStack, $top);
			}
		}
		
		self::sortTimeIntervals($intervalsStack);
		return $intervalsStack;
	}

	/**
	 * Sort given intervals (objects with from and to fields) array
	 * 
	 * @return array of intervals sorted by from field
	 */
	public static function sortTimeIntervals(&$intervals, $fromField = 'from') {
		usort($intervals, function ($item1, $item2) use ($fromField) {
			return $item1[$fromField] >= $item2[$fromField];
		});
	}

}
