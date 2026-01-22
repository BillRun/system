<?php

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * OAuth2 Refresh action class
 * Handles both the OAuth Callback (GET) and Saving Settings (POST)
 *
 * @package  Action
 */
class Oauth2refreshAction extends ApiAction
{
    use Billrun_Traits_Api_UserPermissions;

    public function execute()
    {
        $this->allowed();
        $request = $this->getRequest()->getRequest();

        if ($this->getRequest()->isPost() && isset($request['action']) && $request['action'] === 'save') {
            return $this->handleSaveRequest($request);
        }
        return $this->handleCallbackRequest($request);
    }

    protected function handleSaveRequest($request)
    {
        $jsonString = $request['data'] ?? '{}';
        $dataToSave = json_decode($jsonString, true);
        
        if (empty($dataToSave)) {
            $this->setError('No data provided for save', $request);
        }
        $configKey = $dataToSave['config_path'] ?? '';
        try {
            $targetKey = $configKey . '.oauth2';
            $config_model = new ConfigModel();
            if ($config_model->updateConfig($targetKey, $dataToSave, true)) {
                Billrun_Factory::log()->log("OAuth2: Saved settings for '{$targetKey}'", Zend_Log::INFO);
                $this->getController()->setOutput(array(
                    'status' => 1,
                    'desc' => 'Settings saved successfully',
                ));
            } else {
                Billrun_Factory::log()->log("OAuth2: ConfigModel failed to update settings for '{$targetKey}'", Zend_Log::ERR);
                $this->setError('Failed to save configuration', $request);
            }
        } catch (Exception $e) {
            Billrun_Factory::log()->log("OAuth2 Save Failed: " . $e->getMessage(), Zend_Log::ERR);
            $this->setError('Database Save Failed: ' . $e->getMessage(), $request);
        }
    }

    protected function handleCallbackRequest($request)
    {
        if (empty($request['code']) || empty($request['state'])) {
            $this->setError('Invalid Request: Missing code or state', $request);
        }

        $code = $request['code'];
        $rawState = isset($request['state']) ? $request['state'] : '';
        $stateParts = explode('|', $rawState);
        $configKey = $stateParts[0];
        $oauthConfig = $this->getOauthSettings($configKey);

        $tokenUri     = $oauthConfig['token_url'] ?? '';
        $clientId     = $oauthConfig['client_id'] ?? '';
        $clientSecret = $oauthConfig['client_secret'] ?? '';

        if (empty($tokenUri) || empty($clientId) || empty($clientSecret)) {
            $this->setError('Incomplete Configuration', $request);
        }

        try {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $dynamicRedirectUri = "$protocol://" . $_SERVER['HTTP_HOST'] . "/api/oauth2refresh";
            $params = [
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $dynamicRedirectUri
            ];

            $body = Billrun_AuthClient_Oauth2::exchangeCodeForToken($tokenUri, $params);
            $refreshToken = $body['refresh_token'] ?? '';
            $refreshDate = date('Y-m-d H:i:s');
            $this->redirectBackToFrontend($refreshToken, $refreshDate, $rawState);
        } catch (Exception $e) {
            Billrun_Factory::log()->log("OAuth2 Exception: " . $e->getMessage(), Zend_Log::ERR);
            $this->setError('Token exchange failed', $request);
        }   
    }

    protected function getOauthSettings($key)
    {
        $config = Billrun_Factory::config()->getConfigValue($key);
        return $config['oauth2'] ?? [];
    }


    protected function redirectBackToFrontend($refreshToken, $refreshDate, $state)
    {
        $redirectLocation = "/#/oauth/callback" .
            "?refresh_token=" . urlencode($refreshToken) .
            "&refresh_date=" . urlencode($refreshDate) .
            "&state=" . urlencode($state);

        Billrun_Factory::log()->log("OAuth2 Success, redirecting to " . $redirectLocation, Zend_Log::INFO);
        $this->getController()->redirect($redirectLocation);
    }

    protected function getPermissionLevel()
    {
        return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
    }
}
