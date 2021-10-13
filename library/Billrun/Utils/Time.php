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
	 * Get intersection of intervals
	 * Will return only intervals that overlaps all intervals
	 * 
	 * @param array $intervals1 - every element must have from and to fields
	 * @param array $intervals2 - every element must have from and to fields
	 * @return array of intervals (objects with from and to) sorted by from field
	 */
	public static function getIntervalsIntersections($intervals1, $intervals2, $fromField = 'from', $toField = 'to') {
 		if (empty($intervals1) || empty($intervals2)) {
			return [];
		}

		$ret = [];
		
		foreach ($intervals1 as $interval1) {
			foreach ($intervals2 as $interval2) {
				if ($interval1[$toField] <= $interval2[$fromField] || $interval1[$fromField] >= $interval2[$toField]) { // no intersection
					continue;
				}
				
				$ret[] = [
					$fromField => max($interval1[$fromField], $interval2[$fromField]),
					$toField => min($interval1[$toField], $interval2[$toField]),
				];
			}
		}
		
		return self::mergeTimeIntervals($ret, $fromField, $toField);
	}
	
	/**
	 * returns intervals from $interval1 that are not in $intervals2
	 * 
	 * @param array $intervals1
	 * @param array $intervals2
	 */
	public static function getIntervalsDifference($intervals1, $intervals2, $fromField = 'from', $toField = 'to') {
		if (empty($intervals1)) {
			return [];
		}
		
		if (empty($intervals2)) {
			return $intervals1;
		}
		
		$froms = [];
		$tos = [];
		
		foreach ($intervals1 as $interval1) {
			$overlaps = false;
			foreach ($intervals2 as $interval2) {
				if ($interval2[$fromField] <= $interval1[$fromField] &&
						$interval2[$toField] >= $interval1[$toField]) { // interval1 completely covered by interval2
					$overlaps = true;
				} else if ($interval1[$fromField] <= $interval2[$fromField] &&
						$interval1[$toField] >= $interval2[$toField]) { // interval2 completely covered by interval1
					$froms[] = $interval1[$fromField];
					$froms[] = $interval2[$toField];
					$tos[] = $interval1[$toField];
					$tos[] = $interval2[$fromField];
					$overlaps = true;
				} else if ($interval1[$fromField] < $interval2[$fromField] &&
						$interval1[$toField] > $interval2[$fromField]) { // interval2 starts inside interval1
					$froms[] = $interval1[$fromField];
					$tos[] = $interval2[$fromField];
					$overlaps = true;
				} else if ($interval1[$fromField] < $interval2[$toField] &&
						$interval1[$toField] > $interval2[$toField]) { // interval2 ends inside interval1
					$froms[] = $interval2[$toField];
					$tos[] = $interval1[$toField];
					$overlaps = true;
				}
			}
			
			if (!$overlaps) {
				$froms[] = $interval1[$fromField];
				$tos[] = $interval1[$toField];
			}
		}
		
		$ret = [];
		$froms = array_unique($froms);
		$tos = array_unique($tos);
		sort($froms);
		sort($tos);
		
		foreach ($froms as $i => $from) {
			$to = $tos[$i];
			if ($from == $to) {
				continue;
			}
			
			$ret[] = [
				$fromField => $from,
				$toField => $to,
			];
		}
		
		return self::mergeTimeIntervals($ret, $fromField, $toField);
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
	
	/**
	 * get time in unixtimestamp
	 * 
	 * @param mixed $value
	 */
	public static function getTime($value) {
		if ($value instanceof MongoDate) {
			return $value->sec;
		}
		
		if (is_string($value)) {
			return strtotime($value);
		}
		
		return $value;
	}
	
	/**
	 * Get number of days different between 2 dates
	 * 
	 * @param unixtimestamp $date1
	 * @param unixtimestamp $date2
	 * @param string $roundingType
	 * @return int
	 */
	public static function getDaysDiff($date1, $date2, $roundingType = 'ceil') {
		if ($date1 > $date2) {
			$datediff = $date1 - $date2;
		} else {
			$datediff = $date2 - $date1;
		}
		
		
		$days = $datediff / (60 * 60 * 24);
		switch ($roundingType){
			case 'floor':
				return floor($days);
			case 'round':
				return round($days);
			case 'ceil': 
			default:
				return ceil($days);
		}
	}

	/**
	 * Function calculates inclusive diff. i.e. identical dates return diff > 0 by day amount
	 * @param type $from
	 * @param type $to
	 * @return type
	 */
	public static function getDaysSpanDiff($from, $to, $daySpan) {
		$minDate = new DateTime($from);
		$maxDate = new DateTime($to);

		return (($minDate->diff($maxDate)->days+1) / $daySpan) * ($from > $to ? -1 : 1);
	}

	/**
	 * Function calculates inclusive diff. i.e. identical dates return diff > 0 by day amount with unix timestamps
	 * @param type $from
	 * @param type $to
	 * @return type
	 */
	public static function getDaysSpanDiffUnix($from, $to, $daySpan) {
		$formatedFrom = date(Billrun_Base::base_dateformat,$from);
		$formatedTo = date(Billrun_Base::base_dateformat,$to);

		return static::getDaysSpanDiff($formatedFrom, $formatedTo, $daySpan);
	}

	/**
	 * Function calculates inclusive diff. i.e. identical dates return diff > 0
	 * @param type $from
	 * @param type $to
	 * @return type
	 */
	public static function getDaysSpan($from, $to) {
		$minDate = new DateTime($from);
		$maxDate = new DateTime($to);

		return $minDate->diff($maxDate)->days;
	}


		/**
	 * Function calculates inclusive diff. i.e. identical dates return diff > 0
	 * @param type $from
	 * @param type $to
	 * @return type
	 */
	public static function getMonthsDiff($from, $to) {
		$minDate = new DateTime($from);
		$maxDate = new DateTime($to);
		if ($minDate->format('Y') == $maxDate->format('Y') && $minDate->format('m') == $maxDate->format('m')) {
			return ($maxDate->format('d') - $minDate->format('d') + 1) / $minDate->format('t');
		}
		$yearDiff = $maxDate->format('Y') - $minDate->format('Y');
		switch ($yearDiff) {
			case 0:
				$months = $maxDate->format('m') - $minDate->format('m') - 1;
				break;
			default :
				$months = $maxDate->format('m') + 11 - $minDate->format('m') + ($yearDiff - 1) * 12;
				break;
		}
		return ($minDate->format('t') - $minDate->format('d') + 1) / $minDate->format('t') + $maxDate->format('d') / $maxDate->format('t') + $months;
	}


	/**
	 * Function calculates inclusive diff. i.e. identical dates return diff > 0
	 * @param type $from
	 * @param type $to
	 * @return type
	 */
	public static function getMonthsDiffUnix($from, $to) {
		$formatedFrom = date(Billrun_Base::base_dateformat,$from);
		$formatedTo = date(Billrun_Base::base_dateformat,$to);

		return static::getMonthsDiff($formatedFrom,$formatedTo);
	}
}
