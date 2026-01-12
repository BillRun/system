<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2025 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * OAuth2 Refresh action class
 *
 * @package  Action
 * @since    5.14
 */
class Oauth2refreshAction extends ApiAction {
    use Billrun_Traits_Api_UserPermissions;

    /**
     * Execute the OAuth2 token exchange
     */
    public function execute() {
        $this->allowed();
        Billrun_Factory::log()->log("Execute oauth2refresh api", Zend_Log::INFO);
        $request = $this->getRequest()->getRequest();

        // 1. Validate Input
        if (empty($request['code'])) {
            $this->setError('Missing code param', $request);
            return TRUE;
        }
        if (empty($request['state'])) {
            $this->setError('Missing state param', $request);
            return TRUE;
        }

        $code = $request['code'];
        $configKey = $request['state'];

        // 2. Load Configuration
        $configPath = "shared_secret." . $configKey;
        $oauthConfig = Billrun_Factory::config()->getConfigValue($configPath);

        if (empty($oauthConfig)) {
            Billrun_Factory::log()->log("OAuth2: Config key '{$configKey}' not found.", Zend_Log::ERR);
            $this->setError('Invalid Configuration Key', $request);
            return TRUE;
        }

        $tokenUri = $oauthConfig['token_uri'] ?? '';
        $clientId = $oauthConfig['client_id'] ?? '';
        $clientSecret = $oauthConfig['client_secret'] ?? '';
        $redirectUri = Billrun_Util::getBaseUrl($this->getRequest()) . 'api/oauth2refresh';

        if (empty($tokenUri) || empty($clientId) || empty($clientSecret)) {
             $this->setError('Incomplete OAuth Configuration', $request);
             return TRUE;
        }

        try {
            // Note: We do NOT send 'grant_type' here, so the Helper will default it to 'authorization_code'.
            $params = [
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri
            ];

            $body = Billrun_AuthClient_Oauth2::exchangeCodeForToken($tokenUri, $params);
            
            $refreshToken = $body['refresh_token'] ?? '';

            if (empty($refreshToken)) {
                 Billrun_Factory::log()->log("OAuth2: No refresh token received.", Zend_Log::WARN);
            }

            //Redirect back to Frontend
            $baseUrl = Billrun_Util::getBaseUrl($this->getRequest());
            $frontendUrl = $baseUrl . '#/oauth/callback';
            
            $redirectLocation = $frontendUrl . 
                                "?refresh_token=" . urlencode($refreshToken) . 
                                "&state=" . urlencode($configKey);
            
            Billrun_Factory::log()->log("OAuth2 Success, redirecting...", Zend_Log::INFO);
            $this->getController()->redirect($redirectLocation);
            
            return FALSE; 

        } catch (Exception $e) {
            Billrun_Factory::log()->log("OAuth2 Exception: " . $e->getMessage(), Zend_Log::ERR);
            $this->setError('Token exchange failed: ' . $e->getMessage(), $request);
            return TRUE;
        }
    }

    protected function getPermissionLevel() {
        return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
    }
}