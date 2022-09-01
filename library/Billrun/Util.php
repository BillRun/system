<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing generic util class
 *
 * @package  Util
 * @since    0.5
 */
class Billrun_Util {
	public static $dataUnits = array(
		'B' => 0, 
		'KB' => 1, 
		'MB' => 2, 
		'GB' => 3, 
		'TB' => 4,
		'PB' => 5, 
		'EB' => 6, 
		'ZB' => 7, 
		'YB' => 8
	);
	
	public static $timeUnits = array(
		"second" => 1,
		"minute" => 60,
		"hour" => 3600, // 60 * 60
		"day" => 86400, // 24 * 60 * 60
		"week" => 604800, // 7 * 24 * 60 * 60
		"year" => 220752000, // 365 * 7 * 24 * 60 * 60
	);

	
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
	public static function generateArrayStamp($ar, $filter = array()) {
		
		return md5(serialize(empty($filter) ? $ar : array_intersect_key($ar, array_flip($filter))));
	}
        
    /**
	 * generate array  stamp only  for specific field  within the array
	 * @param array $ar array to generate the stamp from
	 * @return string the array stamp
	 */
	public static function generateFilteredArrayStamp( $ar, $filter = FALSE ) {
		$releventKeys = !empty($filter) ?  array_flip( $filter ) : FALSE ;
		$filteredArray = $releventKeys ? array_intersect_key( $ar, $releventKeys  ) : $ar ;
		return Billrun_Util::generateArrayStamp($filteredArray);
	}

	/**
	 * generate a random number of reqested length based on microtime
	 * @param int $length length of the random number
	 * @return number
	 */
	public static function generateRandomNum($length = 19) {
		$milliseconds = round(microtime(true) * 10000);
		$l = strlen($milliseconds);
		if ($l >= $length) {
			return substr($milliseconds, $l - $length, $length);
		}

		$start = pow(10, $length - $l - 1);
		$additional = rand($start, $start * 10 - 1);
		return $additional . $milliseconds;
	}

	/**
	 * generate current time from the base time date format
	 * 
	 * @return string the current date time formatted by the system default format
	 */
	public static function generateCurrentTime() {
		return date(Billrun_Base::base_datetimeformat);
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
	 * @return int a timestamp on success, false on failure
	 */
	public static function dateTimeConvertShortToIso($datetime, $offset = '+00:00') {
		if (strpos($offset, ':') === FALSE) {
			$tz_offset = substr($offset, 0, 3) . ':' . substr($offset, -2);
		} else {
			$tz_offset = $offset;
		}
		$date_formatted = str_replace(' ', 'T', date(Billrun_Base::base_datetimeformat, strtotime($datetime))) . $tz_offset; // Unnecessary code?
		$ret = strtotime($date_formatted);
		return $ret;
	}

	/**
	 * method to check if string (needle) is starts with another string (haystack)
	 * 
	 * @param string $haystack the string to search in
	 * @param string $needle the searched string
	 * 
	 * @return boolean return true if haystack starts with needle
	 * 
	 * @internal strncmp is faster as twice than substr
	 */
	public static function startsWith($haystack, $needle) {
		return !strncmp($haystack, $needle, strlen($needle));
	}
	
	/**
	 * method to remove prefix from string
	 * 
	 * @param string $str the string to remove prefix
	 * @param string $prefix the prefix
	 * @return string the $str without prefix
	 */
	public static function removePrefix($str, $prefix) {
		if(0 === strpos($str, $prefix)) {
			$str = substr($str, strlen($prefix));
		}
		return $str;
	}
	
	/**
	 * Recursive group array of object by key(s)
	 * 
	 * @param type $array_of_objects
	 * @param array $keys to group by
	 * @return array of objects grouped by key(s) 
	 */
	public static function groupArrayBy($array_of_objects, $keys) {
		$out = array();
		$key = array_shift($keys);
		foreach ($array_of_objects as $array_object){
			$group_key = $array_object[$key];
			if (!array_key_exists($array_object[$key], $out)) {
				$out[$group_key] = [];
			}
			$out[$group_key][] = $array_object;
		}
		if (!empty($keys)) {
			foreach ($out as $key => $group) {
				$out[$key] = self::groupArrayBy($group, $keys);
			}
		}
		return $out;
	}
	
	/**
	 * Returns a readable date from billrun key.
	 * example: converts "201607" to : "July 2016"
	 * 
	 * @param type $billrunKey
	 * @return type
	 */
	public static function billrunKeyToReadable($billrunKey) {
		$cycleData = new Billrun_DataTypes_CycleTime($billrunKey);
		return date('F Y', $cycleData->start());
	}
	
	/**
	 * Returns a readable date from billrun key.
	 * example: converts "201607" to : "July 2016"
	 * 
	 * @param type $billrunKey
	 * @param string $format - returned date format
	 * @param string $invoicing_day - custom invoicing day - in case multi day cycle system's mode on.
	 * @return type
	 */
	public static function billrunKeyToPeriodSpan($billrunKey, $format, $invoicing_day = null) {
		if (Billrun_Factory::config()->isMultiDayCycle() && empty($invoicing_day)) {
			$invoicing_day = Billrun_Factory::config()->getConfigChargingDay();
		} 
		$cycleData = new Billrun_DataTypes_CycleTime($billrunKey, $invoicing_day);
		return date($format, $cycleData->start()) .' - '. date($format, $cycleData->end()-1);
	}

	/**
	 * convert corrency.  
	 * (this  should  be change to somthing more dynamic)
	 * @param type $value the value to convert.
	 * @param type $from the currency to conver from.
	 * @param type $to the currency to convert to.
	 * @return float the converted value.
	 * @deprecated since version 2.5
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
	 * method to get VAT cost on specific datetime
	 * 
	 * @param int $timestamp datetime in unix timestamp format
	 * 
	 * @return float the VAT at the current timestamp
	 * @todo move to specific VAT object
	 * @deprecated since version 5.9 - use Tax calculator
	 */
	public static function getVATAtDate($timestamp) {
		return Billrun_Rates_Util::getVat(0.17, $timestamp);
	}

	/**
	 * method to check if input is timestamp
	 * @param mixed $timestamp
	 * @return return true if input is timestamp else false
	 */
	public static function isTimestamp($timestamp) {
		return ((string) (int) $timestamp === strval($timestamp)) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~PHP_INT_MAX);
	}

	/**
	 * method to set modified time of file
	 * 
	 * @param string $received_path the file full path
	 * @param int $timestamp the timestamp of the file
	 * 
	 * @return true on success else false
	 * 
	 * @todo move to Receiver object
	 */
	public static function setFileModificationTime($received_path, $timestamp) {
		return touch($received_path, $timestamp);
	}

	/**
	 * convert bytes to requested format
	 * if no format supply will take the format that is closet to the bytes
	 * 
	 * @param string $bytes bytes to format
	 * @param string $unit unit to align to
	 * @param int $decimals how many decimals after dot
	 * @param boolean $includeUnit flag to incdicate if to include unit in return value
	 * @param string $dec_point sets the separator for the decimal point
	 * @param string $thousands_sep sets the thousands separator
	 * 
	 * @return string size in requested format
	 */
	public static function byteFormat($bytes, $unit = "", $decimals = 2, $includeUnit = false, $dec_point = ".", $thousands_sep = ",") {
		$unit = strtoupper($unit);
		$value = 0;
		if ($bytes != 0) {
			// Generate automatic prefix by bytes 
			// If wrong prefix given, search for the closest unit
			if (!array_key_exists($unit, self::$dataUnits)) {
				$pow = floor(log(abs($bytes)) / log(1024));
				$unit = array_search($pow, self::$dataUnits);
			}

			// Calculate byte value by prefix
			$value = ($bytes / pow(1024, floor(self::$dataUnits[$unit])));
		}

		if ($unit == 'B') {
			$decimals = 0;
		} else if (!is_numeric($decimals) || $decimals < 0) {
			// If decimals is not numeric or decimals is less than 0 
			// then set default value
			$decimals = 2;
		}

		// Format output
		if (!empty($value)) {
			$number = number_format($value, $decimals, $dec_point, $thousands_sep);
			if ($includeUnit) {
				return $number . $unit;
			}
			return $number;
		}

		return  $includeUnit ? 0 . $unit : 0;
	}

