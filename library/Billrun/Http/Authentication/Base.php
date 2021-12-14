<?php

/**
 * Basic class for adding authentication data to HTTP requests
 */
abstract class Billrun_Http_Authentication_Base {

	/**
	 * Authentication parameters
	 *
	 * @var array
	 */
	protected $params = [];

	/**
	 * The request to authenticate
	 *
	 * @var Billrun_Http_Request
	 */
	protected $request = null;

	public function __construct(Billrun_Http_Request $request, $params = []) {
		$this->request = $request;
		$this->params = $params;
	}

	/**
	 * add authentication data to the request
	 *
	 * @return void
	 */
	public abstract function authenticate();

	/**
	 * Get authenticator handler based on the authentication type
	 *
	 * @param  Billrun_Http_Request $request
	 * @param  array $params
	 * @return Billrun_Http_Authentication_Base
	 */
	public static function getInstance(Billrun_Http_Request $request, $params = []) {
		$type = ucfirst($params['type'] ?? '');
		$authClassName = "Billrun_Http_Authentication_{$type}";
		if (!class_exists($authClassName)) {
			Billrun_Factory::log("Cannot get HTTP authenticator {$type}. Params: " . print_R($params, 1), Zend_Log::ERR);
			return false;
		}

		return new $authClassName($request, $params);
	}

}
