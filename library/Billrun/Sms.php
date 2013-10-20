<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing sending Sms alerts
 *
 * @package  handler
 * @since    1.0
 */
class Billrun_Sms {
	/*	 * #@+
	 * @access protected
	 */

	/**
	 * data
	 * @var array
	 */
	protected $data = array();

	public function __set($name, $value) {
		if ($name == array()) {
			$this->data[$name][] = $value;
		} else {
			$this->data[$name] = $value;
		}
	}

	public function __get($name) {
		return $this->$name;
	}

	/**
	 * sends sms text
	 */
	public function send() {
		if (empty($this->data['message']) || empty($this->data['recipients'])) {
			Billrun_Factory::log()->log("can not send the sms, there are missing params - txt: " . $this->data['message'] . " recipients: " . print_r($this->data['recipients'], TRUE) . " from: " . $this->data['from'], Zend_Log::WARN);
			return false;
		}

		$language = '2';
		$encoded_text = $this->sms__unicode($this->data['message']);
		if (!empty($this->data['message']) && empty($encoded_text)) {
			$encoded_text = urlencode($this->data['message']);
			$language = '1';
		}

		// Temporary - make sure is not 23 chars long
		$encoded_text = str_pad($encoded_text, 24, '+');
		$period = 120;

		foreach ($this->data['recipients'] as $recipient) {
			$url = $this->data['provisoning'] . "?message=$encoded_text&to=" . $recipient . "&from=" . $this->data['from'] . "&language=$language&username=" . $this->data['user'] . "&password=" . $this->data['pwd'] . "&acknowledge=false&period=$period&channel=SRV";

			$sms_result = file_get_contents($url);
			$exploded = explode(',', $sms_result);
			$rv[] = array(
				'error-code' => (empty($exploded[0]) ? 'error' : 'success'),
				'cause-code' => $exploded[1],
				'error-description' => $exploded[2],
				'tid' => $exploded[3],
			);

			Billrun_Factory::log()->log("phone:$recipient,encoded_text:$encoded_text,url:$url,result," . json_encode($rv), Zend_Log::INFO);
		}

		return $rv;
	}

	public static function sms__unicode($message) {
		$latin = @iconv('UTF-8', 'ISO-8859-1', $message);
		if (strcmp($latin, $message)) {
			$arr = unpack('H*hex', @iconv('UTF-8', 'UCS-2BE', $message));
			return strtoupper($arr['hex']);
		}

		return FALSE;
	}

}
