<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Create tenant action class
 *
 * @package	BR cloud
 * @since	5.0
 */
class CreatetenantAction extends ApiAction {

	protected $request;
	protected $status = true;
	protected $desc = '';
	protected $db;
	protected $userName;
	protected $password;
	protected $tenant;
	protected $db_user;
	protected $db_pass;
	protected $db_name;

	public function execute() {
		Billrun_Factory::log('Create Tenant - starting API', Zend_Log::INFO);
		if (!Billrun_Config::isMultitenantEnabled()) {
			Billrun_Factory::log('Create Tenant - System is not running in multi-tenant mode', Zend_Log::INFO);
			return false;
		}
		if (!$this->isWhiteListed()) {
			Billrun_Factory::log('Create Tenant - not allowed', Zend_Log::INFO);
			return false;
		}

		$this->init();

		// Check if tenant already exists
		if (file_exists(Billrun_Config::getMultitenantConfigPath() . $this->tenant . '.ini')) {
			$this->desc = 'Cannot create tenant.';
			Billrun_Factory::log('Create Tenant - tenant already exists', Zend_Log::INFO);
			return false;
		}
		
		if (!$this->createUserInBillrun() ||
			!$this->generateDbUsernameAndPassword() ||
			!$this->createConfigFile() ||
			!$this->createTenantDbUser() ||
			!$this->connectToDb() ||
			!$this->initTenantDatabase() ||
			!$this->createUserInTenantBillrun() ||
			!$this->createTenantFolders()) {
			Billrun_Factory::log('Create Tenant - error: ' . $this->desc, Zend_Log::INFO);
			$this->status = false;
		}

		$this->response();
	}

	/**
	 * Initialize the tenant database: create collections with indexes, load base
	 * config + taxes, enrich config with tenant-specific data, and apply migrations.
	 *
	 * @return bool
	 */
	protected function initTenantDatabase() {
		Billrun_Factory::log('Create Tenant - Initializing tenant database', Zend_Log::INFO);

		// Schema + base data. Skip the default first user - the tenant's admin
		// is inserted later from the API request via createUserInTenantBillrun().
		if (!(new DbinitModel())->execute($this->db, $this->getController(), [
			'create_first_user' => false,
		])) {
			$this->desc = 'Failed to initialize tenant database schema';
			return false;
		}

		// Enrich the base config with tenant-specific data (shared secret,
		// company name, etc.) before migrations read it.
		if (!$this->enrichConfigWithTenantData()) {
			return false;
		}

		if (!(new DbmigrateModel())->execute($this->db, $this->getController())) {
			$this->desc = 'Failed to apply migrations to tenant database';
			return false;
		}

		return true;
	}

	/**
	 * Update the base config record with tenant-specific metadata.
	 *
	 * @return bool
	 */
	protected function enrichConfigWithTenantData() {
		Billrun_Factory::log('Create Tenant - Enriching config with tenant data', Zend_Log::INFO);

		$cursor = $this->db->configCollection()
			->query()
			->cursor()
			->setReadPreference('RP_PRIMARY')
			->sort(['urt' => -1, '_id' => -1])
			->limit(1)
			->current();

		if (!$cursor || $cursor->isEmpty()) {
			$this->desc = 'Config record not found after initialization';
			return false;
		}

		$data = $cursor->getRawData();
		unset($data['_id']);

		if (!isset($data['shared_secret']) || !is_array($data['shared_secret'])) {
			$data['shared_secret'] = [];
		}
		$data['shared_secret'][] = Billrun_Utils_Security::generateSecretKey();
		// generate a per-tenant field-encryption key unless one is supplied via the environment
		Billrun_Utils_Encryption::ensureConfigKey($data);
		$data['creation_date'] = new Mongodloid_Date();
		$data['name'] = 'Initial Secret';
		$data['company_name'] = $this->tenant;
		$data['registration_date'] = new Mongodloid_Date();
		$data['tenant']['name']['v'] = $this->request['companyname'];
		$data['urt'] = new Mongodloid_Date();

		if (!$this->db->configCollection()->insert($data)) {
			$this->desc = 'Cannot save enriched config to DB';
			return false;
		}

		return true;
	}

