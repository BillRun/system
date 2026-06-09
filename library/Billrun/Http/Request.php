<?php

/**
 * A helper class which handles sending HTTP requests
 */
class Billrun_Http_Request extends Zend_Http_Client {

    /**
     * @var Billrun_Http_Authentication_Base
     */
    protected $authenticator;

    public function request($method = null) {
        $this->authenticate();
        
        $response = parent::request($method);

        // Token invalid retry
        if ($this->authenticator && $this->authenticator->handleAuthFailure($response)) {
            Billrun_Factory::log("Authentication failure found. Retrying request to: " . $this->getUri(true), Zend_Log::INFO);
            $this->authenticate();
            return parent::request($method);
        }
        return $response;
    }
    
    /**
     * add authentication data to the request
     *
     * @return void
     */
    protected function authenticate() {
        $authParams = $this->config['authentication'] ?? false;
        if (empty($authParams)) {
            $this->authenticator = null;
            return true;
        }

        $this->authenticator = Billrun_Http_Authentication_Base::getInstance($this, $authParams);
        if (!$this->authenticator) {
            throw new Exception("Failed to authenticate");
        }

        $this->authenticator->authenticate();
    }
}