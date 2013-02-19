<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generic util class
 *
 * @package  Util
 * @since    1.0
 */
class Billrun_Util {

	public static function getLastChargeTime($return_timestamp = false) {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25);
		$format = "Ym" . $dayofmonth . "000000";
		if (date("d") >= $dayofmonth) {
			$time = date($format);
		} else {
			$time = date($format, strtotime('-1 month'));
		}
		if ($return_timestamp) {
			return strtotime($time);
		}
		return $time;
	}
	
	
	public static function joinSubArraysOnKey($arrays, $depth = 1, $key = false) {

		if($depth == 0 || !is_array($arrays)) {return $arrays;}
	//	print_r($arrays);
		$retArr = array();		
		foreach($arrays as $subKey => $subArray) {
			if($key) {
					$retArr[$subKey] = array( $key => Billrun_Util::joinSubArraysOnKey($subArray, $depth-1, $subKey));
				} else {
				$swappedArr = Billrun_Util::joinSubArraysOnKey($subArray, $depth-1, $subKey);
				if(is_array($swappedArr)) {
					$retArr = array_merge_recursive($retArr,$swappedArr);
				} else {
					$retArr[$subKey] = $swappedArr;
				}
			}
		}
		return $retArr;
	}
	
	public static function generateStamp($ar) {
		return md5(serialize($ar));
	}
	
	public static function generateCurrentTime() {
		return date(Billrun_Base::base_dateformat);
	}

}