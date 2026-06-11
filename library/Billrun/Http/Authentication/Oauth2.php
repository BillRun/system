<?php

/**
 * OAuth 2.0 authentication handler for adding OAuth 2.0 authentication to HTTP requests
 */
class Billrun_Http_Authentication_Oauth2 extends Billrun_Http_Authentication_Base {
    
    /**
     * see parent::authenticate
     */
    public function authenticate() {
        $accessToken = $this->getAccessToken();
        if (!empty($accessToken)) {
            $this->request->setHeaders('Authorization', "Bearer {$accessToken}");
        }
    } 
    
    /**
     * Get access token needed as a header for OAuth 2.0 authentication
     * If cache is enabled, stores it for until the end of the token's expiration
     *
     * @return string
     */
    protected function getAccessToken() {
        $url = $this->params['access_token_url'] ?? '';
        $data = $this->params['data'] ?? [];
        $useCache = $this->params['cache'] ?? true;
        
        if ($useCache && !empty($cache = Billrun_Factory::cache())) {
            $cacheKey = $this->getCacheKey();
            $accessToken  = $cache->get($cacheKey);
            if (!empty($accessToken)) {
                return $accessToken;
            }
        }
        
        $request = new Billrun_Http_Request($url);
        $request->setHeaders(['Cache-Control' => 'no-store', 'Content-Type' => 'application/x-www-form-urlencoded']);
        $request->setParameterPost($data);
        $response = json_decode((string)$request->request(Billrun_Http_Request::POST)->getBody(), JSON_OBJECT_AS_ARRAY);
        $accessToken = $response['access_token'] ?? '';

        if (empty($accessToken)) {
            unset($data['client_secret']);
            Billrun_Factory::log("OAuth 2.0 - failed to generate access token. URL: {$url}, Data: " . $data, Zend_Log::ERR);
            throw new Exception("Failed to generate access token for OAuth 2.0");
        }
        
        if ($useCache && !empty($cache)) {
            $cacheKey = $this->getCacheKey();
            $cacheTtl = $response['expires_in'] ?? false;
            $cache->set($cacheKey, $accessToken, null, max($cacheTtl-10, 10));
        }
        
        return $accessToken;
    }

    /**
     * Generates a consistent cache key based on the access token URL.
     * @return string
     */
    private function getCacheKey() {
        $url = $this->params['access_token_url'] ?? '';
        $data = $this->params['data'] ?? [];
        $serializeDataForCacheKey = $this->params['serialize_data_for_cache_key'] ?? false;
        return self::class . "_access_token_" . md5(serialize($url)) . (!empty($serializeDataForCacheKey) ? "_" . md5(serialize($data)) : "");
    }

    /**
     * Handles a failed authentication response to determine if a retry is possible.
     *
     * @param Zend_Http_Response $response The failed response object.
     * @return bool True if the failure was handled and a retry should be attempted, false otherwise.
     */
    public function handleAuthFailure(Zend_Http_Response $response)
    {
        if ($response->getStatus() === 401) {
            $this->clearAccessTokenCache();
            return true;
        }
        return false;
    }

    /**
     * Clears the cached access token.
     * This is called by handleAuthFailure to force a token refresh.
     */
    public function clearAccessTokenCache()
    {
        $useCache = $this->params['cache'] ?? true;
        if ($useCache && !empty($cache = Billrun_Factory::cache())) {
            $cacheKey = $this->getCacheKey();
            $cache->remove($cacheKey);
        }
    }
}