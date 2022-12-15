<?php

/**
 * A helper class which handles sending HTTP requests
 */
class Billrun_Http_Request extends Zend_Http_Client {

    public function request($method = null) {
        $this->authenticate();
        $retries = 0;
        $shouldRetry = Billrun_Factory::config()->getConfigValue('network.http_retry',false);
        do {
            try {
                $retValue = parent::request($method);
            } catch ( \Exception $e ) {
                Billrun_Factory::log("Billrun_Http_Request::request : Request to - {$this->uri} Failed, with {$e->getCode()} : {$e->getMessage()}");
            }
        } while(    $shouldRetry &&
                    !$this->evaluateResponseValidity($retValue, $method) &&
                    ($retries++ < Billrun_Factory::config()->getConfigValue('network.http_retry_max',10)) );

        return $retValue;
    }

    protected function evaluateResponseValidity($response, $method)  {
        if( $response->getStatus() == 202 ) {
            sleep(Billrun_Factory::config()->getConfigValue('network.http_retry_wait',10));
            return false;
        }
        return  true;
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
