<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the billing cycle.
 *
 * @package  DataTypes
 * @since    4
 * @todo Create unit tests for this module
 */
class Billrun_Billingcycle {
	protected static $billruKey = null;
	protected static $startTime = null;	
	protected static $endTime = null;	
	
	/**
	 * returns the end timestamp of the input billing period
	 * @param type $key
	 * @return type int
	 */
	public static function getEndTime($key) {
		if(!$key) {
			return null;
		}
		if(self::$endTime && $key == self::$billruKey) {
			return self::$endTime;
		}
		
		self::$billruKey = $key;
		$datetime = self::getDatetime();
		self::$endTime = strtotime($datetime);
		self::$startTime = strtotime('-1 month', strtotime($datetime));
		return self::$endTime;
	}

	/**
	 * returns the start timestamp of the input billing period
	 * @param type $key
	 * @return type int
	 */
	public static function getStartTime($key) {
		if(!$key) {
			return null;
		}
		
		if(self::$startTime && $key == self::$billruKey) {
			return self::$startTime;
		}
		
		self::$billruKey = $key;
		$datetime = self::getDatetime();
		self::$endTime = strtotime($datetime);
		self::$startTime = strtotime('-1 month', strtotime($datetime));
		return self::$startTime;
	}
	
	/**
	 * Return the date constructed from the current billrun key
	 * @return string
	 */
	protected static function getDatetime() {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 1);
		return self::$billruKey . str_pad($dayofmonth, 2, '0', STR_PAD_LEFT) . "000000";
	}
	
	/**
	 * method to receive billrun key by date
	 * 
	 * @param int $timestamp a unix timestamp, if set to null, use current time
	 * @param int $dayofmonth the day of the month require to get; if omitted return config value
	 * @return string date string of format YYYYmm
	 */
	public static function getBillrunKeyByTimestamp($timestamp=null, $dayofmonth = null) {
		if($timestamp === null) {
			$timestamp = time();
		}
		
		if (!$dayofmonth) {
			$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 1);
		}
		$format = "Ym";
		if (date("d", $timestamp) < $dayofmonth) {
			$key = date($format, $timestamp);
		} else {
			$key = date($format, strtotime('+1 day', strtotime('last day of this month', $timestamp)));
		}
		return $key;
	}

	/**
	 * returns the end timestamp of the input billing period
	 * @param date $date
	 */
	public static function getBillrunEndTimeByDate($date) {
		$dateTimestamp = strtotime($date);
		$billrunKey = self::getBillrunKeyByTimestamp($dateTimestamp);
		return self::getEndTime($billrunKey);
	}

	/**
	 * returns the start timestamp of the input billing period
	 * @param date $date
	 */
	public static function getBillrunStartTimeByDate($date) {
		$dateTimestamp = strtotime($date);
		$billrunKey = self::getBillrunKeyByTimestamp($dateTimestamp);
		return self::getStartTime($billrunKey);
	}
	
	/**
	 * Get the next billrun key
	 * @param string $key - Current key
	 * @return string The following key
	 */
	public static function getFollowingBillrunKey($key) {
		$datetime = $key . "01000000";
		$month_later = strtotime('+1 month', strtotime($datetime));
		$ret = date("Ym", $month_later);
		return $ret;
	}

	/**
	 * Get the previous billrun key
	 * @param string $key - Current key
	 * @return string The previous key
	 */
	public static function getPreviousBillrunKey($key) {
		$datetime = $key . "01000000";
		$month_before = strtotime('-1 month', strtotime($datetime));
		$ret = date("Ym", $month_before);
		return $ret;
	}
}
