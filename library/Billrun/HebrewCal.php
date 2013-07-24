<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generic time utils class
 * @TODO extract this class to from the billrun to a seperate library
 * @package  Billrun_HebrewTimeUtils
 * @since    0.5
 */
class Billrun_HebrewCal {
	
	public static $STANDARD_YEAR_HOLYDAYS =  array(
													'13/29' => 'shortday',// rush ahshanna evening
													'01/01' => 'holyday',//rush ahshanna
													'01/02' => 'holyday',//rush ahshanna
													//Yum kippur
													'01/09' => 'shortday',//yum kipur evening
													'01/10' => 'holyday',//yum kipur
													//Soukott
													'01/14' => 'shortday',//sukott evening
													'01/15' => 'holyday',//sukott
													'01/16' => 'holyday',//sukott
													'01/17' => 'holyday',//sukott
													'01/18' => 'holyday',//sukott
													'01/19' => 'holyday',//sukott
													'01/20' => 'holyday',//sukott
													'01/21' => 'holyday',//sukott
													'01/22' => 'holyday',//sukott
													//Hanukka
													'03/25' => 'workday',//Hanukka
													'03/26' => 'workday',//Hanukka
													'03/27' => 'workday',//Hanukka
													'03/28' => 'workday',//Hanukka
													'03/29' => 'workday',//Hanukka
													'03/30' => 'workday',//Hanukka
													'04/01' => 'workday',//Hanukka
													//Tu besvaht
													'04/15' => 'workday',//Tu besvaht
													//Purim
													'06/13' => 'shortday',//Purim evening	
													'06/14' => 'holyday',//Purim or...
													//Pesaach
													'08/14' => 'shortday',//Pesaach evening
													'08/15' => 'holyday',//Pesaach
													'08/16' => 'holyday',//Pesaach
													'08/17' => 'holyday',//Pesaach
													'08/18' => 'holyday',//Pesaach
													'08/19' => 'holyday',//Pesaach
													'08/20' => 'holyday',//Pesaach
													'08/21' => 'holyday',//Pesaach
													//Lag Baommer
													'09/18' => 'workday',//Lag Baommer
													//Shavoout
													'10/05' => 'shortday',//shavoout evening
													'10/06' => 'holyday',//shavoout
													//Tu Beahav
													'12/15' => 'workday',//Tu Beahav
												);
	
	public static function getHolydaysForYear($unixtime,$isLeapYear = false, $walledCity = false) {
		$retArr = self::$STANDARD_YEAR_HOLYDAYS;
		list($day, $month, $year) = self::getHebrewDate($unixtime, true);
		if($isLeapYear) {
			//TODO add leap year data
		}

		//handled short Kislev 
		if(cal_days_in_month ( CAL_JEWISH, 3 , (int)$year ) == 29 ) {
			 $retArr['04/02'] = 'workday';//Hanukka
		}
		
		//Purim when you have a wall around you
		if($walledCity ) { 
			unset($retArr['06/13']);
			$retArr['06/14'] = 'shortday';
			$retArr['06/15'] = 'holyday';
		}
		
		return $retArr;
	}


	/**
	 * Check if a certain date is a regular weekday or a weekend/holyday
	 * @param type $unixtime the  date to check for
	 * @return boolean true if the  date is a weekday false otherwise.
	 */
	public static function isRegularWorkday($unixtime) {				
		$dayType = self::getJewishDayType($unixtime);
		return	'weekday' == $dayType || 'workday' == $dayType;
	}
	
	/**
	 * Get the type  of the day a given time  is in.
	 * (defaults to jewish calander)
	 * @param type $unixtime the date to check.
	 * @return string 
	 *			'weekday' => a regular weekday.
	 *			'shortday' =>  a short week day.
	 *			'holyday' => and holyday.
	 *			'weekend' => well weekend.
	 */
	public static function getDayType($unixtime,$weekends = false, $holydays= false) {
		$weekends = $weekends ? $weekends : array('6' => 'weekend');
		$holydays = $holydays ? $holydays : self::getHolydaysForYear($unixtime);
		$jewishDate =  preg_replace("/\/\d+$/","",preg_replace("/(?=\b)([1-9])(?=\b)/","0$1", self::getHebrewDate($unixtime)) ); 		
		print_r($jewishDate.PHP_EOL);
		$ret = 'weekday';
		if( isset($holydays[$jewishDate]) ) {
			$ret = $holydays[$jewishDate];
		} 
		if( $ret != 'holyday' && isset($weekends[ date('w',$unixtime) ])) {
			$ret = $weekends[ date('w',$unixtime) ];
		}
		
		return $ret;
	}
	
	/**
	 * Get the hebrew date for a given unixtime.
	 * @param type $unixtime
	 * @param type $asArray ((optional) default to false)
	 * @return type
	 */
	public static function getHebrewDate($unixtime, $asArray =false) {
		$date = jdtojewish(gregoriantojd( date('m',$unixtime), date('d',$unixtime), date('Y',$unixtime)));
		return $asArray ? split("/",$date) : $date;
	}
}