	/**
	 * method that check if the request source IP is allowed to call create tenant
	 * 
	 * @return boolean true if allowed else false
	 */
	protected function isWhiteListed() {
		$ip_list = array($_SERVER['REMOTE_ADDR']);
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip_list_forward = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
			$ip_list = array_merge($ip_list, $ip_list_forward);
		}
		Billrun_Factory::log('Create Tenant - Got request from: ' . implode(', ', $ip_list), Zend_Log::INFO);
		$whiteList = Billrun_Factory::config()->getConfigValue('create_tenant.remotes.white_list', array());
		return !empty(array_intersect($ip_list, $whiteList));
	}

	public function init() {
		Billrun_Factory::log('Create Tenant - initializing...', Zend_Log::INFO);
		$this->request = $this->getRequest()->getRequest(); // supports GET / POST requests 
		$this->tenant = strtolower($this->request['tenant']);
		$this->db_name = 'billing_' . $this->tenant;
		$this->userName = $this->request['email'];
		$this->password = $this->request['password'];
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

	/**
	 * Create a subscriber in Billrun's env
	 * 
	 * @todo complete function - with API
	 */
	protected function createUserInBillrun() {
		Billrun_Factory::log('Create Tenant - Creating user in BillRun', Zend_Log::INFO);
		return true;
	}

	protected function replaceConfigValues($config) {
		$search = array('[USER]', '[PASS]', '[NAME]');
		$replace = array($this->db_user, $this->db_pass, $this->db_name);
		return str_replace($search, $replace, $config);
	}

	/**
	 * craete a .ini file for the tenant (with db connection parameters)
	 * 
	 * @return boolean
	 */
	protected function createConfigFile() {
		Billrun_Factory::log('Create Tenant - Creating configuraition file', Zend_Log::INFO);
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
		$configPath = $configFileBasePath . $this->tenant . '.ini';
		if (!file_put_contents($configPath, $config)) {
			$this->desc = 'Cannot save config file to path: "' . $configPath . '"';
			return false;
		}
		return true;
	}

	/**
	 * Create the tenant's MongoDB user via the createUser command.
	 *
	 * Equivalent to the legacy shell helper:
	 *   mongo admin -u<USER> -p<PASS> --eval "db = db.getSiblingDB('$1');
	 *       db.createUser({user: '$2', pwd: '$3', roles: [{role: '$4', db: '$1'}]})"
	 *
	 * Uses {@see Billrun_Factory::admindb()} for the admin-credentialed
	 * connection and routes createUser to the tenant DB via the underlying
	 * MongoDB driver so the user record lands in the tenant's system.users
	 * (matching the auth-db semantics of the original shell helper).
	 *
	 * @return boolean
	 */
	protected function createTenantDbUser() {
		Billrun_Factory::log('Create Tenant - Creating DB user for tenant', Zend_Log::INFO);

		$adminDb = Billrun_Factory::admindb();
		if (empty($adminDb)) {
			$this->desc = 'Cannot connect with admin credentials to create tenant DB user';
			return false;
		}

		$permissions = Billrun_Factory::config()->getConfigValue('create_tenant.db_permissions', 'readWrite');

		try {
			$adminDb->createUser($this->db_user, $this->db_pass, $permissions, $this->db_name);
		} catch (Exception $e) {
			$this->desc = 'Failed to create tenant DB user: ' . $e->getMessage();
			return false;
		}

		return true;
	}

	protected function connectToDb() {
		$options = Billrun_Factory::config()->getConfigValue('db');
		$options['user'] = $this->db_user;
		$options['password'] = $this->db_pass;
		$options['name'] = $this->db_name;
		if (!isset($options['options'])) {
			$options['options'] = array();
		}
		$options['options']['authSource'] = $this->db_name;
		$options['host'] = "localhost:27017"; // this is a hack because mongo does not create new instance otherwise
		$this->db = Billrun_Factory::db($options);
		if (empty($this->db)) {
			return false;
		}
		return true;
	}

	/**
	 * create an admin user in the tenant's Billrun instance
	 * 
	 * @return boolean
	 */
	protected function createUserInTenantBillrun() {
		Billrun_Factory::log('Create Tenant - Creating user for tenant', Zend_Log::INFO);
		$query = array(
			'username' => $this->userName,
			'password' => password_hash($this->password, PASSWORD_DEFAULT),
			'roles' => array('admin', 'read', 'write'),
		);
		if (!$this->db->usersCollection()->insert($query)) {
			$this->desc = 'Cannot create user in DB.';
			return false;
		}
		return true;
	}

	/**
	 * create a username and password for the tenant's DB
	 * 
	 * @return boolean
	 */
	protected function generateDbUsernameAndPassword() {
		Billrun_Factory::log('Create Tenant - Generating username and password', Zend_Log::INFO);
		$this->db_user = 'user_' . $this->tenant;
		$arr = array('t' => Billrun_Util::generateCurrentTime(), 'r' => Billrun_Util::generateRandomNum());
		$this->db_pass = Billrun_Util::generateArrayStamp($arr);
		return true;
	}

	/**
	 * Create shared folder for the tenant
	 * 
	 * @return boolean
	 * 
	 * @todo fix hard coded values
	 */
	protected function createTenantFolders() {
		Billrun_Factory::log('Create Tenant - Creating log file', Zend_Log::INFO);
		$paths = array(
			APPLICATION_PATH . '/logs/' . $this->tenant,
			APPLICATION_PATH . '/shared/' . $this->tenant . '/workspace',
		);
		foreach ($paths as $path) {
			mkdir($path, 0777, true);
		}
		return true;
	}

}