	/**
         * convert KB/MB/GB/TB/PB/EB/ZB/YB to bytes
         * @param string $unitSizeToByte 
         * @param string $convertToOtherUnit use when we want to return different unit size
         * @param int $decimals 
         * @param string $dec_point sets the separator for the decimal point
         * @return int of bytes
         */
        public static function computerUnitToBytes($unitSizeToByte = '0B', $convertToOtherUnit = 'B', $decimals = 0 , $dec_point = ".", $thousands_sep = ","){
            $unitSizeAndType = [];
            $pattern = '/(\d+\.\d+|\d+)(\w+)$/';
            preg_match($pattern, $unitSizeToByte, $unitSizeAndType);
            $unitSize = $unitSizeAndType[1];
            $unitType = $unitSizeAndType[2];
            $bytes = 0;
            $powerCalc = self::$dataUnits[$unitType] - self::$dataUnits[$convertToOtherUnit];
            
            if(isset(self::$dataUnits[$unitType]) && !empty($unitSize)){
                if($powerCalc >= 0){
                    $bytes = number_format($unitSize * pow(1024, floor($powerCalc)), $decimals, $dec_point, $thousands_sep );
                }else{
					$decimals = 6;
                    $bytes = number_format($unitSize / pow(1024, floor(abs($powerCalc))), $decimals, $dec_point, $thousands_sep );
                }
            }
            
            return $bytes;
        }
        
	/**
	 * convert seconds to requested format
	 * 
	 * @param string $seconds
	 * @param bool $toMinutesSecondFormat if true returning minutes and second format
	 * @return string size in requested foramt
	 * 
	 * 60 sec => 1 min
	 * 10 sec => 10 sec
	 * 3400 sec => X minutes
	 */
	public static function durationFormat($seconds, $formatSeconds = false) {
		if ($seconds >= 3600) {
			return gmdate('H:i:s', $seconds);
		}
		if ($formatSeconds) {
			return gmdate('i:s', $seconds);
		}
		return $seconds;
	}
	
	/**
	 * method to convert seconds to closest unit or by specific unit
	 * 
	 * @param int $seconds seconds value to convert
	 * @param string $unit the unit to convert (empty to automatically convert to closest unit)
	 * @param int $decimals decimal point
	 * @param bool $includeUnit output unit on return
	 * @param string $round_method method to round with the return value
	 * @param string $decimal_sep the decimal point separator
	 * @param string $thousands_sep the thousands separator
	 * 
	 * @return string the seconds formatted with the specific unit
	 */
	public static function secondFormat($seconds, $unit = "", $decimals = 2, $includeUnit = false, $round_method = 'none', $decimal_sep = ".", $thousands_sep = ",") {
		if (empty($unit) || !array_key_exists($unit, self::$timeUnits)) {
			$units = array_reverse(self::$timeUnits);
			foreach ($units as $k => $v) {
				if ($seconds >= $v) {
					$unit = $k;
					break;
				}
			}
		}
		
		$value = $seconds / self::$timeUnits[$unit];
		
		if ($round_method != 'none' && function_exists($round_method)) {
			$value = call_user_func_array($round_method, array($value));
		}
		
		$number = number_format($value, $decimals, $decimal_sep, $thousands_sep);
		
		if ($includeUnit) {
			return $number . ' ' . $unit . ($value > 1 || $value == 0 ? 's' : '');
		}
		return $number;
		
	}
	
