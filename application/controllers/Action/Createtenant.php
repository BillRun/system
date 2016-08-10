<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Recreate invoices action class
 *
 * @package  Action
 * @since    4.2
 */
class CreatetenantAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	protected $request;
	protected $status = true;
	protected $desc = '';
	
	protected $userName;
	protected $password;
	protected $tenant;
	protected $db_user;
	protected $db_pass;
	protected $db_name;


	public function execute() {
		$this->allowed();
		Billrun_Factory::log("Execute save version", Zend_Log::INFO);
		if (!AdminController::authorized('write')) {
			return;
		}
		
		$this->init();
		
		if (!$this->generateDbUsernameAndPassword() ||
			!$this->createConfigFile()				||
			!$this->createBillrunUser()				||
			!$this->createDB() ||
			!$this->createDbConfig()) {
			$this->status = false;
		}

		$this->response();
	}
	
	protected function init() {
		$this->request = $this->getRequest()->getRequest(); // supports GET / POST requests 
		$this->tenant = Billrun_Factory::config()->getTenant();
		$this->db_name = 'billing_' . $this->tenant;
		$this->userName = $this->request['email'];
		$this->password = $this->request['pass'];
	}
	
	protected function buildResponse() {
		return array(
			'status' => $this->status,
			'desc' => $this->desc,
		);
	}


	protected function response() {
		$response = $this->buildResponse();

		if (!$this->getController()->setOutput(array($response))) {
			Billrun_Factory::log("Failed to set message to controller. message: " . print_R($response, 1), Zend_Log::CRIT);
		}

		return false;
	}
	
	protected function replaceConfigValues($config) {
		$host = '127.0.0.1:27017';
		$port = '';
		$search = array('[USER]', '[PASS]', '[NAME]', '[HOST]', '[PORT]');
		$replace = array($this->db_user, $this->db_pass, $this->db_name, $host, $port);
		return str_replace($search, $replace, $config);
	}
	
	protected function createConfigFile() {
		if (empty($configFileBasePath = Billrun_Config::getMultitenantConfigPath())) {
			$this->desc = 'System does not work in multi-tenant method or cannot get multi-tenant config base path.';
			return false;
		}
		$baseConfigPath = $configFileBasePath . DIRECTORY_SEPARATOR . Billrun_Factory::config()->getConfigValue('create_tenant.ini.base_config', 'base.ini');
		if (empty($baseConfig = file_get_contents($baseConfigPath))) {
			$this->desc = 'Cannot read base file config in path: "' . $baseConfigPath . '"';
			return false;
		}
		$config = $this->replaceConfigValues($baseConfig);
		$configPath = $configFileBasePath . $this->tenant;
		if (!file_put_contents($configPath, $config)) {
			$this->desc = 'Cannot save config file to path: "' . $configPath . '"';
			return false;
		}
		//Billrun_Factory::config()->addConfig($configPath);
		return true;
	}
	
	protected function createDB() {
		//$options = Billrun_Factory::config()->getConfigValue('db', array());
		//$db = Billrun_Db::getInstance($options);
		return true;
	}

	protected function createDbConfig() {
		return true;
	}

	protected function createBillrunUser() {
		$query = array(
			'username' => $this->userName,
			'password' => password_hash($this->password, PASSWORD_DEFAULT),
			'roles' => array('read'),
		);
		if (!Billrun_Factory::db()->usersCollection()->insert($query)) {
			return false;
		}
		return true;
	}
	
	protected function generateDbUsernameAndPassword() {
		$this->db_user = 'user_' . $this->tenant;
		$arr = array('t' => Billrun_Util::generateCurrentTime(), 'r' => Billrun_Util::generateRandomNum());
		$this->db_pass = Billrun_Util::generateArrayStamp($arr);
		return true;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}
