<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * This class represents the billing cycle.
 *
 * @package  DataTypes
 * @since    4
 */
class Billrun_Billingcycle {
	protected $billruKey = null;
	protected $startTime = null;	
	protected $endTime = null;	
	
	/**
	 * returns the end timestamp of the input billing period
	 * @param type $key
	 * @return type int
	 */
	public static function getEndTime($key) {
		if($key == self::$billruKey) {
			return self::$endTime;
		}
		
		self::$billruKey = $key;
		$datetime = self::getDatetime();
		self::$endTime = strtotime($datetime);
		return self::$endTime;
	}

	/**
	 * returns the start timestamp of the input billing period
	 * @param type $key
	 * @return type int
	 */
	public static function getStartTime($key) {
		if($key == self::$billruKey) {
			return self::$startTime;
		}
		
		self::$billruKey = $key;
		$datetime = self::getDatetime();
		self::$startTime = strtotime('-1 month', strtotime($datetime));
		return self::$startTime;
	}
	
	protected static function getDatetime() {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 1);
		return self::$billrunKey . str_pad($dayofmonth, 2, '0', STR_PAD_LEFT) . "000000";
	}
	
	/**
	 * method to receive billrun key by date
	 * 
	 * @param int $timestamp a unix timestamp
	 * @param int $dayofmonth the day of the month require to get; if omitted return config value
	 * @return string date string of format YYYYmm
	 */
	public static function getBillrunKeyByTimestamp($timestamp, $dayofmonth = null) {
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
	
	public static function getFollowingBillrunKey($billrun_key) {
		$datetime = $billrun_key . "01000000";
		$month_later = strtotime('+1 month', strtotime($datetime));
		$ret = date("Ym", $month_later);
		return $ret;
	}

	public static function getPreviousBillrunKey($billrun_key) {
		$datetime = $billrun_key . "01000000";
		$month_before = strtotime('-1 month', strtotime($datetime));
		$ret = date("Ym", $month_before);
		return $ret;
	}
}
