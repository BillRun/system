<?php


use Jumbojett\OpenIDConnectClient;

class Billrun_Auth_Oidc extends Billrun_Auth_Abstract
{
    protected $protocolName = 'oidc';
    protected $baseUrl;
    protected $redirectUri;

    public function __construct($request)
    {
        parent::__construct($request);
        $this->baseUrl = Billrun_Util::getBaseUrl($this->request);
        $this->redirectUri = $this->baseUrl . 'auth/login?protocol=oidc';
        if (isset($this->config['name'])) {
            $this->redirectUri .= '&provider=' . $this->config['name'];
        }
    }

    public function login()
    {
        if (empty($this->config) || empty($this->config['issuer_url'])) {
            Billrun_Factory::log("AuthOpenIDConnect: Missing or invalid configuration for provider.", Zend_Log::ERR);
            header("Location: " . $this->baseUrl . '?error=missing_config');
            exit;
        }
        Billrun_Factory::log("AuthOpenIDConnect: login() started for " . (isset($this->config['name']) ? $this->config['name'] : 'unknown'), Zend_Log::INFO);
        $request = $this->request;
        $session = Yaf_Session::getInstance();
        if ($request->get('return_to')) {
            $session->offsetSet('oidc_return_to', $request->get('return_to'));
        }

        $oidc = new OpenIDConnectClient(
            $this->config['issuer_url'],
            $this->config['client_id'],
            $this->config['client_secret']
        );

        $oidc->setRedirectURL($this->redirectUri);
        $oidc->addScope(['openid', 'profile', 'email', 'billrun']);

        if (empty($request->get('code'))) {
            $oidc->authenticate();
        }

        try {
            $oidc->authenticate();
            $fullUserInfo = $oidc->requestUserInfo();
            $userData = $this->mapUserToBillRun($fullUserInfo);
            $extraData = ['id_token' => $oidc->getIdToken()];
            if ($this->createVirtualSession($userData, $extraData)) {
                Billrun_Factory::log("AuthOpenIDConnect: Success.", Zend_Log::INFO);
                $storedReturnTo = $session->offsetGet('oidc_return_to');
                $finalDestination = !empty($storedReturnTo) ? $storedReturnTo : $this->baseUrl;
                $session->offsetUnset('oidc_return_to');
                header("Location: " . $finalDestination);
                exit;
            } else {
                header("Location: " . $this->baseUrl . '?error=session_error');
                exit;
            }
        } catch (Exception $e) {
            Billrun_Factory::log("AuthOpenIDConnect: Error: " . $e->getMessage(), Zend_Log::ERR);
            header("Location: " . $this->baseUrl . '?error=login_failed');
            exit;
        }
    }

    public function logout()
    {
        $result = $this->performLocalLogout();
        return array(
            'status' => $result ? 1 : 0,
            'desc' => 'success',
            'details' => $result
        );
    }

    private function mapUserToBillRun($oidcData)
    {
        $dataArray = (array)$oidcData;
        $username = isset($dataArray['preferred_username'])
            ? $dataArray['preferred_username']
            : (isset($dataArray['sub']) ? $dataArray['sub'] : null);
        $roles = [];

        if (isset($dataArray['billrun_roles'])) {
            $billrunRoles = (array)$dataArray['billrun_roles'];
            if (isset($billrunRoles['billing'])) {
                $roles = (array)$billrunRoles['billing'];
            }
        }
        return [
            'username' => $username,
            'roles'    => $roles,
            'protocol' => 'Oidc',
            'provider' => isset($this->config['name']) ? $this->config['name'] : null
        ];
    }
}