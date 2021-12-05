<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';


/**
 * Description of Auth
 * TODO merge  this logic withe  the admin login logic
 * @author eran
 */
class AuthAction extends ApiAction  {

	protected $cors = false;

	public function execute() {
		$params = array_merge($this->getRequest()->getRequest(), $this->getRequest()->getPost());
		switch(Billrun_Util::getFieldVal($params['action'],'')) {
			case 'logout':
				$result = $this->logout($params);
				break;
			
			case 'login' :
			default		 :
				$result = $this->login($params);
				break;
		}

		//A small protective messure
		unset($params['password']);
		
		$this->getController()->setOutput(array(array(
				'status' => empty($result) ? 0 : 1,
				'desc' => 'success',
				'details' => $result,
				'input' => $params,
			)));

	}
	
	/**
	 *  Login a user to the system.
	 * @param type $params the  login credentials
	 * @return boolean array  with  the user data or FALSE  if  the login wasn't  successful.
	 * @todo Refactor this function, no reason for all this very simple logic to be in one long function, seperate to functions.
	 */
	protected function login($params) {
		if (Billrun_Factory::user() !== FALSE) {
			// if already logged-in redirect to admin homepage
			$userData = array(
				'user' => Billrun_Factory::user()->getUsername(),
				'permissions' => Billrun_Factory::user()->getPermissions(),
				'last_login' => Billrun_Factory::user()->getLastLogin(),
			);
			return $userData;
		}
		if (isset($params['username'], $params['password'])/* && $this->getRequest()->isPost()*/) {
			$db = Billrun_Factory::db()->usersCollection()->getMongoCollection();

			$username = $params['username'];
			$password = $params['password'];


			if ($username != '' && !is_null($password)) {
				$adapter = new Zend_Auth_Adapter_MongoDb($db, 'username', 'password');

				$adapter->setIdentity($username);
				$adapter->setCredential($password);

				$result = Billrun_Factory::auth()->authenticate($adapter);

				if ($result->isValid()) {
					$ip = $this->getRequest()->getServer('REMOTE_ADDR', 'Unknown IP');
					Billrun_Factory::log('User ' . $username . ' logged in to admin panel from IP: ' . $ip, Zend_Log::INFO);
					// TODO: stringify to url encoding (A-Z,a-z,0-9)
					$ret_action = $this->getRequest()->get('ret_action');
					//				if (empty($ret_action)) {
					//					$ret_action = 'admin';
					//				}
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
					$user_model->updateUserLastLogin(Billrun_Factory::user()->getMongoId(true));
					$entity = Billrun_Factory::db()->usersCollection()->query(array('username' => $username))->cursor()->current();
					Billrun_Factory::auth()->getStorage()->write(array('current_user' => $entity->getRawData()));
					return $userData;
				} else { // LDAP
					$entity = new stdClass();
					$result = Billrun_Factory::chain()->trigger('userAuthenticate', array($username, $password, &$this, &$entity));
					if ($result) {
						$ip = $this->getRequest()->getServer('REMOTE_ADDR', 'Unknown IP');
						Billrun_Factory::log('User ' . $username . ' logged in to admin panel from IP: ' . $ip, Zend_Log::INFO);
						// TODO: stringify to url encoding (A-Z,a-z,0-9)
						$ret_action = $this->getRequest()->get('ret_action');
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

						return $userData;
					}
				}
			}
		}
		return FALSE;
	}

	/**
	 * Logout  the current user.
	 * @param type $params unused
	 * @return boolean TRUE  if the logout  was successful  FALSE if the user is not loggged in
	 * @todo This function is duplicated in the admin controller, 
	 * if billrun cloud will have both we should merge the classes to avoid confusion.
	 */
	protected function logout($params) {
		if (Billrun_Factory::user() === FALSE) {
			return TRUE;
		}
		
		$username = Billrun_Factory::user()->getUsername();
		$ip = $this->getRequest()->getServer('REMOTE_ADDR', 'Unknown IP');

		Billrun_Factory::auth()->clearIdentity();
		$session = Yaf_Session::getInstance();
		foreach ($session as $k => $v) {
			unset($session[$k]);
		}			
		session_destroy();
		Billrun_Factory::log('User ' . $username . ' logged out from IP: ' . $ip, Zend_log::INFO);
		return TRUE;
	}
}
