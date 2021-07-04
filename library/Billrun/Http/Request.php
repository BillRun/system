<?php

/**
 * A helper class which handles sending HTTP requests
 */
class Billrun_Http_Request {
    
    /**
     * Request's URL
     *
     * @var string
     */
    protected $url = '';
        
    /**
     * Request's data (body)
     *
     * @var array
     */
    protected $data = [];
    
    /**
     * Additional parameters
     *
     * @var array
     */
    protected $params = [];

    public function __construct($url, $data = [], $params = []) {
        $defaultParams = [
            'method' => Zend_Http_Client::POST,
            'headers' => ['Accept-encoding' => 'deflate'],
            'timeout' => null,
            'ssl_verify' => null,
            'return_response' => false,
            'authentication' => false,
        ];
        
        $this->url = $url;
        $this->data = $data;
        $this->params = array_merge($defaultParams, $params);
    }
    
    /**
     * Sends the HTTP request
     *
     * @return array or false on failure
     */
    public function send() {
        $this->authenticate();
        return Billrun_Util::sendRequest($this->getUrl(), $this->getData(), $this->getMethod(), $this->getHeaders(),
            $this->getTimeout(), $this->getSslVerify(), $this->returnEntireResponse());
    }
    
    /**
     * get request url
     *
     * @return string
     */
    public function getUrl() {
        return $this->url;
    }
    
    /**
     * set request url
     *
     * @param  string $url
     * @return void
     */
    public function setUrl($url) {
        $this->url = $url;
    }
    
    /**
     * get request data
     *
     * @return array
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * set request ata
     *
     * @param  array $data
     * @return void
     */
    public function setData($data) {
        $this->data = $data;
    }
    
    /**
     * add data to the request
     *
     * @param  array $data
     * @return void
     */
    public function addData($data) {
        $this->data = array_merge($this->data, $data);
    }
    
    /**
     * get request method (POST/GET/PUT/DELETE)
     *
     * @return string
     */
    public function getMethod() {
        return $this->params['method'] ?? Zend_Http_Client::POST;
    }
    
    /**
     * get request headers
     *
     * @return array
     */
    public function getHeaders() {
        return $this->params['headers'];
    }
    
    /**
     * set request headers
     *
     * @param  array $headers
     * @return void
     */
    public function setHeaders($headers) {
        $this->params['headers'] = $headers;
    }
    
    /**
     * add header to the request
     *
     * @param  string $header
     * @param  mixed $value
     * @return void
     */
    public function addHeader($header, $value) {
        $this->params['headers'][$header] = $value;
    }
    
    /**
     * get request timeout
     *
     * @return float or null if not set
     */
    protected function getTimeout() {
        return $this->params['timeout'] ?? null;
    }
        
    /**
     * get request ssl verification
     *
     * @return boolean or null if not set
     */
    protected function getSslVerify() {
        return $this->params['ssl_verify'] ?? null;
    }
        
    /**
     * should return the entire response or only the body
     *
     * @return boolean
     */
    protected function returnEntireResponse() {
        return $this->params['returnResponse'] ?? false;
    }
    
    /**
     * add authentication data to the request
     *
     * @return void
     */
    protected function authenticate() {
        $authParams = $this->params['authentication'] ?? false;
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