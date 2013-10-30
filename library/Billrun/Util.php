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
		$format = "Ym" . str_pad($dayofmonth, '2', '0', STR_PAD_LEFT) . "000000";
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
		$date_formatted = str_replace(' ', 'T', date(Billrun_Base::base_dateformat, strtotime($datetime))) . $tz_offset;
		$datetime = strtotime($date_formatted);
		return $datetime;
	}

	/**
	 * Send Email helper
	 * @param type $subject the subject of the message.
	 * @param type $body the body of the message
	 * @param type $attachments (optional)
	 * @return type
	 */
	public static function sendMail($subject, $body, $recipients = array(), $attachments = array()) {

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
		//send email
		try {
			$ret = $mailer->send();
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Failed when trying to send  email on alert results, Failed with : " . $e, Zend_Log::ERR);
			$ret = FALSE;
		}
		return $ret;
	}

	/**
	 * Get the data the is stored in the file name.
	 * @return an array containing the sequence data. ie:
	 * 			array(seq => 00001, date => 20130101 )
	 */
	public static function getFilenameData($type, $filename) {
		return array(
			'seq' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($type . ".sequence_regex.seq", "/(\d+)/"), $filename),
			'zone' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($type . ".sequence_regex.zone", "//"), $filename),
			'date' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($type . ".sequence_regex.date", "/(20\d{4})/"), $filename),
			'time' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($type . ".sequence_regex.time", "/\D(\d{4,6})\D/"), $filename),
		);
	}

	/**
	 * Send SMS helper
	 * @param type $recipient - recipient number
	 * @param type $message - body message
	 * @return type
	 */
	public static function sendSms($message, $recipients) {
		$smser = Billrun_Factory::smser();

		if (empty($recipients)) {
			return FALSE;
		}

		//send sms
		try {
			//set recipents
			$smser->recipients = $recipients;
			//set message
			$smser->message = $message;

			$ret = $smser->send();
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Failed when trying to send  sms on alert results, Failed with : " . $e, Zend_Log::ERR);
			$ret = FALSE;
		}
		return $ret;
	}

	/**
	 * 
	 * @param string $url
	 * @param string $data
	 * @param string $method
	 * @return array or boolean
	 */
	public static function sendRequest($url, $data, $method = 'POST') {
		if ($method != 'POST' || $method != 'GET' || empty($data) || empty($url)) {
			Billrun_Factory::log()->log("Bad parameters: url - ".$url." data - ".$data." method: ".$method, Zend_Log::ERR);
			return FALSE;
		}

		$curl = new Zend_Http_Client_Adapter_Curl();
		$client = new Zend_Http_Client($url);
		$client->setHeaders(array('Accept-encoding' => 'deflate'));
		$client->setAdapter($curl);
		$client->setMethod(Zend_Http_Client::$method);

		if ($method == 'POST') {
			$client->setParameterPost($data);
		} else {
			$client->setParameterGet($data);
		}

		$response = $client->request();
		$output = $response->getBody();

		if (empty($output)) {
			Billrun_Factory::log()->log("Bad RPC result: " . print_r($response, TRUE) . " Parameters sent: " . $params, Zend_Log::WARN);
			return FALSE;
		}

		return $output;
	}

}