	/**
	 * convert seconds to readable format [English]
	 * 
	 * @param int $seconds seconds to convert
	 * 
	 * @return string readable format
	 */
	public static function durationReadableFormat($seconds) {
		$units = array(
			"year" => 220752000, // 365 * 7 * 24 * 60 * 60
			"week" => 604800, // 7 * 24 * 60 * 60
			"day" => 86400, // 24 * 60 * 60
			"hour" => 3600, // 60 * 60
			"minute" => 60,
			"second" => 1,
		);

		if ($seconds == 0) {
			return "0 seconds";
		}
		$s = array();
		foreach ($units as $name => $div) {
			$quot = intval($seconds / $div);
			if ($quot) {
				$unit = $name;
				if (abs($quot) > 1) {
					$unit .= "s";
				}
				$s[] = $quot . " " . $unit;
				$seconds -= $quot * $div;
			}
		}
		return implode($s, ', ');
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

	public static function sendMail($subject, $body, $recipients, $attachments = array(), $html = false) {
		$mailer = Billrun_Factory::mailer()->setSubject($subject);
		if($html){
			$mailer->setBodyHtml($body, "UTF-8");
		} else {
			$mailer->setBodyText($body);
		}
		//add attachments
		foreach ($attachments as $attachment) {
			$mailer->addAttachment($attachment);
		}
		//set recipents
//		foreach ($recipients as $recipient) {
//			$mailer->addTo($recipient);
//		}
		$mailer->addTo($recipients);
		//sen email
		return $mailer->send();
	}

	public static function getForkUrl() {
		$request = Yaf_Dispatcher::getInstance()->getRequest();
		$protocol = (empty($request->getServer('HTTPS'))) ? 'http' : 'https';
		return $protocol . '://' . $request->get('SERVER_ADDR') . '/' . $request->getBaseUri();
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
		return true;
	}

	/**
	 * Get start time by period given:
	 * "day" - begin of day
	 * "week" - begin of week
	 * "month" - begin of month
	 * "year" - begin of year
	 * 
	 * @param type $period
	 * @return type
	 */
	public static function getStartTimeByPeriod($period = 'day') {
		switch ($period) {
			case ('day'):
				return strtotime("midnight");
			case ('week'):
				return strtotime("last sunday");
			case ('month'):
				return strtotime(date('01-m-Y'));
			case ('year'):
				return strtotime(date('01-01-Y'));
		}

		return time();
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
		if (!defined('STDERR')) {
			define('STDERR', fopen('php://stderr', 'w'));
		}
		$syscmd = $cmd . " > /dev/null & ";
		if (defined('APPLICATION_MULTITENANT') && APPLICATION_MULTITENANT) {
			$syscmd = 'export APPLICATION_MULTITENANT=1 ; ' . $syscmd;
		}
		$descriptorspec = array(
			2 => STDERR,
		);
		Billrun_Factory::log("About to run CLI command: " . $syscmd,Zend_Log::DEBUG);
		$process = proc_open($syscmd, $descriptorspec, $pipes);
		if ($process === FALSE) {
			Billrun_Factory::log('Can\'t execute CLI command',Zend_Log::ERR);
			return false;
		}
		if (proc_close($process) === -1) {
			Billrun_Factory::log('CLI command returned with error ',Zend_Log::ERR);
			return false;
		}
		return true;
	}

	public static function isBillrunKey($billrun_key) {
		return is_string($billrun_key) && Zend_Locale_Format::isInteger($billrun_key) && strlen($billrun_key) == 6 && substr($billrun_key, 4, 2) >= '01' && substr($billrun_key, 4, 2) <= '12';
	}

	/**
	 * Cast an array / string which represents an array.
	 * @param type $ar
	 * @param type $type
	 * @param type $explode
	 * @return type
	 */
	public static function verify_array($ar, $type = null, $explode = ',') {
		if (is_string($ar)) {
			$ar = explode($explode, $ar);
		} elseif (!is_array($ar)) {
			settype($ar, 'array');
		}
		// check if casting required
		if (!is_null($type)) {
			$ar = array_map($type . 'val', $ar);
		}
		return $ar;
	}

	/**
	 * remove all elements that are not string and not numeric
	 * @param array $ar array to verify
	 * @return array
	 */
	public static function array_remove_compound_elements($ar) {
		if (!is_array($ar)) {
			return array();
		}
		return array_filter($ar, function($var) {
			return is_string($var) || is_numeric($var);
		});
	}

	/**
	 * method to convert msisdn to local phone number (remove country extension)
	 * 
	 * @param string $msisdn the phone number to convert
	 * @param string $defaultPrefix the default prefix to add
	 * 
	 * @return string phone number in msisdn format
	 */
	public static function localNumber($msisdn, $defaultPrefix = null) {
		if (is_null($defaultPrefix)) {
			$defaultPrefix = Billrun_Factory::config()->getConfigValue('billrun.defaultCountryPrefix', 972);
		}
		$prefixLength = strlen($defaultPrefix);
		if (substr($msisdn, 0, $prefixLength) != $defaultPrefix) {
			return $msisdn;
		}
		if (substr($msisdn, 0, $prefixLength+1) == $defaultPrefix . '1') {
			$prefix = '';
		} else {
			$prefix = '0';
		}
		return $prefix . substr($msisdn, (-1) * strlen($msisdn) + $prefixLength);
	}

	/**
	 * method to convert phone number to msisdn
	 * 
	 * @param string $phoneNumber the phone number to convert
	 * @param string $defaultPrefix the default prefix to add
	 * @param boolean $cleanLeadingZeros decide if to clean leading zeros on return value
	 * 
	 * @return string phone number in msisdn format
	 */
	public static function msisdn($phoneNumber, $defaultPrefix = null, $cleanLeadingZeros = true) {

		if (empty($phoneNumber)) {
			return $phoneNumber;
		}

		settype($phoneNumber, 'string');

		if ($cleanLeadingZeros) {
			$phoneNumber = self::cleanLeadingZeros($phoneNumber);
		}

		if (is_null($defaultPrefix)) {
			$defaultPrefix = Billrun_Factory::config()->getConfigValue('billrun.defaultCountryPrefix', 972);
		}

		$phoneLength = strlen($phoneNumber);
		$prefixLength = strlen($defaultPrefix);

		if ($phoneLength >= $prefixLength && substr($phoneNumber, 0, $prefixLength) == $defaultPrefix) {
			return $phoneNumber;
		}

		if (self::isIntlNumber($phoneNumber) || $phoneLength > 12) { // len>15 means not msisdn
			return $phoneNumber;
		}

		return $defaultPrefix . $phoneNumber;
	}

	/**
	 * method to check if phone number is intl number or local number base on msisdn standard
	 * 
	 * @param string $phoneNumber the phone number to check
	 * 
	 * @return boolean true in case is international phone number else false
	 */
	public static function isIntlNumber($phoneNumber) {
		$cleanNumber = self::cleanLeadingZeros(self::cleanNumber($phoneNumber));

		//CCNDCSN - First part USA; second non-USA
		if (preg_match("/^(1[2-9]{1}[0-9]{2}|[2-9]{1}[0-9]{1,2}[1-9]{1}[0-9]{0,2})[0-9]{7,9}$/", $cleanNumber)) {
			return true;
		}

		return false;
	}

	/**
	 * method to clean phone number and leave only numeric characters
	 * 
	 * @param string $phoneNumber
	 * 
	 * @return string the clean phone number
	 */
	public static function cleanNumber($phoneNumber) {
		return preg_replace("/[^0-9]/", "", $phoneNumber);
	}

	/**
	 * method to clean leading zero of phone number
	 * 
	 * @param string $number
	 * 
	 * @return string the number without leading zeros
	 */
	public static function cleanLeadingZeros($number) {
		return ltrim($number, "+0");
	}

	/**
	 * utility to reset and initialized fork process
	 * use this method when you open a child fork process with pcntl_fork
	 */
	public static function resetForkProcess() {
		Billrun_Factory::log()->removeWriters('Mail');
		Billrun_Factory::log()->addWriters('Mail');
	}

	/**
	 * method to parse credit row from API
	 * 
	 * @param array $credit_row
	 * 
	 * @return array after filtering and validation
	 * @todo move to parser Object
	 */
	public static function parseCreditRow($credit_row) {
		// @TODO: take to config
		$required_fields = array(
			array('credit_type', 'charge_type'), // charge_type is for backward compatibility
			'amount_without_vat',
			'reason',
			'account_id',
			'subscriber_id',
			'credit_time',
			'service_name',
		);

		// @TODO: take to config
		$optional_fields = array(
			'plan' => array(),
			'vatable' => array('default' => '1'),
			'promotion' => array(),
			'fixed' => array(),
			'additional' => array(),
		);
		$filtered_request = array();

		foreach ($required_fields as $field) {
			$found_field = false;
			if (is_array($field)) {
				foreach ($field as $req) {
					if (isset($credit_row[$req])) {
						if ($found_field) {
							unset($credit_row[$req]); // so the stamp won't be calculated on it.
						} else {
							$filtered_request[$req] = $credit_row[$req];
							$found_field = true;
						}
					}
				}
			} else if (isset($credit_row[$field])) {
				$filtered_request[$field] = $credit_row[$field];
				$found_field = true;
			}
			if (!$found_field) {
				return array(
					'status' => 0,
					'desc' => 'required field(s) missing: ' . print_r($field, true),
				);
			}
		}

		foreach ($optional_fields as $field => $options) {
			if (!isset($credit_row[$field])) {
				if (isset($options['default'])) {
					$filtered_request[$field] = $options['default'];
				}
			} else {
				$filtered_request[$field] = $credit_row[$field];
			}
		}

		if (isset($filtered_request['charge_type'])) {
			$filtered_request['credit_type'] = $filtered_request['charge_type'];
			unset($filtered_request['charge_type']);
		}
		if ($filtered_request['credit_type'] != 'charge' && $filtered_request['credit_type'] != 'refund') {
			return array(
				'status' => 0,
				'desc' => 'credit_type could be either "charge" or "refund"',
			);
		}

		$amount_without_vat = Billrun_Util::filter_var($filtered_request['amount_without_vat'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
		if (!is_numeric($filtered_request['amount_without_vat']) || $amount_without_vat === false) {
			return array(
				'status' => 0,
				'desc' => 'amount_without_vat is not a number',
			);
		} else if ($amount_without_vat == 0) {
			return array(
				'status' => 0,
				'desc' => 'amount_without_vat equal zero',
			);
		} else {
			// TODO: Temporary conversion. Remove it once they send negative values!
			if ($filtered_request['credit_type'] == 'refund' && floatval($amount_without_vat) > 0) {
				$filtered_request['amount_without_vat'] = -floatval($amount_without_vat);
			} else {
				$filtered_request['amount_without_vat'] = floatval($amount_without_vat);
			}
		}

		if (is_string($filtered_request['reason'])) {
			$filtered_request['reason'] = preg_replace('/[^a-zA-Z0-9-_]+/', '_', $filtered_request['reason']); // removes unwanted characters from the string (especially dollar sign and dots)
		} else {
			return array(
				'status' => 0,
				'desc' => 'reason error',
			);
		}

		if (!empty($filtered_request['service_name']) && is_string($filtered_request['service_name'])) {
			$filtered_request['service_name'] = preg_replace('/[^a-zA-Z0-9-_]+/', '_', $filtered_request['service_name']); // removes unwanted characters from the string (especially dollar sign and dots) as they are not allowed as mongo keys
		} else {
			return array(
				'status' => 0,
				'desc' => 'service_name error',
			);
		}

		if (isset($filtered_request['account_id'])) {
			$filtered_request['aid'] = (int) $filtered_request['account_id'];
			unset($filtered_request['account_id']);
		}

		if (isset($filtered_request['subscriber_id'])) {
			$filtered_request['sid'] = (int) $filtered_request['subscriber_id'];
			unset($filtered_request['subscriber_id']);
		}

		if ($filtered_request['aid'] == 0) {
			return array(
				'status' => 0,
				'desc' => 'account id must be positive integers',
			);
		}

		if ($filtered_request['sid'] < 0) {
			return array(
				'status' => 0,
				'desc' => 'subscriber id must be greater or equal to zero',
			);
		}

		$credit_time = new Zend_Date($filtered_request['credit_time']);
		$filtered_request['urt'] = new MongoDate($credit_time->getTimestamp());
		unset($filtered_request['credit_time']);

		$filtered_request['vatable'] = filter_var($filtered_request['vatable'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if (!is_null($filtered_request['vatable'])) {
			$filtered_request['vatable'] = (int) $filtered_request['vatable'];
		} else {
			return array(
				'status' => 0,
				'desc' => 'vatable could be either "0" or "1"',
			);
		}

		$filtered_request['source'] = 'api';
		$filtered_request['usaget'] = $filtered_request['type'] = 'credit';
		ksort($filtered_request);
		$filtered_request['stamp'] = Billrun_Util::generateArrayStamp($filtered_request);

		return $filtered_request;
	}

	/**
	 * method to update service row from API
	 * @param array $service_row
	 * @return $service_row after addition of fields
	 */
	public static function parseServiceRow($service_row, $billrun_key) {
		$service_row['source'] = 'api';
		$service_row['usaget'] = $service_row['type'] = 'service';
		$service_row['urt'] = new MongoDate(Billrun_Billingcycle::getEndTime($billrun_key));
		ksort($service_row);
		$service_row['stamp'] = Billrun_Util::generateArrayStamp($service_row);
		return $service_row;
	}

	/**
	 * Convert associative Array to XML
	 * @param Array $data Associative Array
	 * @param Array $parameters
	 * @return mixed XML string or FALSE if failed
	 */
	public static function arrayToXml($data, $parameters = array()) {
		if (!is_array($data)) {
			return false;
		}
		//Defaults
		$version = !empty($parameters['version']) ? $parameters['version'] : '1.0';
		$encoding = !empty($parameters['encoding']) ? $parameters['encoding'] : 'UTF-8';
		$indent = !empty($parameters['indent']) ? $parameters['indent'] : false;
		$rootElement = !empty($parameters['rootElement']) ? $parameters['rootElement'] : 'root';
		$childElement = !empty($parameters['childElement']) ? $parameters['childElement'] : 'node';

		$xml = new XmlWriter();
		$xml->openMemory();
		$xml->setIndent($indent);
		$xml->startDocument($version, $encoding);
		$xml->startElement($rootElement);
		self::recursiveWriteXmlBody($xml, $data, $childElement);
		$xml->endElement(); //write end element
		return $xml->outputMemory();
	}

	/**
	 * Write XML body nodes
	 * @param object $xml XMLWriter Object
	 * @param array $data Associative Data Array
	 */
	public static function recursiveWriteXmlBody(XMLWriter $xml, $data, $childElement) {
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$key = is_numeric($key) ? $childElement : $key;
				$xml->startElement($key);
				self::recursiveWriteXmlBody($xml, $value, $childElement);
				$xml->endElement();
				continue;
			}
			$key = is_numeric($key) ? $childElement : $key;
			$xml->writeElement($key, $value);
		}
	}

	/**
	 * Returns an array value if it is set
	 * @param mixed $field the array value
	 * @param mixed $defVal the default value to return if $field is not set
	 * @param callable $callback a callback function to run on the field in case it is set and return its value
	 * @return mixed the array value if it is set, otherwise returns $defVal
	 */
	static public function getFieldVal(&$field, $defVal, $callback = false) {
		if (isset($field)) {
			return $callback ? call_user_func($callback, $field) : $field;
		}
		return $defVal;
	}

	/**
	 * method to log failed credit
	 * 
	 * @param array $row row to log
	 * 
	 * @since 2.6
	 */
	public static function logFailedCreditRow($row) {
		$fd = fopen(Billrun_Factory::config()->getConfigValue('credit.failed_credits_file', './files/failed_credits.json'), 'a+');
		fwrite($fd, json_encode($row) . PHP_EOL);
		fclose($fd);
	}

	/**
	 * method to log failed service
	 * 
	 * @param array $row row to log
	 * 
	 * @since 2.8
	 */
	public static function logFailedServiceRow($row) {
		$fd = fopen(Billrun_Factory::config()->getConfigValue('service.failed_credits_file', './files/failed_service.json'), 'a+');
		fwrite($fd, json_encode($row) . PHP_EOL);
		fclose($fd);
	}

	public static function logFailedResetLines($sids, $billrun_key, $invoicing_day = null) {
		$fd = fopen(Billrun_Factory::config()->getConfigValue('resetlines.failed_sids_file', './files/failed_resetlines.json'), 'a+');
		$output = array('sids' => $sids, 'billrun_key' => $billrun_key);
		if (!is_null($invoicing_day)) {
			$output['invoicing_day'] = $invoicing_day;
		}
		fwrite($fd, json_encode($output) . PHP_EOL);
		fclose($fd);
	}

	/**
	 * Get an array of prefixes for a given.
	 * @param string $str the number to get prefixes to.
	 * @return Array the possible prefixes of the number sorted by prefix length in decreasing order.
	 */
	public static function getPrefixes($str) {
		$prefixes = array();
		for ($i = strlen($str); $i > 0; $i--) {
			$prefixes[] = substr($str, 0, $i);
		}
		return $prefixes;
	}

	/**
	 * Make sure that a date start with the full year and make sure it compitibale with a given format.
	 * @param $date the date to make sure is corrcet.
	 * @param $foramt the fromat the date should be in.
	 * @return mixed the fixed date sting if possible or false  if the date couldn't be fixed.
	 */
	public static function fixShortHandYearDate($date, $format = "Y") {
		if (!preg_match('/^' . date($format, strtotime($date)) . '/', $date)) {
			$date = substr(date("Y"), 0, 2) . $date;
		}
		return preg_match('/^' . date($format, strtotime($date)) . '/', $date) ? $date : false;
	}

	/**
	 * method to get current hostname runnning the PHP
	 * 
	 * @return string host name or false when gethostname is not available (PHP 5.2 and lower)
	 */
	public static function getHostName() {
		return function_exists('gethostname') ? @gethostname() : false;
	}
	
	/**
	 * method to get current operating system process id runnning the PHP
	 * 
	 * @return mixed current PHP process ID (int) or false on failure
	 */
	public static function getPid() {
		return function_exists('getmypid') ? @getmypid() : false;
	}

	/**
	 * Return the decimal value from the coded binary representation
	 * @param int $binary
	 * @return int
	 * @todo move to Parser object
	 */
	public static function bcd_decode($binary) {
		return ($binary & 0xF) . ((($binary >> 4) < 10) ? ($binary >> 4) : '' );
	}

	/**
	 * 
	 * @param type $array
	 * @param type $fields
	 * @param type $defaultVal
	 * @return type
	 */
	public static function getNestedArrayVal($array, $fields, $defaultVal = null, $retArr = FALSE) {
		$fields = is_array($fields) ? $fields : explode('.', $fields);
		$rawField = array_shift($fields);
		preg_match("/\[([^\]]*)\]/", $rawField, $attr);
		if (!empty($attr)) {//Allow for  multiple attribute checks
			$attr = explode("=", Billrun_Util::getFieldVal($attr[1], FALSE));
		}
		$field = preg_replace("/\[[^\]]*\]/", "", $rawField);
		$aggregate = $retArr && ($field == '*');
		$keys = ($field != "*") ? array($field) : array_keys($array);

		$retVal = $aggregate ? array() : $defaultVal;
		foreach ($keys as $key) {
			if (isset($array[$key]) && (empty($attr) || isset($array[$key][$attr[0]])) && (!isset($attr[1]) || $array[$key][$attr[0]] == $attr[1] )) {
				if (!$aggregate) {
					$retVal = empty($fields) ? $array[$key] : static::getNestedArrayVal($array[$key], $fields, $defaultVal, $retArr);
					break;
				} else {
					$retVal[] = empty($fields) ? $array[$key] : static::getNestedArrayVal($array[$key], $fields, $defaultVal, $retArr);
				}
			}
		}

		return $retVal;
	}

	/**
	 * method to retrieve internation circuit groups
	 * 
	 * @todo take from db (?) with cache (static variable)
	 */
	public static function getIntlCircuitGroups() {
		return Billrun_Factory::config()->getConfigValue('Rate_Nsn.calculator.intl_cg', array());
	}

	/**
	 * method to retrieve rates that ought to be included for fraud 
	 * @return array of rate refs
	 */
	public static function getIntlRateRefs() {
		$rate_key_list = Billrun_Factory::config()->getConfigValue('Rate_Nsn.calculator.intl_rates', array());
		$query = array("key" => array('$in' => $rate_key_list));
		$ratesmodle = new RatesModel(array("collection" => "rates"));
		$rates = $ratesmodle->getRates($query);
		$rate_ref_list = array();
		$ratesColl = Billrun_Factory::db()->ratesCollection();
		foreach ($rates as $rate) {
			$rate_ref_list[] = $ratesColl->createRefByEntity($rate)['$id']->{'$id'};
		}
		return $rate_ref_list;
	}

	/**
	 * method to retrieve roaming circuit groups
	 * 
	 * @todo take from db (?) with cache (static variable)
	 */
	public static function getRoamingCircuitGroups() {
		return Billrun_Factory::config()->getConfigValue('Rate_Nsn.calculator.roaming_cg', array());
	}

	/**
	 * Send curl request
	 * 
	 * @param string $url full path
	 * @param string $data parameters for the request
	 * @param string $method should be POST or GET
	 * 
	 * @param returnResponse - true - function returns the whole response, false - returns only body.
	 * @return array or FALSE on failure
	 */
	public static function sendRequest($url, $data = array(), $method = Zend_Http_Client::POST, array $headers = array('Accept-encoding' => 'deflate'), $timeout = null, $ssl_verify = null, $returnResponse  = false) {
		if (empty($url)) {
			Billrun_Factory::log("Bad parameters: url - " . $url . " method: " . $method, Zend_Log::ERR);
			return FALSE;
		}

		$method = strtoupper($method);
		if (!defined("Zend_Http_Client::" . $method)) {
			return FALSE;
		}

		$zendMethod = constant("Zend_Http_Client::" . $method);
		$curl = new Zend_Http_Client_Adapter_Curl();
		if (!is_null($timeout)) {
			$curl->setCurlOption(CURLOPT_TIMEOUT, $timeout);
		}
		if (!is_null($ssl_verify)) {
			$curl->setCurlOption(CURLOPT_SSL_VERIFYPEER, $ssl_verify);
		}
		$client = new Zend_Http_Client($url);
		$client->setHeaders($headers);
		$client->setAdapter($curl);
		$client->setMethod($method);

		if (!empty($data)) {
			if (!is_array($data)) {
				$client->setRawData($data);
			} else {
				if ($zendMethod == Zend_Http_Client::POST) {
					$client->setParameterPost($data);
				} else {
					$client->setParameterGet($data);
				}
			}
		}
		$response = null;
		try {
			$urlHost = parse_url($url,  PHP_URL_HOST);
			Billrun_Factory::log("Initiated HTTP request to " . $urlHost, Zend_Log::DEBUG);
			$response = $client->request();
			Billrun_Factory::log("Got HTTP response from " . $urlHost, Zend_Log::DEBUG);
			$output = !$returnResponse ? $response->getBody() : $response;
		} catch (Zend_Http_Client_Exception $e) {
			$output = null;
			if(!$response) {
				$response = $e->getMessage();
			}
		}
		if (empty($output)) {
			Billrun_Factory::log("Bad RPC result: " . print_r($response, TRUE) . " Parameters sent: " . print_r($data, TRUE), Zend_Log::WARN);
			return FALSE;
		}

		return $output;
	}

	/**
	 * Convert array keys to lower case and underscore (Billrun convention)
	 * 
	 * @param array $data
	 */
	public static function parseDataToBillrunConvention($data = array()) {
		if (empty($data)) {
			return array();
		}
		$parsedData = array();
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$value = self::parseDataToBillrunConvention($value);
			}
			$newKey = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
			$parsedData[$newKey] = $value;
		}

		return $parsedData;
	}

	/**
	 * Convert array keys to camel case from Billrun convention
	 * 
	 * @param array $data
	 */
	public static function parseBillrunConventionToCamelCase($data = array()) {
		$parsedData = array();
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$value = self::parseBillrunConventionToCamelCase($value);
			}
			$newKey = self::underscoresToCamelCase($key);
			$parsedData[$newKey] = $value;
		}

