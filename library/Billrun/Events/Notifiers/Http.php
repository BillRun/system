<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Http request event notifier
 *
 * @since 5.6
 */
class Billrun_Events_Notifiers_Http extends Billrun_Events_Notifiers_Base {

	/**
	 * see Billrun_Events_Notifiers_Base::getNotifierName
	 * @return string
	 */
	public function getNotifierName() {
		return "http";
	}

	/**
	 * sends an http request for the event
	 * 
	 * @return mixed- response from notifier on success, false on failure
	 */
	public function notify() {
		return $this->sendRequest();
	}

	/**
	 * sends an HTTP request
	 * 
	 * @return response if received, false otherwise
	 */
	protected function sendRequest() {
		$requestUrl = $this->getRequestUrl();
		$data = $this->getRequestBody();
		$method = $this->getMethod();
		Billrun_Factory::log('HTTP request - sending request to prov ' . '. Details: ' . print_r($data, 1), Zend_Log::DEBUG);
		$response = $this->parseResponse(Billrun_Util::sendRequest($requestUrl, $data, $method));
		if ($this->isResponseValid($response)) {
			Billrun_Factory::log('Got HTTP response. Details: ' . $response, Zend_Log::DEBUG);
			return $this->getSuccessResponse($response);
		}
		Billrun_Factory::log('HTTP request - no response. Request details: ' . print_r($data, 1), Zend_Log::ALERT);
		return $this->getFailureResponse();
	}

	/**
	 * gets the url to send the request to
	 * gets the value from event or params or general settings
	 * @return string
	 */
	protected function getRequestUrl() {
		return $this->getSettingValue('url', '');
	}

	/**
	 * gets the method to send the request (POST/GET/...)
	 * gets the value from event or params or general settings
	 * @return string
	 */
	protected function getMethod() {
		return $this->getSettingValue('method', Zend_Http_Client::POST);
	}

	/**
	 * gets additional parameters to add to the request
	 * @return array
	 */
	protected function getRequestAdditionalParams() {
		return array();
	}

	/**
	 * gets the request body to send
	 * @return array
	 */
	protected function getRequestBody() {
		$additionalParams = $this->getRequestAdditionalParams();
		$body = array_merge($this->event, $additionalParams, $this->params);
		unset($body['_id']);
		return $body;
	}

	/**
	 * parse the response received from the request
	 * @return mixed
	 */
	protected function parseResponse($response) {
		$decoderType = $this->getSettingValue('decoder', 'json');
		$decoder = Billrun_Decoder_Manager::getDecoder(array('decoder' => $decoderType));
		return $decoder->decode($response);
	}

	/**
	 * build a response to send in case of response received from the request
	 * 
	 * @param mixed $response
	 * @return mixed
	 */
	protected function getSuccessResponse($response) {
		return $response;
	}

	/**
	 * build a response to send in case no response received from the request
	 * @return mixed
	 */
	protected function getFailureResponse() {
		return false;
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

	/**
	 * gets settings value
	 * first checked in the event, then in the additional parameters, and then in the general settings
	 * return default value if not found
	 * 
	 * @param string $field - settings field name
	 * @param mixed $defaultValue - in case fields was not found in all setting sources
	 * @return mixed
	 */
	protected function getSettingValue($field, $defaultValue = '') {
		if (isset($this->event[$field])) {
			return $this->event[$field];
		}
		if (isset($this->params[$field])) {
			return $this->params[$field];
		}
		if (isset($this->settings[$field])) {
			return $this->settings[$field];
		}

		return $defaultValue;
	}

}
