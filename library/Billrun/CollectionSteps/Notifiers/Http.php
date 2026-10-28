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
	 * headers that cannot be set as custom headers (lower case) - authentication, transport / framing and content
	 * negotiation headers are owned by BillRun and the HTTP client
	 */
	const BLACKLISTED_HEADERS = array('authorization', 'x-api-key', 'cookie', 'host', 'transfer-encoding',
		'connection', 'content-type', 'accept', 'x-custom-feature', 'prefer');

	/**
	 * header name - an HTTP token, limited to the characters the HTTP client accepts in strict mode
	 */
	const HEADER_NAME_REGEX = '/^[A-Za-z0-9-]+$/';

	/**
	 * characters a header value cannot contain - control characters except tab (CR / LF / NUL would allow header injection)
	 */
	const HEADER_VALUE_FORBIDDEN_REGEX = '/[\x00-\x08\x0A-\x1F\x7F]/';

	const MAX_HEADERS = 20;
	const MAX_HEADER_NAME_LENGTH = 128;
	const MAX_HEADER_VALUE_LENGTH = 4096;

	protected $remote_authentication = [];

	public function __construct($task)
	{
		parent::__construct($task);
		$this->remote_authentication = Billrun_Factory::config()->getConfigValue('collection.settings.authentication', []);
	}
	
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
		$params = $this->getRequestParams();
		$data = $this->getRequestBody();
		$method = $this->getMethod();
		$headers = $this->getRequestHeaders();
		Billrun_Factory::log('HTTP request - sending request to prov '. '. Details: ' . print_r($data, 1), Zend_Log::DEBUG);
		return Billrun_Util::sendRequest($requestUrl, $data, $method, $headers, null, null, false, $params);
	}

	/**
	 * @return array name => value
	 */
	protected function getRequestHeaders() {
		return array_merge(array('Accept-encoding' => 'deflate'), $this->getCustomHeaders());
	}

	/**
	 * the custom headers of the request (step_config.headers - [['key' => name, 'value' => value], ...]).
	 * rows with an empty name or value are not sent. the user's rows are validated on configuration save (see getHeadersErrors)
	 *
	 * @return array name => value
	 */
	protected function getCustomHeaders() {
		$rows = $this->task['step_config']['headers'] ?? array();
		$headers = array();
		foreach ($rows as $row) {
			$name = trim($row['key'] ?? '');
			$value = trim($row['value'] ?? '');
			if ($name !== '' && $value !== '') {
				$headers[$name] = $value;
			}
		}
		return $headers;
	}

	/**
	 * validates custom headers rows
	 *
	 * @param mixed $rows [['key' => name, 'value' => value], ...]
	 *
	 * @return array the errors, empty when the rows are valid. never contains a header value (values may be secrets)
	 */
	public static function getHeadersErrors($rows) {
		if (empty($rows)) {
			return array();
		}
		if (!is_array($rows)) {
			return array('headers must be a list of key and value rows');
		}
		if (count($rows) > static::MAX_HEADERS) {
			return array('up to ' . static::MAX_HEADERS . ' headers are allowed');
		}
		$errors = array();
		$names = array();
		foreach (array_values($rows) as $index => $row) {
			$rowNumber = $index + 1;
			$name = is_array($row) ? ($row['key'] ?? '') : null;
			$value = is_array($row) ? ($row['value'] ?? '') : null;
			if (!is_string($name) || !is_scalar($value)) {
				$errors[] = "header #{$rowNumber} must have a key and a value";
				continue;
			}
			$name = trim($name);
			$value = trim(strval($value));
			if ($name === '') {
				if ($value !== '') {
					$errors[] = "header #{$rowNumber} key is missing";
				}
				continue;
			}
			if (strlen($name) > static::MAX_HEADER_NAME_LENGTH) {
				$errors[] = "header #{$rowNumber} key is too long (up to " . static::MAX_HEADER_NAME_LENGTH . ' characters)';
				continue;
			}
			if (!preg_match(static::HEADER_NAME_REGEX, $name)) {
				$errors[] = "header #{$rowNumber} key is invalid (letters, digits and '-' only)";
				continue;
			}
			$normalizedName = strtolower($name);
			if (in_array($normalizedName, static::BLACKLISTED_HEADERS)) {
				$errors[] = "header {$name} is not allowed";
				continue;
			}
			if (isset($names[$normalizedName])) {
				$errors[] = "header {$name} is duplicated";
				continue;
			}
			$names[$normalizedName] = true;
			if (strlen($value) > static::MAX_HEADER_VALUE_LENGTH) {
				$errors[] = "header {$name} value is too long (up to " . static::MAX_HEADER_VALUE_LENGTH . ' characters)';
			} else if (preg_match(static::HEADER_VALUE_FORBIDDEN_REGEX, $value)) {
				$errors[] = "header {$name} value is invalid (no line breaks or control characters)";
			}
		}
		return $errors;
	}

	/**
	 * the task without the custom headers, which may be secrets
	 *
	 * @return array
	 */
	protected function getTaskForLog() {
		$task = parent::getTaskForLog();
		unset($task['step_config']['headers']);
		return $task;
	}

	protected function getRequestUrl() {
		return $this->task['step_config']['url'];
	}

	protected function getRequestParams() {
		return [
			'authentication' => $this->remote_authentication
		];
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
		if(strtolower($method) == 'post') {
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
