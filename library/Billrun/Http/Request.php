<?php

/**
 * A helper class which handles sending HTTP requests
 */
class Billrun_Http_Request extends Zend_Http_Client {

	public function request($method = null) {
		$this->authenticate();
		return parent::request($method);
	}

	/**
	 * add authentication data to the request
	 *
	 * @return void
	 */
	protected function authenticate() {
		$authParams = $this->config['authentication'] ?? false;
		if (empty($authParams)) {
			return true;
		}

		$authenticator = Billrun_Http_Authentication_Base::getInstance($this, $authParams);
		if (!$authenticator) {
			throw new Exception("Failed to authenticate");
		}

		$authenticator->authenticate();
	}

}
