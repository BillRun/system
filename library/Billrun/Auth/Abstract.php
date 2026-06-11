<?php

/**
 * Abstract class that defines the rules for any Auth Protocol.
 */
abstract class Billrun_Auth_Abstract
{

    protected $request;

    /** * @var array Holds the configuration for this specific protocol 
     */
    protected $config = [];

    /**
     * @var string Child class should overwrite this (e.g., 'oidc')
     */
    protected $protocolName = null;

    public function __construct($request)
    {
        $this->request = $request;
        $this->loadConfig();
    }

    abstract public function login();
    abstract public function logout();

    /**
     * Creates a "Virtual" user session.
     * Expects a prepared array from the child class.
     * * @param array $userData Must contain ['username' => string, 'roles' => array]
     * @return boolean True on success, False on failure (missing data)
     */
    protected function createVirtualSession($userData, $extraData = [])
    {
        if (empty($userData['username'])) {
            Billrun_Factory::log("Auth: Virtual session failed. No username provided.", Zend_Log::NOTICE);
            return false;
        }

        if (empty($userData['roles']) || !is_array($userData['roles'])) {
            Billrun_Factory::log("Auth: Virtual session failed. User '{$userData['username']}' has no roles.", Zend_Log::NOTICE);
            return false;
        }

        $sessionData = [
            '_id'           => new Mongodloid_Id(),
            'username'      => $userData['username'],
            'roles'         => $userData['roles'],
            'last_login'    => new Mongodloid_Date(),
            'virtual_user'  => true,
            'auth_protocol' => isset($userData['protocol']) ? $userData['protocol'] : 'Internal',
            'auth_provider' => isset($userData['provider']) ? $userData['provider'] : null
        ];

        if (!empty($extraData)) {
            $sessionData = array_merge($sessionData, $extraData);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown IP';
        Billrun_Factory::auth()->getStorage()->write(array('current_user' => $sessionData));
        Billrun_Factory::log("Virtual user '{$userData['username']}' logged in via AuthWorker.", Zend_Log::INFO);

        $additionalParams = array('ip' => $ip);
        Billrun_AuditTrail_Util::trackChanges('login', 'login_' . $userData['username'], 'login', null, null, $additionalParams);

        return true;
    }

    protected function performLocalLogout()
    {
        $user = Billrun_Factory::user();
        if ($user === FALSE) {
            return true;
        }
        $username = $user->getUsername();
        $ip = $this->getIpAddress(); 

        Billrun_Factory::auth()->clearIdentity();
        $session = Yaf_Session::getInstance();
        foreach ($session as $k => $v) {
            unset($session[$k]);
        }
        session_destroy();
        Billrun_Factory::log("User '{$username}' logged out from IP: {$ip}", Zend_Log::INFO);

        return true;
    }

    protected function loadConfig() 
    {
        if (!empty($this->protocolName)) {
            $allConfigs = Billrun_Factory::config()->getConfigValue('auth.' . $this->protocolName, []);
            $requestedProvider = $this->request->get('provider');
            if (is_array($allConfigs)) {
                foreach ($allConfigs as $conf) {
                    if (isset($conf['name']) && $conf['name'] === $requestedProvider) {
                        $this->config = $conf;
                        break;
                    }
                }
            }
        }
    }

    protected function getIpAddress()
    {
        return $this->request->getServer('REMOTE_ADDR', 'Unknown IP');
    }
    
}