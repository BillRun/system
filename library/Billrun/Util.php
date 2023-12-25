<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
		return strtotime($date_formatted);
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
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 15);
		$datetime = $billrun_key . $dayofmonth . "000000";
		return strtotime('-1 second', strtotime($datetime));
	}

	/**
	 * returns the start timestamp of the input billing period
	 * @param type $billrun_key
	 * @return type int
	 */
	public static function getStartTime($billrun_key) {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 15);
		$datetime = $billrun_key . $dayofmonth . "000000";
		return strtotime('-1 month', strtotime($datetime));
	}

	/**
	 * method to get VAT cost on specific datetime
	 * 
	 * @param int $timestamp datetime in unix timestamp format
	 * 
	 * @return float the VAT at the current timestamp
	 */
	public static function getVATAtDate($timestamp) {
		$mongo_date = new MongoDate($timestamp);
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		return $rates_coll
				->query('key', 'VAT')
				->lessEq('from', $mongo_date)
				->greaterEq('to', $mongo_date)
				->cursor()->current()->get('vat');
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

		return 0;
	}

	/**
	 * method to check if indexes exists in the query filters
	 * 
	 * @param type $filters the filters to search in
	 * @param type $searched_filter the filter to search
	 * 
	 * @return boolean true if searched filter exists in the filters supply
	 */
	public static function filterExists($filters, $searched_filter) {
		settype($searched_filter, 'array');
		foreach ($filters as $k => $f) {
			$keys = array_keys($f);
			if (count(array_intersect($searched_filter, $keys))) {
				return true;
			}
		}
		return false;
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
		if ($seconds > 3600) {
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
//		foreach ($recipients as $recipient) {
//			$mailer->addTo($recipient);
//		}
		$mailer->addTo($recipients);
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
		$syscmd = $cmd . " > /dev/null & ";
		if (system($syscmd) === FALSE) {
			error_log("Can't fork PHP process");
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

		if (self::isIntlNumber($phoneNumber) || strlen($phoneNumber) > 12) { // len>15 means not msisdn
			return $phoneNumber;
		}

		if (is_null($defaultPrefix)) {
			$defaultPrefix = Billrun_Factory::config()->getConfigValue('billrun.defaultCountryPrefix', 972);
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
		return ltrim($number, "0");
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
			'activation' => array(),
			'deactivation' => array(),
			'fraction' => array(),
			'source_amount_without_vat' => array(),
			'additional' => array(),
			'id' => array(),
			'unique_plan_id' => array(),
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
		if (isset($filtered_request['source_amount_without_vat']) && $filtered_request['fraction'] == 0) {
			$amount = $filtered_request['source_amount_without_vat'];
		}
		else{
			$amount = $filtered_request['amount_without_vat'];
		}
		$amount_without_vat = Billrun_Util::filter_var($amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
		if (!is_numeric($amount) || $amount_without_vat === false) {
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
			$amount_without_vat = Billrun_Util::filter_var($filtered_request['amount_without_vat'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
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
		$service_row['urt'] = new MongoDate(Billrun_Util::getEndTime($billrun_key));
		ksort($service_row);
		$service_row['stamp'] = Billrun_Util::generateArrayStamp($service_row);
		return $service_row;
	}

	/**
	 * convert assoc array to MongoDB query
	 * 
	 * @param array $array the array to convert
	 * @return array the MongoDB array conversion
	 * 
	 * @todo move to Mongodloid
	 */
	public static function arrayToMongoQuery($array) {
		$query = array();
		foreach ($array as $key => $val) {
			if (is_array($val) && strpos($key, '$') !== 0) {
				foreach (self::arrayToMongoQuery($val) as $subKey => $subValue) {
					if (strpos($subKey, '$') === 0) {
						$query[$key][$subKey] = $subValue;
					} else {
						$query[$key . "." . $subKey] = $subValue;
					}
				}
			} else {
				$query[$key] = $val;
			}
		}
		return $query;
	}

	/**
	 * Returns an array value if it is set
	 * @param mixed $field the array value
	 * @param mixed $defVal the default value to return if $field is not set
	 * @return mixed the array value if it is set, otherwise returns $defVal
	 */
	static public function getFieldVal(&$field, $defVal) {
		if (isset($field)) {
			return $field;
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

	public static function logFailedServiceRow($row) {
		$fd = fopen(Billrun_Factory::config()->getConfigValue('service.failed_credits_file', './files/failed_service.json'), 'a+');
		fwrite($fd, json_encode($row) . PHP_EOL);
		fclose($fd);
	}

	public static function logFailedResetLines($sids, $billrun_key) {
		$fd = fopen(Billrun_Factory::config()->getConfigValue('resetlines.failed_sids_file', './files/failed_resetlines.json'), 'a+');
		fwrite($fd, json_encode(array('sids' => $sids, 'billrun_key' => $billrun_key)) . PHP_EOL);
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
	public static function getNestedArrayVal($array, $fields, $defaultVal = null,$retArr = FALSE) {
		$fields = is_array($fields) ? $fields : explode('.', $fields);
		$rawField = array_shift($fields);
		preg_match("/\[([^\]]*)\]/", $rawField,$attr);		
		if(!empty($attr)) {//Allow for  multiple attribute checks
			$attr = explode("=",Billrun_Util::getFieldVal($attr[1],FALSE)); 
		}
		$field = preg_replace("/\[[^\]]*\]/", "", $rawField); 
		$aggregate = $retArr &&  ($field =='*') ;
		$keys = ($field != "*") ? array($field) : array_keys($array);	
		
		$retVal = $aggregate ? array() : $defaultVal;
		foreach ($keys as $key ) {
			if( isset($array[$key]) && (empty($attr) || isset($array[$key][$attr[0]])) && (!isset($attr[1]) || $array[$key][$attr[0]] == $attr[1] ) ) {
				if (!$aggregate) {
					$retVal = empty($fields) ? $array[$key] : static::getNestedArrayVal($array[$key], $fields, $defaultVal,$retArr); 
					break;
				}
				else {
					$retVal[] = empty($fields) ? $array[$key] : static::getNestedArrayVal($array[$key], $fields, $defaultVal,$retArr); 
				}
			}
		}		
		
		return $retVal;
	}
	
	/**
	 * method to retrieve IldsOneWay circuit groups
	 * @return array of out_circuit_groups
	 */
	public static function getIldsOneWayCircuitGroups() {
		return Billrun_Factory::config()->getConfigValue('ildsOneWay.ocg', array());
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
		$rate_key_list = Billrun_Factory::config()->getConfigValue('Rate_Nsn.calculator.intl_rates',array());
		$query = array("key" => array('$in' => $rate_key_list));
		$ratesmodle =new RatesModel(array("collection" => "rates"));
		$rates =$ratesmodle->getRates($query);
		$rate_ref_list=array();
		foreach ($rates as $rate) {
			$rate_ref_list[]=$rate->createRef(Billrun_Factory::db()->ratesCollection())['$id']->{'$id'};
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
	 * @return array or FALSE on failure
	 */
	public static function sendRequest($url, array $data = array(), $method = Zend_Http_Client::POST, array $headers = array('Accept-encoding' => 'deflate'), $timeout = null) {
		if (empty($url)) {
			Billrun_Factory::log()->log("Bad parameters: url - " . $url . " method: " . $method, Zend_Log::ERR);
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
		$client = new Zend_Http_Client($url);
		$client->setHeaders($headers);
		$client->setAdapter($curl);
		$client->setMethod($method);

		if (!empty($data)) {
			if ($zendMethod == Zend_Http_Client::POST) {
				$client->setParameterPost($data);
			} else {
				$client->setParameterGet($data);
			}
		}
		try {
			$response = $client->request();
			$output = $response->getBody();
		} catch (Zend_Http_Client_Exception $e) {
			$output = null;
		}
		if (empty($output)) {
			Billrun_Factory::log()->log("Bad RPC result: " . print_r($response, TRUE) . " Parameters sent: " . print_r($data, TRUE), Zend_Log::WARN);
			return FALSE;
		}

		return $output;
	}
	
	public static function getTimezoneOffsetInSeconds($timezone) {
		$sign = $timezone[0] == '+' ? 1 : -1;
		$hours = substr($timezone, 1, 2);
		$minutes = substr($timezone, -2, 2);
		return $sign * ($hours * 3600 + $minutes * 60);
	}
	
	public static function convertToBillrunDate($date){
		return date(Billrun_Base::base_dateformat, strtotime($date));
	}
	
	
	public static function getTimeTransitionsByTimezone(){
 		return timezone_transitions_get(new DateTimeZone(date_default_timezone_get()), strtotime('January 1st'), strtotime('December 31'));
 	}
 	
 	public static function getTransitionTime($transitions, $index){
 		return $transitions[$index]['time'];
 	}
 	
 	public static function getTransitionOffset($transitions, $index){
 		return $transitions[$index]['offset'];
 	}
 	
 	public static function buildTransitionsDates($transitions){
 		$summer_transition = Billrun_Util::getTransitionTime($transitions, 1);
 		$winter_transition = Billrun_Util::getTransitionTime($transitions, 2);	
 		$summer_date = new DateTime($summer_transition);
 		$winter_date = new DateTime($winter_transition);
 		
 		$transitions_dates = array('summer' => $summer_date, 'winter' => $winter_date);
 		return $transitions_dates;
 	}
	
	
	
	/**
	 * calculates zend_date with offset from the timestamp that is transffered.
	 * 
	 * @param string $tzoffset - time zone offset from GMT
	 * @param integer $timestamp - the line urt
	 * 
	 * @return new zend_date after taking the offset in account
	 */
	public static function createTimeWithOffset($tzoffset, $timestamp){
		$sign = substr($tzoffset, 0, 1);
		$hours = substr($tzoffset, 1, 2);
		$minutes = substr($tzoffset, 3, 2);
		$time = $hours . ' hours ' . $minutes . ' minutes';
		if ($sign == "-") {
			$time .= ' ago';
		}
		$timestamp = strtotime($time, $timestamp);
		$zend_date = new Zend_Date($timestamp);
		return $zend_date;
	}
	
	public static function getIsraelTransitions(){
		return timezone_transitions_get(new DateTimeZone(date_default_timezone_get()), strtotime('January 1st'), strtotime('December 31'));
	}
	
	public static function isWrongIsrTransitions($transitions){
		if (count($transitions) != 3){
			return true;
		}
		return false;
	}
	
	/**
	 * Translate standard [[]] template strings to thier actual values
	 * @param type $str
	 * @param type $translations
	 * @param type $self
	 * @return type
	 */
	public static function translateTemplateValue($str, $translations, $self = NULL) {
		foreach ($translations as $key => $translation) {
			if(is_string($translation) || is_numeric($translation)) {
				$replace = is_integer($translation) ? '"[['.$key.']]"' : '[['.$key.']]';
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
	* Translates specified fields in the given data array using the provided translations.
	*
	* @param array       $data         The data array to be translated.
	* @param array       $translations An associative array where keys represent the fields to translate,
	* @param object|null $self         An optional object instance used for calling class methods during translation.
	*
	* @return array The translated data array.
	*/
	public static function translateFieldValue($data, $translations, $self = NULL) {
		foreach ($translations as $key => $translation) {
			if(is_string($translation) || is_numeric($translation)) {
				$replace = is_integer($translation) ? '"[['.$key.']]"' : '[['.$key.']]';
				$data[$key] = $translation;
			} elseif ($self !== NULL && method_exists($self, $translation["class_method"])) {
				$data[$key]= call_user_func( array($self, $translation["class_method"]) , $data, $translation['arguments']);
			} else {
				Billrun_Factory::log("Couldn't translate {$key} to ".print_r($translation,1),Zend_log::WARN);
			}
		}
		return $data;
	}
}

