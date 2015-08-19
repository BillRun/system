<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Static functions to calculate miliseconds in different resolutions.
 *
 * @author tom
 */
class Billrun_Utils_TimerUtils {

	const MILISEC_IN_MINUTE = 60000;
	const MILISEC_IN_HOUR =  3600000;

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
		return $minutes * self::MILISEC_IN_MINUTE;
	}
	
	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $hours - How many seconds to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function hoursToMili($hours) {
		return $hours * self::MILISEC_IN_HOUR;
	}
	
	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $days - How many seconds to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function daysToMili($days) {
		return $days * 24 * self::MILISEC_IN_HOUR;
	}
	
	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $weeks - How many seconds to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function weeksToMili($weeks) {
		return $weeks * 24 * 7 * self::MILISEC_IN_HOUR;
	}
	
	/**
	 * Get the number of miliseconds according to selected resolution.
	 * @param type $months - How many seconds to calculate.
	 * @return type Int - number of miliseconds
	 */
	static function monthsToMili($months) {
		return $months * 30 * 24 * 7 * self::MILISEC_IN_HOUR;
	}
}
