<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the autorenew
 *
 * @package  Util
 * @since    4.1
 */
class Billrun_Utils_Autorenew {

	public static function getNextRenewDate($from, $retMongoDate = true, $relative_datetime = null) {
		if (is_null($relative_datetime)) { // default is current time
			$relative_datetime = time();
		}
		$from_dom = (int) date('d', $from);
		$current_dom = (int) date('d', $relative_datetime);
		$current_num_of_days = date('t', $relative_datetime);
		$origin_from_dom = $from_dom;
		
		if ($from_dom > $current_num_of_days) {
			$from_dom = $current_num_of_days;
		}
		
		if ($current_dom < $from_dom && $from_dom <= $current_num_of_days) { // check if we have the next renew date in the current month in the next following days
			 $ret_ts = strtotime(date('Y-m-' . self::pad_date_part($from_dom), $relative_datetime));
		} else {
			$first_day_of_next_month = strtotime('tomorrow', strtotime('last day of this month', $relative_datetime));
			$last_day_of_next_month = strtotime('last day of this month', $first_day_of_next_month); // this will be the last day of next month midnight
			$last_day_of_next_month_dom = (int) date('d', $last_day_of_next_month);
			if ($from_dom > $last_day_of_next_month_dom) { // if next month is less than from date need to handle this
				$ret_ts = $last_day_of_next_month;
			} else if ($origin_from_dom <= $last_day_of_next_month_dom) {
				$ret_ts = strtotime(date('Y-m-' . self::pad_date_part($origin_from_dom), $first_day_of_next_month));
			} else {
				$ret_ts = strtotime(date('Y-m-' . self::pad_date_part($from_dom), $first_day_of_next_month));
			}
		}

		if ($retMongoDate) {
			return new MongoDate($ret_ts);
		}
		return $ret_ts;
	}
	
	protected static function pad_date_part($date_part) {
		return str_pad($date_part, 2, '0', STR_PAD_LEFT);
	}
	
	public static function countMonths($d1, $d2) {
		$min_date = $next_min_date = min($d1, $d2);
		$max_date = max($d1, $d2);
		$count = 0;
		$source_dom = date('d', $min_date);
		while ($min_date < $max_date && $count++ < 9999) { // second arg avoid infinite loop
//			print $count . " " . date('Y-m-d H:i:s', $min_date) . "<br />" . PHP_EOL;
			$next_min_date = self::getNextRenewDate($min_date, false, $min_date);
			if (date('d', $next_min_date) != $source_dom && date('t', $next_min_date) >= $source_dom) {
				$min_date = strtotime(date('Y-m-' . $source_dom . ' H:i:s' , $next_min_date));
			} else {
				$min_date = $next_min_date;
			}
		}
		return $count;
	}
	
}
