<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Collect HTTP Notifier
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_CollectionSteps_Notifiers_Http extends Billrun_CollectionSteps_Notifiers_Abstract {

	/**
	 * sends an HTTP request
	 * 
	 * @return response if received, false otherwise
	 */
	protected function run() {
		$requestUrl = $this->getRequestUrl();
		if (empty($requestUrl)) {
			return false;
		}
		$data = $this->getRequestBody();
		$method = $this->getMethod();
		Billrun_Factory::log('HTTP request - sending request to prov ' . '. Details: ' . print_r($data, 1), Zend_Log::DEBUG);
		return Billrun_Util::sendRequest($requestUrl, $data, $method);
	}

	protected function getRequestUrl() {
		return $this->task['step_config']['url'];
	}

	/**
	 * 
	 * @return type string - default json
	 */
	protected function getDecoder() {
		if (!empty($this->task['step_config']['decoder'])) {
			return $this->task['step_config']['decoder'];
		}
		return 'json'; // default
	}

	/**
	 * 
	 * @return type string - POST or GET default GET
	 */
	protected function getMethod() {
		$method = $this->task['step_config']['method'];
		if (strtolower($method) == 'post') {
			return Zend_Http_Client::POST;
		}
		return Zend_Http_Client::GET;
	}

	protected function getRequestBody() {
		$data = $this->task;
		unset($data['step_config']);
		unset($data['_id']);
		return $data;
	}

	/**
	 * parse the response received from the request
	 * @return mixed
	 */
	protected function parseResponse($response) {
		$decoderType = $this->getDecoder();
		$decoder = Billrun_Decoder_Manager::getDecoder(array('decoder' => $decoderType));
		return $decoder->decode($response);
	}

	/**
	 * checks if the response from request is valid
	 * 
	 * @param array $response
	 * @return boolean
	 */
	protected function isResponseValid($response) {
		return $response && isset($response['success']) && $response['success'];
	}

}
