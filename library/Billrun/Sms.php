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
	/**
	 * data
	 * @var array
	 */
	protected $data = array();
	
	/**
	 * rv
	 * @var array
	 */
	protected $rv = array();
	
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
		$unicode_text = $this->sms_unicode($this->data['message']);
		if (!empty($this->data['message']) && empty($unicode_text)) {
			$encoded_text = urlencode($this->data['message']);
			$language = '1';
		}

		// Temporary - make sure is not 23 chars long
		$text = str_pad($encoded_text, 24, '+');
		$period = 120;

		foreach ($this->data['recipients'] as $recipient) {
			$send_params = array(
				'message' => $text,
				'to' => $recipient,
				'from' => $this->data['from'],
				'language' => $language,
				'username' => $this->data['user'],
				'password' => $this->data['pwd'],
				'acknowledge' => "false",
				'period' => $period,
				'channel' => "SRV",
			);
			
			$url = $this->data['provisoning'] . "?" . http_build_query($send_params);

			// @todo: change to zend http client
			$sms_result = file_get_contents($url);
			$exploded = explode(',', $sms_result);
			
			$response = array(
				'error-code' => (empty($exploded[0]) ? 'error' : 'success'),
				'cause-code' => $exploded[1],
				'error-description' => $exploded[2],
				'tid' => $exploded[3],
			);
			
			Billrun_Factory::log()->log("phone: " . $recipient  . " encoded_text: " . $text . " url: " . $url . " result" . print_R($response, 1), Zend_Log::INFO);
		}

		return $this;
	}

	public static function sms_unicode($message) {
		$latin = @iconv('UTF-8', 'ISO-8859-1', $message);
		if (strcmp($latin, $message)) {
			$arr = unpack('H*hex', @iconv('UTF-8', 'UCS-2BE', $message));
			return strtoupper($arr['hex']);
		}

		return FALSE;
	}

}