		return $parsedData;
	}

	public static function underscoresToCamelCase($string, $capitalizeFirstCharacter = false) {

		$str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
		if (!$capitalizeFirstCharacter) {
			$str[0] = strtolower($str[0]);
		}
		return $str;
	}

	/**
	 * Return an integer based on an input string.
	 * @param string/integer $input - String to convert or integer.
	 * @return Integer value of input, or false if failed.
	 */
	public static function toNumber($input) {
		if ($input === Billrun_Service::UNLIMITED_VALUE) {
			return $input;
		}

		// Check that the input is an integer.
		if (is_int($input)) {
			return $input;
		}

		// Convert to int.
		$temp = (int) $input;

		// If the convertion returns 0 and the input string is not 0 it's an error.
		if (!$temp && $input !== "0") {
			Billrun_Factory::log("Update action did not receive a valid subscriber ID! [" . print_r($input, true) . ']', Zend_Log::ALERT);
			return false;
		}

		return $temp;
	}

	/**
	 * Check if an array is multidimentional.
	 * @param $arr - Array to check.
	 * @return boolean true if multidimentional array.
	 */
	public static function isMultidimentionalArray($arr) {
		return is_array($arr) && count($arr) != count($arr, COUNT_RECURSIVE);
	}

	public static function isAssoc($arr) {
		return is_array($arr) && (array_keys($arr) !== range(0, count($arr) - 1));
	}

	public static function getUsagetUnit($usaget) {
		$units = Billrun_Factory::config()->getConfigValue('usaget.unit');
		return isset($units[$usaget]) ? $units[$usaget] : '';
	}

	/**
	 * Are two numbers equal (up to epsilon)
	 * @param float $number1
	 * @param float $number2
	 * @param float $epsilon positive number
	 * @return boolean
	 */
	public static function isEqual($number1, $number2, $epsilon = 0) {
		return abs($number1 - $number2) < abs($epsilon);
	}

	/**
	 * Floor a decimal
	 * @param float $num
	 * @param float $epsilon positive number
	 * @return float
	 */
	public static function floordec($num, $epsilon) {
		$rounded = round($num);
		return static::isEqual($num, $rounded, $epsilon) ? $rounded : floor($num);
	}

	/**
	 * Ceil a decimal
	 * @param float $num
	 * @param float $epsilon positive number
	 * @return float
	 */
	public static function ceildec($num, $epsilon) {
		$rounded = round($num);
		return static::isEqual($num, $rounded, $epsilon) ? $rounded : ceil($num);
	}

	/**
	 * Calculate the remaining months for an auto renew service
	 * @param int $d1 unix timestamp
	 * @param int $d2 unix timestamp
	 * @return int
	 * @deprecated since version 4.1 please use Billrun_Utils_Autorenew::countMonths
	 * 
	 */
	public static function countMonths($d1, $d2) {
		return Billrun_Utils_Autorenew::countMonths($d1, $d2);
	}

	/**
	 * Check if a key exists in a multidimantional array.
	 * @param array $arr - Array to search for the key.
	 * @param type $key - Value of key to be found.
	 * @return boolean - true if the key is found.
	 */
	public static function multiKeyExists(array $arr, $key) {
		// is in base array?
		if (array_key_exists($key, $arr)) {
			return true;
		}

		// check arrays contained in this array
		foreach ($arr as $element) {
			if (!is_array($element)) {
				continue;
			}

			// Recursively check if the key exists.
			if (self::multiKeyExists($element, $key)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Set a dot seperated array as an assoc array.
	 * @param type $original
	 * @param type $dotArray
	 * @param type $value
	 * @param type $separator
	 * @return type
	 */
	function setDotArrayToArray(&$original, $dotArray, $value, $separator = '.') {
		$parts = explode($separator, $dotArray);
		if (count($parts) <= 1) {
			return $dotArray;
		}

		unset($original[$dotArray]);
		$parts[] = $value;

		$result = self::constructAssocArray($parts);
		foreach ($result as $key => $value) {
			$original[$key] = $value;
		}
	}

	function constructAssocArray($parts) {
		if ((count($parts)) <= 1) {
			return $parts[0];
		}

		$shiftResult = array_shift($parts);
		return array($shiftResult => self::constructAssocArray($parts));
	}

	/**
	 * Return the first value of a multidimentional array.
	 * Example:
	 * [a => [b => [c => 4]]] returns 4.
	 * @param array $array - The array to get the value of.
	 * @return The first value of the array.
	 */
	public function getFirstValueOfMultidimentionalArray($array) {
		if (is_array($array)) {
			$next = reset($array);
			return self::getFirstValueOfMultidimentionalArray($next);
		}

		return $array;
	}
	
	public static function getCallTypes() {
		return array_values(Billrun_Factory::config()->getConfigValue('realtimeevent.callTypes', array('call', 'video_call')));
	}

	
	public static function getBillRunPath($path) {
		if (empty($path) || !is_string($path)) {
			return FALSE;
		}
		if ($path[0] == DIRECTORY_SEPARATOR) {
			return $path;
		}
		return APPLICATION_PATH . DIRECTORY_SEPARATOR . $path;
	}

	/**
	 * Get the shared folder path of the input path.
	 * @param string $path - Path to convert to relative shared folder path.
	 * @param boolean $strict - If true, and the path is a root folder, we return
	 * the absoulute path, not the shared folder path! False by default.
	 * @return string Relative file path in the shared folder.
	 * @TODO: Add validation that if the input $path is already in the shared folder, return just the path.
	 */
	public static function getBillRunSharedFolderPath($path, $strict=false) {
		if (empty($path) || !is_string($path)) {
			return FALSE;
		}
		if ($strict && ($path[0] == DIRECTORY_SEPARATOR)) {
			return $path;
		}
		return  APPLICATION_PATH . DIRECTORY_SEPARATOR . Billrun_Factory::config()->getConfigValue('shared_folder', 'shared') . DIRECTORY_SEPARATOR . Billrun_Factory::config()->getTenant() . DIRECTORY_SEPARATOR . $path;
	}
	
	
		/**
	 * Return rounded amount for charging
	 * @param float $amount
	 * @return float
	 */
	public static function getChargableAmount($amount) {
		return number_format($amount, 2, '.', '');
	}

	public static function generateHash($aid, $key){
		return md5($aid . $key);
	}
	
	public static function isValidCustomLineKey($jsonKey) {
		if (strpos($jsonKey, '.') === FALSE) {
			return is_scalar($jsonKey) && preg_match('/^(([a-z]|[A-Z]|\d|_)+)$/', $jsonKey);
		}
		
		foreach (explode('.', $jsonKey) as $key) {
			if (!self::isValidCustomLineKey($key)) {
				return false;
			}
		}
		return true;
	}
	
	public static function getBillRunProtectedLineKeys() {
		return array('_id', 'aid', 'apr', 'aprice', 'arate', 'billrun', 'billrun_pretend', 'call_offset', 'connection_type', 'file', 'log_stamp', 'plan', 'plan_ref', 'process_time', 'row_number', 'sid', 'source', 'stamp', 'type', 'urt', 'usaget', 'usagev');
	}

	public static function isValidRegex($regex) {
		return !(@preg_match($regex, null) === false);
	}

	public static function getCompanyName() {
		return Billrun_Factory::config()->getConfigValue('tenant.name', '');
	}

	public static function getCompanyAddress() {
		return Billrun_Factory::config()->getConfigValue('tenant.address', '');
	}
	public static function getCompanyPhone() {
		return Billrun_Factory::config()->getConfigValue('tenant.phone', '');
	}
	public static function getCompanyWebsite() {
		return Billrun_Factory::config()->getConfigValue('tenant.website', '');
	}
	public static function getCompanyEmail() {
		return Billrun_Factory::config()->getConfigValue('tenant.email', '');
	}
	
	public static function getCompanyLogo($base64 = true) {
		$gridFsColl = Billrun_Factory::db()->getDb()->getGridFS();
		$logo = $gridFsColl->find(array('billtype' => 'logo'))->sort(array('uploadDate' => -1))->limit(1)->getNext();
		if (!$logo) {
			return '';
		}
		if (!($logo instanceof MongoGridFSFile)) {
			$logo = new MongoGridFSFile($gridFsColl, $logo);
		}
		$bytes = $logo->getBytes();
		if ($base64) {
			return base64_encode($bytes);
		}
		
		return $bytes;
	}
	
	public static function getTokenToDisplay($token, $charactersToShow = 4, $characterToDisplay = '*') {
		return str_repeat($characterToDisplay, strlen($token) - $charactersToShow) . substr($token, -$charactersToShow);
	}

	/**
	 * Returns params for a command (cmd).
	 * if running with multi tenant adds the tenant to the command.
	 * 
	 */
	public static function getCmdEnvParams() {
		$ret = '--env ' . Billrun_Factory::config()->getEnv();
		if (defined('APPLICATION_MULTITENANT') && APPLICATION_MULTITENANT) {
			$ret .= ' --tenant ' . Billrun_Factory::config()->getTenant();
		}
		return $ret;
	}
	
	public static function getCmdCommand($options, $params = array()) {
		$cmd = 'php ' . APPLICATION_PATH . '/public/index.php ' . Billrun_Util::getCmdEnvParams();
		if (!is_array($options)) {
			$options = array($options);
		}
		foreach ($options as $option) {
			$cmd .= ' ' . $option;
		}
		foreach ($params as $paramKey => $paramVal) {
			$cmd .= ' ' . $paramKey . '="' . $paramVal . '"';
		}
		return $cmd;
	}
	
	public static function IsIntegerValue($value) {
		return is_numeric($value) && ($value == intval($value));
	}
	
	public static function IsUnixTimestampValue($value) {
		return is_numeric($value) && $value > strtotime('-30 years') &&  $value < strtotime('+30 years');
	}
	
	
	public static function setHttpSessionTimeout($timeout = null) {
		if (!is_null($timeout)) {
			$sessionTimeout = $timeout;
		} else {
			$sessionTimeout = Billrun_Factory::config()->getConfigValue('session.timeout', 3600);
		}
		
		ini_set('session.gc_maxlifetime', $sessionTimeout);
		ini_set("session.cookie_lifetime", $sessionTimeout);
        
		$cookieParams = session_get_cookie_params();
		session_set_cookie_params(
			(int) $sessionTimeout, $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure']
		);
	}
	
	public static function isValidIP($subject) {
		return preg_match('/^(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/', strval($subject));
	}
	
	public static function isValidHostName($subject) {
		return preg_match('/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])$/', strval($subject));
	}

	public static function isValidIPOrHost($subject) {
		return self::isValidIP($subject) || self::isValidHostName($subject);
	}

	/**
	 * 
	 * @param type $source
	 * @param type $translations
	 * @return type
	 */
	public static function translateFields($source, $translations, $instance = FALSE,$userData = FALSE) {
		$retData = array();
		
		foreach ($translations as $key => $trans) {
			$sourceKey = Billrun_Util::getIn($trans, array('translation', 'source_key'), $key);
			if (!isset($source[$sourceKey])&& empty($trans['nullable'])) {
				Billrun_Factory::log("Couldn't translate field $key with translation of  :".print_r($trans,1),Zend_Log::DEBUG);
			} else if(is_string($trans) && isset($source[$sourceKey])){
				//Handle s simple field copy  translation
				$retData[$trans] =  $source[$sourceKey];
			} else {
				switch (@$trans['type']) {
					//Handle funtion based transaltion
					case 'function' :
						if (!empty($instance) && method_exists($instance, $trans['translation']['function'])) {
							$val = $instance->{$trans['translation']['function']}(@$source[$sourceKey],
																							Billrun_Util::getFieldVal($trans['translation']['values'], array()),
																							$source,
																							$userData);
						} else if (function_exists($trans['translation']['function'])) {
							$val = call_user_func_array($trans['translation']['function'], array(@$source[$sourceKey],
																										   $userData) );
						} else {
							Billrun_Factory::log("Couldn't translate field $key using function.",Zend_Log::DEBUG);
						}
						break;
					//Handle regex translation
					case 'regex' :
						if (isset($trans['translation'][0]) && is_array($trans)) {
							foreach ($trans['translation'] as $value) {
								$val = preg_replace(key($value), reset($value), @$source[$sourceKey]);
							}
						} else if(isset($trans['translation'])) {
							$val = preg_replace(key($trans['translation']), reset($trans['translation']), $source[$sourceKey]);
						} else {
							Billrun_Factory::log("Couldn't translate field $key with translation of  :".print_r($trans,1),Zend_Log::DEBUG);
						}
						break;
					default :
							Billrun_Factory::log("Couldn't translate field $key with translation of :".print_r($trans,1).' type is not supported.',Zend_Log::ERR);
						break;
				}
				if (!is_null($val) || empty($trans['ignore_null'])) {
					$retData[$key] = $val;
				}
			}
		}
		
		return $retData;
	}
	
	/**
	 * Deeply sets an array value.
	 * 
	 * @param type $arr - reference to the array (will be changed)
	 * @param mixed $keys - array or string separated by dot (.) "path" to set
	 * @param mixed $value - new value to set
	 */
	public static function setIn(&$arr, $keys, $value) {
		if (!is_array($arr)) {
			return;
		}
		
		if (!is_array($keys)) {
			$keys = explode('.', $keys);
		}
		
		$current = &$arr;
		foreach($keys as $key) {
			$current = &$current[$key];
		}
		
		$current = $value;
	}
	

	/**
	 * Deeply unset an array values by path.
	 *
	 * @param type $arr - reference to the array (will be changed)
	 * @param mixed $keys - array or string separated by dot (.) "path" to unset
	 * @param type $clean_tree - if TRUE, all empty branches  in in keys will be removed
	 */
	public static function unsetInPath(&$arr, $keys, $clean_tree = false) {
		if (empty($keys)) {
			return;
		}
		if (!is_array($keys)) {
			$keys = explode('.', $keys);
		}
		$prev_el = NULL;
		$el = &$arr;
		foreach ($keys as &$key) {
			$prev_el = &$el;
			$el = &$el[$key];
		}
		if ($prev_el !== NULL) {
			unset($prev_el[$key]);
		}
		if ($clean_tree) {
			array_pop($keys);
			$prev_branch = static::getIn($arr, $keys);
			if (empty($prev_branch)) {
				static::unsetInPath($arr, $keys, true);
			}
		}
	}

	/**
	 * Deeply unsets an array value.
	 * 
	 * @param type $arr - reference to the array (will be changed)
	 * @param mixed $keys - array or string separated by dot (.) "path" to unset
	 * @param mixed $value - value to unset
	 */
	public static function unsetIn(&$arr, $keys, $value = null) {
		if (!is_array($arr)) {
			return;
		}
		if (!is_array($keys)) {
			$keys = explode('.', $keys);
		}
		$current = &$arr;
		foreach($keys as $key) {
			$current = &$current[$key];
		}
		
		if (!is_null($value)) {
			$current = &$current[$value];
		}
		
		unset($current);
	}

	/**
	 * Gets the value from an array.
	 * Also supports deep fetch (for nested arrays)
	 * 
	 * @param array $arr
	 * @param array/string $keys  - array of keys, or string of keys separated by "."
	 * @param mixed $defaultValue - returns in case one the fields is not found
	 * @return mixed the value in the array, default value if one of the keys is not found
	 */
	public static function getIn($arr, $keys, $defaultValue = null) {
		if (!$arr) {
			return $defaultValue;
		}
		
		if (!is_array($keys)) {
			if (isset($arr[$keys])) {
				return $arr[$keys];
			}
			$keys = explode('.', $keys);
		}
		
		$ret = $arr;
		foreach ($keys as $key) {
			if (!isset($ret[$key])) {
				return $defaultValue;
			}
			$ret = $ret[$key];
		}
		
		return $ret;
	}
	
	/**
	 * Increase the value in an array.
	 * Also supports deep fetch (for nested arrays)
	 * 
	 * @param array $arr
	 * @param array/string $keys  - array of keys, or string of keys separated by "."
	 * @param float $value - the value to add
	 */
	public static function increaseIn(&$arr, $keys, $value) {
		$currentValue = Billrun_Util::getIn($arr, $keys, 0);
		Billrun_Util::setIn($arr, $keys, $currentValue + $value);
	}
	
	/**
	 * Retrive the first field (field path supported) that has value 
	 * 	(mostly should be used to get )
	 */
	 public static function getFirstValueIn($src, $keys, $defaultValue = null) {
		foreach($keys as $keyPath) {
			$ret = static::getIn($src,$keyPath,$defaultValue);
			if($ret !=  $defaultValue) {
				return $ret;
			}
		}
		
		return $defaultValue;
	 }
	
	/**
	 * Maps a nested array  where the identifing key is in the object (as a field values ) to an hash  where the identifing key is the field name.
	 * (used to  convert querable objects from the DB to a faster structure in PHP (keyed hash))
	 * @param type $arrayData the  nested
	 * @param type $hashKeys the  keys to search for.
	 * @return type
	 */
	public static function mapArrayToStructuredHash($arrayData,$hashKeys) {
		$retHash =array();
		$currentKey = array_shift($hashKeys);
		if(isset($arrayData[0]) && is_array($arrayData) && $currentKey) {
			foreach($arrayData as $data) {
				if(isset($data[$currentKey])) {
					$retHash[$data[$currentKey]] = static::mapArrayToStructuredHash( $data, $hashKeys );
				} else {
					Billrun_Factory::log("Could not map the $currentKey in array to hashed value, received array :".print_r($data,1), Zend_Log::WARN);
				}
			}
		} else {
			$retHash = $arrayData;
		}
		return $retHash;
	}
	
	/**
	 * Maps a array where the identifing keys are in the object (as a field value) to an hash  where the identifing key is the field name.
	 * (used to  convert querable objects from the DB to a faster structure in PHP (keyed hash))	
	 * @param type $arrayData the  nested
	 * @param type $hashKeys the  keys to search for.
	 * @return type
	 */
	public static function mapFlatArrayToStructuredHash($arrayData,$hashKeys) {
		$retHash =array();
		$currentKey = array_shift($hashKeys);
		if($currentKey) {
			foreach($arrayData as $data) {
				if(isset($data[$currentKey])) {
						$key = $data[$currentKey];
						unset($data[$currentKey]);
						$retHash[$key] = array_merge(
														Billrun_Util::getFieldVal( $retHash[$key], array()),
														static::mapFlatArrayToStructuredHash(array($data), $hashKeys) 
													);
				} else {
					Billrun_Factory::log("Could not map the $currentKey in flat array to hash, received array :".print_r($data,1), Zend_Log::WARN);
				}
			}
		} else {
			$retHash = $arrayData[0];
		}
		return $retHash;
	}
	
	/**
	 * Translate standard [[]] template strings to thier actual values
	 * @param type $str
	 * @param type $translations
	 * @param type $self
	 * @return type
	 */
	public static function translateTemplateValue($str, $translations, $self = NULL, $customGateway = false) {
		foreach ($translations as $key => $translation) {
			if(is_string($translation) || is_numeric($translation)) {
				$replace = !is_string($translation) && !$customGateway ? '"[['.$key.']]"' : '[['.$key.']]';
				$str = str_replace($replace, $translation, $str);
			} elseif ($self !== NULL && method_exists($self, $translation["class_method"])) {
				$str = str_replace('[['.$key.']]', call_user_func( array($self, $translation["class_method"]) ), $str);
			} else {
				Billrun_Factory::log("Couldn't translate {$key} to ".print_r($translation,1),Zend_log::WARN);
			}
		}
		return $str;
	}
	
	/**
	 * Check if a given string/strings array has one item that matches a given regex array
	 * @param type $regexs An array of regexes
	 * @param type $strings A string or an array of strings to check the regexes against
	 * @return TRUE if there was at leat one match FALSE otherwise
	 */
	public static function regexArrMatch($regexs, $strings) {
		$strings = is_array($strings) ? $strings : array($strings);
		foreach ($regexs as $regex) {
			if (!empty(preg_grep($regex, $strings))) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * check if a specific condition is met
	 * 
	 * @param array $row
	 * @param array $condition - includes the following attributes: "field_name", "op", "value"
	 * @return boolean
	 */
	public static function isConditionMet($row, $condition) {
		$data = array('first_val' => Billrun_Util::getIn($row, $condition['field_name']));
		$query = array(
			'first_val' => array(
				$condition['op'] => $condition['value'],
			),
		);
		
		return Billrun_Utils_Arrayquery_Query::exists($data, $query);
	}
	
	/**
	 * try to fork, and if successful update the process log stamp
	 * to match the correct pid after the fork
	 * 
	 * @return $pid the result from fork attempt
	 */
	public static function fork() {
		$pid = pcntl_fork();
		if ($pid !== -1) {
			Billrun_Factory::log()->updateStamp();
		}	
		return $pid;
	}

	/**
	 * 
	 * @param type $array
	 * @param type $fields
	 * @param type $defaultVal
	 * @return type
	 */
	public static function findInArray($array, $fields, $defaultVal = null, $retArr = FALSE) {
		$fields = is_array($fields) ? $fields : explode('.', $fields);
		$rawField = array_shift($fields);
		preg_match("/\[([^\]]*)\]/", $rawField, $attr);
		if (!empty($attr)) {//Allow for  multiple attribute checks
			$attr = explode("=", Billrun_Util::getFieldVal($attr[1], FALSE));
		}
		$field = preg_replace("/\[[^\]]*\]/", "", $rawField);
		$aggregate = $retArr && ($field == '*');
		$keys = ($field != "*") ? array($field) : array_keys($array);

		$retVal = $aggregate ? array() : $defaultVal;
		foreach ($keys as $key) {
			if (isset($array[$key]) && (empty($attr) || isset($array[$key][$attr[0]])) && (!isset($attr[1]) || $array[$key][$attr[0]] == $attr[1] )) {
				if (!$aggregate) {
					$retVal[$key] = empty($fields) ? $array[$key] : static::findInArray($array[$key], $fields, $defaultVal, $retArr);
					if ($retVal[$key] === $defaultVal) {
						unset($retVal[$key]);
					}
					break;
				} else {
					$tmpRet = empty($fields) ? $array[$key] : static::findInArray($array[$key], $fields, $defaultVal, $retArr);
					if ($tmpRet !== $defaultVal) {
						$retVal[$key] = $tmpRet;
					}
				}
			}
		}

		return $retVal;
	}
	
	/**
	 * Aggregate strings representing time from some start point
	 * @param array $relativeTimes - array of relative time strings
	 * @param int $startTime - unix timestamp determine where to start the count
	 * @return aggregated unix timestamp
	 */
	public static function calcRelativeTime($relativeTimes, $startTime) {
		if (!is_array($relativeTimes)) {
			$relativeTimes = array($relativeTimes);
		}
		foreach ($relativeTimes as $relativeTime) {
			$actualTime = strtotime($relativeTime, $startTime);
			$startTime = $actualTime;
		}
		
		return $actualTime;
	}
	
        /**
         * Rounds a number.
         * @param string $roundingType - round up, round down or round nearest. 
         * @param float $number - The value to round
         * @param int $decimals-  The optional number of decimal digits to round to
         * @return float
         */
        public static function roundingNumber($number, $roundingType, $decimals = 0){
            switch ($roundingType){
                    case 'up': 
                        $newNumber = ceil($number*pow(10,$decimals))/pow(10,$decimals);
                        break;
                    case 'down':
                        $newNumber = floor($number*pow(10,$decimals))/pow(10,$decimals);
                        break;
                    case 'nearest':
                        $newNumber = round($number, $decimals); 
                        break;
                    default:
                        return;
                }
            return $newNumber;   
        }

	public static function addGetParameters($url, $queryData) {
		$query = parse_url($url, PHP_URL_QUERY);	
		$url .= ($query ? "&" : "?") . http_build_query($queryData);
		$url = htmlspecialchars($url);
		return $url;
	}


}
