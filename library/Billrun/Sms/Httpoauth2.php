<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing sending Sms through Http OAuth2
 *
 * @package  Sms
 * @since    5.16
 * 
 */
class Billrun_Sms_Httpoauth2 extends Billrun_Sms_Http {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'httpouth2';

	/**
	 * login path
	 * @var string
	 */
	protected $loginPath;

	/**
	 * authentication user
	 * @var string
	 */
	protected $user;

	/**
	 * authentication password
	 * @var string
	 */
	protected $password;

	/**
	 * authentication bearer
	 * @var string
	 */
	protected $bearer;

	/**
	 * authentication bearer expiration
	 * @var int
	 */
	protected $bearerExpiration;

	/**
	 * http request method type
	 * 
	 * @var string
	 */
	protected $bearerField = 'token';

	/**
	 * user field name; login default
	 * 
	 * @var string
	 */
	protected $userField = 'login';

	/**
	 * user field name; password default
	 * 
	 * @var string
	 */
	protected $passwordField = 'password';

	/**
	 * the field to that defined the return sms ack in the http response
	 * 
	 * @var string
	 */
	protected $returnResultCodeField = 'returnCode';

	/**
	 * the token bearer expiration duration
	 * 
	 * @var int
	 */
	protected $bearerExpirationDuration = 3540; // 1 hour minus buffer

	/**
	 * callable to parse the data
	 * 
	 * @var mixed
	 */
	protected $parseDataFunc = 'json_encode';

	/**
	 * method to parse response from the http request
	 * 
	 * @var mixed
	 */
	protected $parseResponseFunc = 'json_decode';

	/**
	 * method arguments to parse response from the http request
	 * 
	 * @var mixed
	 */
	protected $parseResponseFuncArgs = array(true);

	protected function getHeaders() {
		$authHeader = array(
			'Authorization' => 'bearer ' . $this->bearer,
		);
		return array_merge($authHeader, $this->httpHeaders);
	}

	protected function login() {
		if (!empty($this->bearer) && $this->bearerExpiration > time()) {
			return $this->bearer;
		}

		$data = array(
			$this->userField => $this->user,
			$this->passwordField => $this->password,
		);

		$output = billrun_util::sendRequest($this->url . $this->loginPath, $this->parseData($data), $this->httpRequestMethod, $this->getHeaders(), $this->httpTimeout);
		Billrun_Factory::log("Send Http OAuth2 SMS login http response: " . $output, Zend_Log::DEBUG);
		$ret = json_decode($output, true);

		$this->bearer = Billrun_Util::getIn($ret, $this->bearerField) ?? false;

		$this->bearerExpiration = time() + $this->bearerExpirationDuration;

		return $this->bearer;
	}

	/**
	 * method to pre-check before sending
	 * 
	 * @return boolean
	 */
	protected function precheckBeforeSend() {
		if (!$this->login()) {
			return false;
		}
		return true;
	}

}
