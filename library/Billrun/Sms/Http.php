<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing sending Sms through Http
 *
 * @package  Sms
 * @since    5.16
 * 
 */
class Billrun_Sms_Http extends Billrun_Sms_Abstract {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'http';

	/**
	 * url for the REST API
	 * @var string
	 */
	protected $url;

	/**
	 * send sms path
	 * @var string
	 */
	protected $sendSmsPath;

	/**
	 * the from (number or name) that the sms will be sent on behalf of
	 * @var string
	 */
	protected $from;

	/**
	 * array of client options
	 * 
	 * @var array
	 */
	protected $clientOptions = array();

	/**
	 * array of client options
	 * 
	 * @var array
	 */
	protected $sendSmsOptions = array();

	/**
	 * http headers to send
	 * 
	 * @var array
	 */
	protected $httpHeaders = array();

	/**
	 * http request method type
	 * 
	 * @var string
	 */
	protected $httpRequestMethod = Zend_Http_Client::POST;

	/**
	 * destination field format; array or string; string default
	 * 
	 * @var string
	 */
	protected $destinationFieldFormat = 'string';

	/**
	 * destination field name; to default
	 * 
	 * @var string
	 */
	protected $destinationField = 'to';

	/**
	 * from field name; from default
	 * 
	 * @var string
	 */
	protected $fromField = 'from';

	/**
	 * from field name; message default
	 * 
	 * @var string
	 */
	protected $messageField = 'message';

	/**
	 * the field to that defined the return sms ack in the http response
	 * 
	 * @var string
	 */
	protected $returnResultCodeField = null;
	
	/**
	 * method to parse data before sending the http request
	 * 
	 * @var mixed
	 */
	protected $parseDataFunc = null;

	/**
	 * method arguments to parse response from the http request
	 * 
	 * @var mixed
	 */
	protected $parseDataFuncArgs = array();

	/**
	 * method to parse response from the http request
	 * 
	 * @var mixed
	 */
	protected $parseResponseFunc = null;
	
	/**
	 * method arguments to parse response from the http request
	 * 
	 * @var mixed
	 */
	protected $parseResponseFuncArgs = array();

	
	/**
	 * method arguments to parse response from the http request
	 * 
	 * @var mixed
	 */
	protected $getResponseStatusFunc = array('Billrun_Util', 'getIn');

	
	/**
	 * socket time out in seconds
	 * 
	 * @var int
	 */
	protected $httpTimeout = 10;
	
	protected function getHeaders() {
		return $this->httpHeaders;
	}

	public function getFrom() {
		return $this->from;
	}

	public function setFrom($from) {
		$this->setFrom($from);
	}

	protected function init($params) {
		parent::init($params);
		$this->initHttpMethod();
	}

	protected function parseData($data) {
		if (empty($this->parseDataFunc)) {
			return $data;
		}
		if (!is_callable($this->parseDataFunc)) {
			return $data;
		}
		return call_user_func_array($this->parseDataFunc, array_merge([$data], $this->parseDataFuncArgs));
	}

	protected function getDestination() {
		if ($this->destinationFieldFormat == 'array' && !is_array($this->to)) {
			return array($this->to);
		} 
		if ($this->destinationFieldFormat == 'string' && is_array($this->to)) {
			return implode(',', $this->to);
		} 
		if ($this->destinationFieldFormat == 'string') {
			return (string) $this->to;
		}
		return $this->to;
	}

	protected function parseResponse($data) {
		if (empty($this->parseResponseFunc)) {
			return $data;
		}
		if (!is_callable($this->parseResponseFunc)) {
			return $data;
		}
		return call_user_func_array($this->parseResponseFunc, array_merge([$data], $this->parseResponseFuncArgs));
	}
	
	protected function getResponseStatus($data) {
		if (empty($this->returnResultCodeField)) {
			return $data;
		}

		if (!is_callable($this->getResponseStatusFunc)) {
			return $data;
		}

		return call_user_func_array($this->getResponseStatusFunc, [$data, $this->returnResultCodeField]);
	}

	/**
	 * method to pre-check before sending
	 * 
	 * @return boolean
	 */
	protected function precheckBeforeSend() {
		return true;
	}
	
	protected function getData() {
		return array(
			$this->fromField => $this->from,
			$this->destinationField => $this->getDestination(),
			$this->messageField => $this->body,
		);
	}

	/**
	 * see parent::send
	 * 
	 * @return mixed msg id if success, false on failure
	 */
	public function send() {
		Billrun_Factory::log('Sending Http SMS to: ' . $this->to . ' content: ' . $this->body, Zend_Log::DEBUG);

		try {
			if (empty($this->body)) {
				Billrun_Factory::log('SMS Http: need to set sms body', Zend_Log::NOTICE);
				return false;
			}

			if (empty($this->to)) {
				Billrun_Factory::log('SMS Http: need to set sms destination (to)', Zend_Log::NOTICE);
				return false;
			}

			if ($this->precheckBeforeSend() === false) {
				return false;
			}

			$data = $this->getData();

			$unifiedData = array_merge($data, $this->sendSmsOptions);
			$requestData = $this->parseData($unifiedData);

			$output = billrun_util::sendRequest($this->url . $this->sendSmsPath, $requestData, $this->httpRequestMethod, $this->getHeaders(), $this->httpTimeout);
	
			Billrun_Factory::log("Send Http SMS send http response: " . $output, Zend_Log::DEBUG);
	
			$retArray = $this->parseResponse($output);

			$ret = $this->getResponseStatus($retArray);
		} catch (Throwable $th) {
			Billrun_Factory::log('Send Http SMS send: got throwable. code: ' . $th->getCode() . ', message: ' . $th->getMessage(), Zend_Log::WARN);
			$ret = false;
		} catch (Exception $ex) {
			Billrun_Factory::log('Send Http SMS send: got exception. code: ' . $ex->getCode() . ', message: ' . $ex->getMessage(), Zend_Log::WARN);
			$ret = false;
		}

		return $ret;
	}
	
	protected function initHttpMethod() {
		if (empty($this->clientOptions['httpRequestMethod'])) {
			return;
		}
		$this->httpRequestMethod = $this->getClassConstant('Zend_Http_Client', $this->clientOptions['httpRequestMethod']);
	}

	/**
	 * method to get variable from class constants; on some case the variable input is the value itself
	 * 
	 * @param string $class
	 * @param mixed $var
	 * 
	 * @return the constant value
	 */
	protected function getClassConstant($class, $var) {
		if (is_numeric($var)) {
			return (int) $var;
		}

		if (is_bool($var)) {
			return (bool) $var;
		}

		return constant($class . '::' . $var);
	}

}
