<?php

/**
 * Internal Authentication (code copied from old auth.php action.
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

class Billrun_Auth_Internal extends Billrun_Auth_Abstract {

    public function login() {
        $params = array_merge($this->request->getRequest(), $this->request->getPost());
        $userData = FALSE;

        if (Billrun_Factory::user() !== FALSE) {
            $userData = array(
                'user' => Billrun_Factory::user()->getUsername(),
                'permissions' => Billrun_Factory::user()->getPermissions(),
                'last_login' => Billrun_Factory::user()->getLastLogin(),
                'protocol' => Billrun_Factory::user()->getProtocol(),
            );
        }

        elseif (isset($params['username'], $params['password'])) {
            $db = Billrun_Factory::db()->usersCollection()->getMongoCollection();

            $username = $params['username'];
            $password = $params['password'];

            if ($username != '' && !is_null($password)) {
                $adapter = new Zend_Auth_Adapter_MongoDb($db, 'username', 'password');

                $adapter->setIdentity($username);
                $adapter->setCredential($password);

                $result = Billrun_Factory::auth()->authenticate($adapter);

                if ($result->isValid()) {
                    $ip = $this->getIpAddress();
                    Billrun_Factory::log('User ' . $username . ' logged in to admin panel from IP: ' . $ip, Zend_Log::INFO);
                    
                    // TODO: stringify to url encoding (A-Z,a-z,0-9)
                    $ret_action = $this->request->get('ret_action');
                    
                    $entity = Billrun_Factory::db()->usersCollection()->query(array('username' => $username))->cursor()->current();
                    Billrun_Factory::auth()->getStorage()->write(array('current_user' => $entity->getRawData()));

                    $additionalParams = array(
                        'ip' => $ip,
                    );
                    Billrun_AuditTrail_Util::trackChanges('login', 'login_' . $username, 'login', null, null, $additionalParams);

                    $userData = array(
                        'user' => Billrun_Factory::user()->getUsername(),
                        'permissions' => Billrun_Factory::user()->getPermissions(),
                        'last_login' => Billrun_Factory::user()->getLastLogin(),
                    );
                    
                    // save user last login and update current
                    $user_model = new UsersModel();
                    $entity = Billrun_Factory::db()->usersCollection()->query(array('username' => $username))->cursor()->current();
                    Billrun_Factory::auth()->getStorage()->write(array('current_user' => $entity->getRawData()));
                } else { // LDAP
                    $entity = new stdClass();
                    // Passing $this (The Auth Object) instead of the Action Controller.
                    $result = Billrun_Factory::chain()->trigger('userAuthenticate', array($username, $password, &$this, &$entity));
                    
                    if ($result) {
                        $ip = $this->getIpAddress();
                        Billrun_Factory::log('User ' . $username . ' logged in to admin panel from IP: ' . $ip, Zend_Log::INFO);
                        
                        // TODO: stringify to url encoding (A-Z,a-z,0-9)
                        $ret_action = $this->request->get('ret_action');
                        
                        $entity = new stdClass();
                        $entity->username = $username;
                        $entity->roles = array();
                        $entity->last_login = null;
                        $xml = simplexml_load_string($result);
                        $groups = (array) $xml->PARAMS->IT_OUT_PARAMS->MemberOf->Group;
                        $entity->roles = array();
                        foreach ($groups as $group) {
                            $entity->roles[] = str_ireplace('billrun_', '', $group);
                        }
                        Billrun_Factory::auth()->getStorage()->write(array('current_user' => (array) $entity));

                        $userData = array(
                            'user' => Billrun_Factory::user()->getUsername(),
                            'permissions' => Billrun_Factory::user()->getPermissions(),
                            'last_login' => Billrun_Factory::user()->getLastLogin(),
                        );
                    }
                }
            }
        }
        unset($params['password']);
        return array(
            'status' => empty($userData) ? 0 : 1,
            'desc' => 'success',
            'details' => $userData,
            'input' => $params,
        );
    }

    public function logout() {
        $params = array_merge($this->request->getRequest(), $this->request->getPost());
        if (Billrun_Factory::user() === FALSE) {
            $result = TRUE;
        }
        else{
        $username = Billrun_Factory::user()->getUsername();
        $ip = $this->getIpAddress();
        $result = $this->performLocalLogout();
        Billrun_Factory::log('User ' . $username . ' logged out from IP: ' . $ip, Zend_Log::INFO);
        $result = TRUE;
    }
        unset($params['password']);

    return array(
        'status' => $result ? 1 : 0,
        'desc' => 'success',
        'details' => $result,
        'input' => $params,
    );
    }
    
}