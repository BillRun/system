<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing sending Sms alerts
 *
 * @package  Sms
 * @since    2.1
 * @deprecated since version 5.13
 * 
 * @todo Refactoring to interface with inheritance of configuration (bridge)
 */
class Billrun_Sms_Legacy extends Billrun_Sms_Abstract {

	/**
	 * data
	 * 
	 * @var array
	 */
	protected $data = array();

	/**
	 * constructor
	 * set options via magic method
	 * 
	 * @param type $options
	 */
	public function __construct($options = array()) {
		foreach ($options as $key => $value) {
			$this->{$key} = $value;
		}
	}

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
	 * method to send sms
	 * 
	 * @see parent::send
	 */
	public function send() {
		if (empty($this->body) || empty($this->to)) {
			Billrun_Factory::log("can not send the sms, there are missing params - txt: " . $this->body . " recipients: " . $this->to . " from: " . $this->data['from'], Zend_Log::WARN);
			return false;
		}

		$unicode_text = $this->sms_unicode($this->body);
		if (!empty($this->body) && empty($unicode_text)) {
			$language = '1';
		} else {
			$language = '2';
		}

		// Temporary - make sure is not 23 chars long
		$text = str_pad($this->body, 24, '+');
		$period = 120;

		$send_params = array(
			'message' => $text,
			'to' => $this->to,
			'from' => $this->data['from'],
			'language' => $language,
			'username' => $this->data['user'],
			'password' => $this->data['pwd'],
			'acknowledge' => "false",
			'period' => $period,
			'channel' => "SRV",
		);

		$url = $this->data['provisioning'] . "?" . http_build_query($send_params);

		$sms_result = Billrun_Util::sendRequest($url);
		$exploded = explode(',', $sms_result);

		$response = array(
			'error-code' => (empty($exploded[0]) ? 'error' : 'success'),
			'cause-code' => $exploded[1],
			'error-description' => $exploded[2],
			'tid' => $exploded[3],
		);

		Billrun_Factory::log("phone: " . $recipient . " encoded_text: " . $this->body . " url: " . $url . " result" . print_R($response, 1), Zend_Log::INFO);

		return $response['error-code'] == 'success' ? true : false;
	}

	protected function sms_unicode($message) {
		$latin = @iconv('UTF-8', 'ISO-8859-1', $message);
		if (strcmp($latin, $message)) {
			$arr = unpack('H*hex', @iconv('UTF-8', 'UCS-2BE', $message));
			return strtoupper($arr['hex']);
		}

		return FALSE;
	}

}
