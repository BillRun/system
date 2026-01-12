<?php

/**
 * Client to handle OAuth 2.0 Token Retrieval and Caching
 */
class Billrun_AuthClient_Oauth2 {

    protected $params;

    /**
     * @param array $params Structure:
     * [
     * 'access_token_url' => string,
     * 'data' => array (client_id, client_secret, grant_type, etc),
     * 'cache' => boolean (default true),
     * 'serialize_data_for_cache_key' => boolean
     * ]
     */
    public function __construct(array $params) {
        $this->params = $params;
    }

    /**
     * Fetches the token data.
     * * @return array 
     * - If from CACHE: returns ['access_token' => '...']
     * - If from REMOTE: returns full response ['access_token' => '...', 'refresh_token' => '...', 'expires_in' => 123]
     * @throws Exception
     */
    public function fetchTokenData() {
        $useCache = $this->params['cache'] ?? true;
        $cacheKey = $this->getCacheKey();
        $cache = Billrun_Factory::cache();
        
        if ($useCache && !empty($cache)) {
            $cachedToken = $cache->get($cacheKey);
            if (!empty($cachedToken)) {
                return ['access_token' => $cachedToken];
            }
        }
        
        $url = $this->params['access_token_url'] ?? '';
        $data = $this->params['data'] ?? [];
        
        $request = new Billrun_Http_Request($url);
        $request->setHeaders(['Cache-Control' => 'no-store', 'Content-Type' => 'application/x-www-form-urlencoded']);
        $request->setParameterPost($data);
        $responseBody = (string)$request->request(Billrun_Http_Request::POST)->getBody();
        $response = json_decode($responseBody, JSON_OBJECT_AS_ARRAY);
        $accessToken = $response['access_token'] ?? '';

        if (empty($accessToken)) {
            unset($data['client_secret']);
            Billrun_Factory::log("OAuth 2.0 - failed to generate access token. URL: {$url}, Data: " . print_r($data, true), Zend_Log::ERR);
            throw new Exception("Failed to generate access token for OAuth 2.0");
        }
        
        if ($useCache && !empty($cache)) {
            $cacheTtl = $response['expires_in'] ?? false; 
            $cache->set($cacheKey, $accessToken, null, max($cacheTtl - 10, 10));
        }
        
        return $response;
    }

    /**
     * Force clear the cache
     */
    public function clearCache() {
        $useCache = $this->params['cache'] ?? true;
        if ($useCache && !empty($cache = Billrun_Factory::cache())) {
            $cache->remove($this->getCacheKey());
        }
    }

    /**
     * Generates consistent cache key
     */
    protected function getCacheKey() {
        $url = $this->params['access_token_url'] ?? '';
        $data = $this->params['data'] ?? [];
        $serializeDataForCacheKey = $this->params['serialize_data_for_cache_key'] ?? false;
        return "Billrun_AuthClient_Oauth2_token_" . md5(serialize($url)) . (!empty($serializeDataForCacheKey) ? "_" . md5(serialize($data)) : "");
    }
}