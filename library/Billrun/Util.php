<?php

class Util 
{
	public static function getLastChargeTime($return_timestamp = false) {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day',25);
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
}