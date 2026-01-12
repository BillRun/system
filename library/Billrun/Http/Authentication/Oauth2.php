<?php

/**
 * OAuth 2.0 authentication handler for adding OAuth 2.0 authentication to HTTP requests
 */
class Billrun_Http_Authentication_Oauth2 extends Billrun_Http_Authentication_Base {
    /**
     * @var Billrun_AuthClient_Oauth2
     */
    protected $authClient;

    public function __construct(Billrun_Http_Request $request, $params = [])
    {
        parent::__construct($request, $params);
        $this->authClient = new Billrun_AuthClient_Oauth2($params);
    }

    /**
     * see parent::authenticate
     */
    public function authenticate()
    {
        try {
            $tokenData = $this->authClient->fetchTokenData();
            if (!empty($tokenData['access_token'])) {
                $this->request->setHeaders('Authorization', "Bearer {$tokenData['access_token']}");
            }
        } catch (Exception $e) {
            Billrun_Factory::log("OAuth2 Auth: " . $e->getMessage(), Zend_Log::WARN);
        }
    }

    public function handleAuthFailure(Zend_Http_Response $response)
    {
        if ($response->getStatus() === 401) {
            $this->authClient->clearCache();
            return true;
        }
        return false;
    }

}