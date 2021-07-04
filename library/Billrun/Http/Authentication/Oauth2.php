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
            $this->request->addHeader('Authorization', "Bearer {$accessToken}");
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
            $cacheKey = self::class . "_access_token_" . md5(serialize($url)) . "_" . md5(serialize($data));
            $accessToken  = $cache->get($cacheKey);
            if (!empty($accessToken)) {
                return $accessToken;
            }
        }
        
        $params = [
            'headers' => [
                'Cache-Control' => 'no-store',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ];
        
        $request = new Billrun_Http_Request($url, $data, $params);
        $response = json_decode((string)$request->send(), JSON_OBJECT_AS_ARRAY);
        $accessToken = $response['access_token'] ?? '';

        if (empty($accessToken)) {
            unset($data['client_secret']);
            Billrun_Factory::log("OAuth 2.0 - failed to generate access token. URL: {$url}, Data: " . $data, Zend_Log::ERR);
            throw new Exception("Failed to generate access token for OAuth 2.0");
        }
        
        if ($useCache && !empty($cache)) {
            $cacheTtl = $response['expires_in'] ?? false;
            $cache->set($cacheKey, $accessToken, null, $cacheTtl);
        }
        
        return $accessToken;
    }
}