<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generic util class
 *
 * @package  Util
 * @since    0.5
 */
class Billrun_Util {

	/**
	 * method to filter user input
	 * 
	 * @param mixed $input Value to filter
	 * @param int $filter The ID of the filter to apply. The manual page lists the available filters
	 * @param mixed $option [Optional] Associative array of options or bitwise disjunction of flags.
	 * 
	 * @return mixed the filtered data, or FALSE if the filter fails
	 * 
	 * @see http://www.php.net/manual/en/function.filter-var.php
	 * @see http://www.php.net/manual/en/filter.filters.php
	 */
	public static function filter_var($input, $filter = FILTER_DEFAULT, $options = array()) {
		return filter_var($input, $filter, $options);
	}

	/**
	 * method to receive full datetime of last billrun time
	 * 
	 * @param boolean $return_timestamp set this on if you need timestamp instead of string
	 * @param int $dayofmonth the day of the month require to get; if omitted return config value
	 * @return mixed date string of format YYYYmmddHHmmss or int timestamp 
	 */
	public static function getLastChargeTime($return_timestamp = false, $dayofmonth = null) {
		if (!$dayofmonth) {
			$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25);
		}
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

		if ($depth == 0 || !is_array($arrays)) {
			return $arrays;
		}
		//	print_r($arrays);
		$retArr = array();
		foreach ($arrays as $subKey => $subArray) {
			if ($key) {
				$retArr[$subKey] = array($key => Billrun_Util::joinSubArraysOnKey($subArray, $depth - 1, $subKey));
			} else {
				$swappedArr = Billrun_Util::joinSubArraysOnKey($subArray, $depth - 1, $subKey);
				if (is_array($swappedArr)) {
					$retArr = array_merge_recursive($retArr, $swappedArr);
				} else {
					$retArr[$subKey] = $swappedArr;
				}
			}
		}
		return $retArr;
	}

	/**
	 * generate array stamp
	 * @param array $ar array to generate the stamp from
	 * @return string the array stamp
	 */
	public static function generateArrayStamp($ar) {
		return md5(serialize($ar));
	}

	/**
	 * generate current time from the base time date format
	 * 
	 * @return string the current date time formatted by the system default format
	 */
	public static function generateCurrentTime() {
		return date(Billrun_Base::base_dateformat);
	}

	/**
	 * Get the first value that match to a regex
	 * @param $pattern the regex pattern
	 * @param $subject the string to run the regex on.
	 * @param $resIndex (optional) the index to get , of the returned regex results.
	 * @return the first regex group  that match ed or false if there was no match
	 */
	public static function regexFirstValue($pattern, $subject, $resIndex = 1) {
		$matches = array();
		if (!preg_match(($pattern ? $pattern : "//"), $subject, $matches)) {
			return FALSE;
		}
		return (isset($matches[$resIndex])) ? $matches[$resIndex] : FALSE;
	}

	/**
	 * method to convert short datetime (20090213145327) into ISO-8601 format (2009-02-13T14:53:27+01:00)
	 * the method can be relative to timezone offset
	 * 
	 * @param string $datetime the datetime. can be all input types of strtotime function
	 * @param type $offset the timezone offset +/-xxxx or +/-xx:xx
	 * 
	 * @return MongoDate MongoObject
	 */
	public static function dateTimeConvertShortToIso($datetime, $offset = '+00:00') {
		if (strpos($offset, ':') === FALSE) {
			$tz_offset = substr($offset, 0, 3) . ':' . substr($offset, -2);
		} else {
			$tz_offset = $offset;
		}
		$date_formatted = str_replace(' ', 'T', date(Billrun_Base::base_dateformat, strtotime($datetime))) . $tz_offset; // Unnecessary code?
		$datetime = strtotime($date_formatted);
		return $datetime;
	}

	public static function startsWith($haystack, $needle) {
		return !strncmp($haystack, $needle, strlen($needle));
	}

	/**
	 * method to receive billrun key by date
	 * 
	 * @param int $timestamp a unix timestamp
	 * @param int $dayofmonth the day of the month require to get; if omitted return config value
	 * @return string date string of format YYYYmm
	 */
	public static function getBillrunKey($timestamp, $dayofmonth = null) {
		if (!$dayofmonth) {
			$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25);
		}
		$format = "Ym";
		if (date("d", $timestamp) < $dayofmonth) {
			$key = date($format, $timestamp);
		} else {
			$key = date($format, strtotime('+1 day', strtotime('last day of this month', $timestamp)));
		}
		return $key;
	}

	public static function getFollowingBillrunKey($billrun_key) {
		$datetime = $billrun_key . "01000000";
		$month_later = strtotime('+1 month', strtotime($datetime));
		$ret = date("Ym", $month_later);
		return $ret;
	}

	public static function getPreviousBillrunKey($billrun_key) {
		$datetime = $billrun_key . "01000000";
		$month_later = strtotime('-1 month', strtotime($datetime));
		$ret = date("Ym", $month_later);
		return $ret;
	}

	/**
	 * convert corrency.  
	 * (this  should  be change to somthing more dynamic)
	 * @param type $value the value to convert.
	 * @param type $from the currency to conver from.
	 * @param type $to the currency to convert to.
	 * @return float the converted value.
	 */
	public static function convertCurrency($value, $from, $to) {
		$conversion = array(
			'ILS' => 1,
			'EUR' => 4.78,
			'USD' => 3.68,
		);

		return $value * ($conversion[$from] / $conversion[$to]);
	}

	/**
	 * returns the end timestamp of the input billing period
	 * @param type $billrun_key
	 * @return type int
	 */
	public static function getEndTime($billrun_key) {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25);
		$datetime = $billrun_key . $dayofmonth . "000000";
		return strtotime('-1 second', strtotime($datetime));
	}

	/**
	 * returns the start timestamp of the input billing period
	 * @param type $billrun_key
	 * @return type int
	 */
	public static function getStartTime($billrun_key) {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25);
		$datetime = $billrun_key . $dayofmonth . "000000";
		return strtotime('-1 month', strtotime($datetime));
	}

	/**
	 * 
	 * @param type $timestamp
	 * @return real the VAT at the current timestamp
	 */
	public static function getVATAtDate($timestamp) {
		$mongo_date = new MongoDate($timestamp);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		return $rates_coll
				->query('key', 'VAT')
				->lessEq('from', $mongo_date)
				->greaterEq('to', $mongo_date)
				->cursor()->setReadPreference(Billrun_Factory::config()->getConfigValue('read_only_db_pref'))->current()->get('vat');
	}

	public static function isTimestamp($timestamp) {
		return ((string) (int) $timestamp === strval($timestamp)) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~PHP_INT_MAX);
	}

	public static function setFileModificationTime($received_path, $timestamp) {
		return touch($received_path, $timestamp);
	}

	/**
	 * convert bytes to requested format
	 * if no format supply will take the format that is closet to the bytes
	 * 
	 * @param string $bytes
	 * @param string $unit
	 * @param int $decimals
	 * @return string size in requested format
	 */
	public static function byteFormat($bytes, $unit = "", $decimals = 2, $includeUnit = false) {
		$units = array('B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4,
			'PB' => 5, 'EB' => 6, 'ZB' => 7, 'YB' => 8);

		$value = 0;
		if ($bytes > 0) {
			// Generate automatic prefix by bytes 
			// If wrong prefix given, search for the closest unit
			if (!array_key_exists($unit, $units)) {
				$pow = floor(log($bytes) / log(1024));
				$unit = array_search($pow, $units);
			}

			// Calculate byte value by prefix
			$value = ($bytes / pow(1024, floor($units[$unit])));
		}

		// If decimals is not numeric or decimals is less than 0 
		// then set default value
		if (!is_numeric($decimals) || $decimals < 0) {
			$decimals = 2;
		}

		// Format output
		if (!empty($value)) {
			if ($includeUnit) {
				return number_format($value, $decimals) . $unit;
			}
			return number_format($value, $decimals);
		}

		return FALSE;
	}
	
	/**
	 * convert seconds to requested format
	 * 
	 * @param string $bytes
	 * 
	 * @return string size in requested foramt
	 * 
	 * 60 sec => 1 min
	 * 10 sec => 10 sec
	 * 3400 sec => X minutes
	 */
	public static function durationFormat($seconds) {
		if ($seconds> 3600) {
			return gmdate('H:i:s', $seconds);
		}
		return gmdate('i:s', $seconds);
	}


	/**
	 * convert megabytes to bytes
	 * @param string $megabytes
	 * @return string size in bytes
	 */
	public static function megabytesToBytesFormat($megabytes) {
		// Format output
		if (!empty($megabytes)) {
			return $megabytes * pow(1024, 2);
		}

		return FALSE;
	}

	public static function sendMail($subject, $body, $recipients, $attachments = array()) {
		$mailer = Billrun_Factory::mailer()->
			setSubject($subject)->
			setBodyText($body);
		//add attachments
		foreach ($attachments as $attachment) {
			$mailer->addAttachment($attachment);
		}
		//set recipents
		foreach ($recipients as $recipient) {
			$mailer->addTo($recipient);
		}
		//sen email
		return $mailer->send();
	}
	
	/**
	 * method to fork process of PHP-Web (Apache/Nginx/FPM)
	 * 
	 * @param String $url the url to open
	 * @param Array $params data sending to the new process
	 * @params Boolean $post use POST to query string else use GET
	 * 
	 * @return Boolean true on success else FALSE
	 */
	public static function forkProcessWeb($url, $params, $post = false, $sleep = 0) {
		$params['fork'] = 1;
		if ($sleep) {
			$params['SLEEP'] = (int) $sleep;
		}
		$forkUrl = self::getForkUrl();
		$querystring = http_build_query($params);
		if (!$post) {
			$cmd = "wget -O /dev/null '" . $forkUrl . $url . "?" . $querystring .
				"' > /dev/null & ";
		} else {
			$cmd = "wget -O /dev/null '" . $forkUrl . $url . "' --post-data '" . $querystring .
				"' > /dev/null & ";
		}

//		echo $cmd . "<br />" . PHP_EOL;
		if (system($cmd) === FALSE) {
			error_log("Can't fork PHP process");
			return false;
		}
		usleep(500000);
		return true;
	}

	/**
	 * method to fork process of PHP-Cli
	 * 
	 * @param String $cmd the command to run
	 * @param String $cmd the command to run
	 * 
	 * @return Boolean true on success else FALSE
	 */
	public static function forkProcessCli($cmd) {
		$syscmd = $cmd ." > /dev/null & ";
		if (system($syscmd) === FALSE) {
			error_log("Can't fork PHP process");
			return false;
		}
		return true;
	}


	/**
	 * method to convert phone number to msisdn
	 * 
	 * @param string $phoneNumber the phone number to convert
	 * @param string $defaultPrefix the default prefix to add
	 * 
	 * @return string phone number in msisdn format
	 */
	public static function msisdn($phoneNumber, $defaultPrefix = null) {
		if (is_null($defaultPrefix)) {
			$defaultPrefix = Billrun_Factory::config()->getConfigValue('billrun.defaultCountryPrefix', 972);
		}
		
		echo $phoneNumber . "<br />" . PHP_EOL;
		//CCNDCSN
		// USA is the only country that have extension with string length of 1
		if (preg_match("/^([1-9]{2,3}|1)[1-9]{1,2}[0-9]{7}$/", $phoneNumber)) {
			return $phoneNumber;
		}
		
		//0NDCSN
		$ret = preg_replace("/^(0)([0-9]{1,2}[0-9]{7})$/", $defaultPrefix . '$2', $phoneNumber);
		if (!empty($ret) && $phoneNumber !== $ret) {
			return $ret;
		}
		
		//NDCSN
		$ret = preg_replace("/^([0-9]{1,2}[0-9]{7})$/", $defaultPrefix . '$1', $phoneNumber);
		if (!empty($ret) && $phoneNumber !== $ret) {
			return $ret;
		}
		
		return $phoneNumber;
	}
}
