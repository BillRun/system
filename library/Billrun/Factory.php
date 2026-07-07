<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing factory class
 *
 * @package  Factory
 * @since    0.5
 */
class Billrun_Factory {

	/**
	 * Log instance
	 * 
	 * @var Billrun_Log
	 */
	protected static $log = null;

	/**
	 * Config instance
	 * 
	 * @var Yaf config
	 */
	protected static $config = null;

	/**
	 * Database instance
	 * 
	 * @var Mongoloid db
	 */
	protected static $db = array();

	/**
	 * Cache instance
	 * 
	 * @var Billrun_Billrun Cache
	 */
	protected static $cache = null;

	/**
	 * Chain instance
	 * 
	 * @var Billrun_Billrun Chain
	 */
	protected static $chain = null;

	/**
	 * Chain instance
	 * 
	 * @var Billrun_Billrun Chain
	 */
	protected static $importer = null;

	/**
	 * Subscriber instance
	 * 
	 * @var Billrun_Billrun Subscriber
	 */
	protected static $subscriber = null;
	
	/**
	 * Account instances
	 * 
	 * @var Billrun_Account[] Account
	 */
	protected static $accounts = null;
	
	/**
	 * Collection Steps instance
	 * 
	 * @var Billrun_Billrun Collection Steps
	 */
	protected static $collectionSteps = null;
	
	/**
	 * Collection Steps instance
	 * 
	 * @var Billrun_Billrun Collection Steps
	 */
	protected static $templateTokens = null;
	
	/**
	 * Balance instance
	 * 
	 * @var Billrun_Billrun Balance
	 */
	protected static $balance = null;

	/**
	 * Tariff instance
	 * 
	 * @var Billrun_Billrun Tariff
	 */
	protected static $tariff = null;

	/**
	 * Plan instance
	 * 
	 * @var Billrun_Billrun Plan
	 */
	protected static $plan = array();

	/**
	 * Service instances
	 *
	 * @var Billrun_Billrun Service
	 */
	protected static $service = array();
	/**
	 * Smser instance
	 * 
	 * @var Billrun_Billrun Smser
	 */
	protected static $smser = null;

	/**
	 * Users container
	 * 
	 * @var Mongodloid_Entity
	 */
	protected static $users = array();

	/**
	 * Authentication main dispatcher
	 * 
	 * @var Zend_Auth
	 */
	protected static $auth = null;
	
	/**
	 * Oauth container for oauth2
	 * 
	 * @var Oauth2\Server
	 */
	protected static $oauth2 = array();
	
	/**
	 * Collection instance
	 * 
	 * @var Billrun_Billrun Collection
	 */
	protected static $collection;

	/**
	 * Collection instance
	 * 
	 * @var Billrun_Billrun Collection
	 */
	protected static $queues;

	/**
	 * method to retrieve the log instance
	 * 
	 * @param string [Optional] $message message to log
	 * @param int [Optional] $priority message to log
	 * 
	 * @return Billrun_Log
	 */
	static public function log() {
		if (!self::$log) {
			self::$log = Billrun_Log::getInstance();
		}

		$args = func_get_args();
		if (count($args) > 0) {
			$message = (string) $args[0];
			if (!isset($args[1])) {
				$priority = Zend_Log::DEBUG;
			} else {
				$priority = (int) $args[1];
			}
			self::$log->log($message, $priority);
		}

		return self::$log;
	}

	/**
	 * method to retrieve the config instance
	 * 
	 * @return Billrun_Config
	 */
	static public function config() {
		if (!self::$config) {
			self::$config = Billrun_Config::getInstance();
		}

		return self::$config;
	}

		/**
	 * method to update the config instance
	 * 
	 * @return Billrun_Config
	 */
	static public function updateConfig() {
		self::$config->loadDbConfig();
	}

	/**
	 * method to retrieve the db instance
	 * 
	 * @return Billrun_Db
	 */
	static public function db(array $options = array(), $refresh = false) {
		$stamp = md5(serialize($options)); // unique stamp per db connection
		if ($refresh) {
			self::$db[$stamp] = null;
		}
		if (!isset(self::$db[$stamp])) {
			if (empty($options)) { // get the db settings from config
				$options = Billrun_Factory::config()->getConfigValue('db');
			}
			if ($refresh) {
				$options['options']['refresh'] = true;
			}
			self::$db[$stamp] = Billrun_Db::getInstance($options);
		}

		return self::$db[$stamp];
	}

	/**
	 * method to retrieve the admin db instance
	 * admin db should have higher permissions, like sharding and
	 * create new db scheme, db user and db pass for multi-tenancy support
	 *
	 * Sources, in order of precedence:
	 *   1. BR_ADMDB_* env vars (always override when set)
	 *   2. 'admindb' config block
	 *   3. Fall back to the default db() connection
	 *
	 * @return Billrun_Db
	 */
	static public function admindb() {
		$options = Billrun_Factory::config()->getConfigValue('admindb');
		if (!is_array($options)) {
			$options = [];
		}
		self::setAdmindbEnvConfig($options);
		if (empty($options)) {
			return self::db();
		}
		return self::db($options);
	}

	/**
	 * Apply BR_ADMDB_* environment variables to admindb options in-place.
	 *
	 * Mirrors Billrun_Config::setDbEnvConfig() for the admin connection. Env
	 * values take precedence over anything supplied via the 'admindb' config
	 * block, so a docker/k8s deployment can override individual fields (or
	 * supply the whole connection) without editing config files.
	 *
	 * @param array $options
	 */
	static protected function setAdmindbEnvConfig(array &$options) {
		$envMap = array(
			'BR_ADMDB_HOST'            => 'host',
			'BR_ADMDB_PORT'            => 'port',
			'BR_ADMDB_DBNAME'          => 'name',
			'BR_ADMDB_USER'            => 'user',
			'BR_ADMDB_PASS'            => 'password',
			'BR_ADMDB_AUTHDB'          => 'options.authSource',
			'BR_ADMDB_TLS'             => 'options.tls',
			'BR_ADMDB_TLSKEYFILE'      => 'options.tlsCertificateKeyFile',
			'BR_ADMDB_TLSPASSWORD'     => 'options.tlsCertificateKeyFilePassword',
			'BR_ADMDB_TLSCAFILE'       => 'options.tlsCAFile',
			'BR_ADMDB_TLSINSECURE'     => 'options.tlsInsecure',
			'BR_ADMDB_TLSINVALIDCERT'  => 'options.tlsAllowInvalidCertificates',
			'BR_ADMDB_TLSINVALIDHOST'  => 'options.tlsAllowInvalidHostnames',
		);
		foreach ($envMap as $envVar => $confVar) {
			if (!empty($envVal = getenv($envVar))) {
				Billrun_Util::setIn($options, $confVar, $envVal);
			}
		}
	}

	/**
	 * method to retrieve the cache instance
	 * 
	 * @return Billrun_Cache
	 */
	static public function cache() {
		try {
			if (!self::$cache) {
				$args = self::config()->getConfigValue('cache', array());
				if (isset($args[2]['is_relative_path']) && $args[2]['is_relative_path']) {
					$args[2]['cache_dir'] = APPLICATION_PATH . '/' . $args[2]['cache_dir'];
				}
				if (isset($args[2]['cache_id_prefix'])) {
					$args[2]['cache_id_prefix'] .= '_' . Billrun_Factory::config()->getTenant() . '_';
				}
				if (empty($args)) {
					return false;
				}
				self::$cache = Billrun_Cache::getInstance($args);
			}

			return self::$cache;
		} catch (Exception $e) {
			Billrun_Factory::log('Cache instance cannot be generated.', Zend_Log::ALERT);
			Billrun_Factory::log()->logCrash($e, Zend_Log::DEBUG);
		}
		return false;
	}

	/**
	 * method to retrieve the a mailer instance
	 * 
	 * @return Zend_Mail
	 */
	static public function mailer() {
		try {
			$mailer = new Zend_Mail();
			$mailerTransport = Billrun_Factory::config()->getConfigValue('mailer.transport');
			if (!empty($mailerTransport)) {
				$className = 'Zend_Mail_Transport_' . ucfirst($mailerTransport['type']);
				$transport = new $className($mailerTransport['host'], $mailerTransport);
				Zend_Mail::setDefaultTransport($transport);
			}
			$fromAddress = Billrun_Factory::config()->getConfigValue('tenant.email', '');
			if (empty($fromAddress)) {
				$fromAddress = Billrun_Factory::config()->getConfigValue('mailer.from.address', 'no-reply@bill.run');
			}
			$fromName = Billrun_Factory::config()->getConfigValue('tenant.name', Billrun_Factory::config()->getConfigValue('mailer.from.name', 'BillRun'));
			$mailer->setFrom($fromAddress, $fromName);
			return $mailer;
			//$mail->setDefaultTransport($transport);
		} catch (Exception $e) {
			self::log("Can't instantiate mail object. Please check your settings", Zend_Log::ALERT);
			return false;
		}
	}

	/**
	 * method to retrieve the a smser instance
	 * 
	 * @return Billrun_Sms
	 * 
	 * @todo Refactoring Billrun_Sms object
	 */
	static public function smser($options = array()) {
		if (empty($options)) {
			$options = Billrun_Factory::config()->getConfigValue('smser', array());
		}
		$stamp = Billrun_Util::generateArrayStamp($options);
		if (!isset(self::$smser[$stamp])) {
			self::$smser[$stamp] = Billrun_Sms_Abstract::getInstance($options);
		}

		return self::$smser[$stamp];
	}

	/**
	 * method to retrieve the dispatcher instance. Billrun_Dispatcher decides whether to create a new instance or not.
	 * 
	 * @return Billrun_Dispatcher
	 */
	static public function dispatcher() {
		return Billrun_Dispatcher::getInstance();
	}

	/**
	 * method to retrieve the chain instance
	 * 
	 * @return Billrun_Chain
	 */
	static public function chain() {
		if (!self::$chain) {
			self::$chain = Billrun_Dispatcher::getInstance(array('type' => 'chain'));
		}

		return self::$chain;
	}

	/**
	 * method to retrieve the subscriber instance
	 * 
	 * @return Billrun_Subscriber
	 */
	public static function subscriber() {
		if (!self::$subscriber) {
			$subscriberSettings = self::config()->getConfigValue('subscribers.subscriber', array());
			if (!isset($subscriberSettings['type'])) {
				$subscriberSettings['type'] = 'db';
			}
			self::$subscriber = Billrun_Subscriber::getInstance($subscriberSettings);
		}

		return self::$subscriber;
	}
	
	/**
	 * method to retrieve the account instance
	 * 
	 * @return Billrun_Account
	 */
	public static function account() {
		$settings = self::config()->getConfigValue('subscribers.account', array());
		if (!isset($settings['type'])) {
			$settings['type'] = 'db';
		}
		if (!isset(self::$accounts[$settings['type']])) {
			self::$accounts[$settings['type']] = Billrun_Account::getInstance($settings);
		}
		return self::$accounts[$settings['type']];
	}


	
	/**
	 * method to retrieve the account instance
	 * 
	 * @return Billrun_CollectionSteps
	 */
	static public function collectionSteps() {
		if (!self::$collectionSteps) {
			$settings = self::config()->getConfigValue('collection_steps', array());
			self::$collectionSteps = Billrun_CollectionSteps::getInstance($settings);
		}

		return self::$collectionSteps;
	}
	
	/**
	 * method to retrieve the Template Tokens instance
	 * 
	 * @return Billrun_Subscriber
	 */
	static public function templateTokens() {
		if (!self::$templateTokens) {
			self::$templateTokens = Billrun_Template_Token_Base::getInstance();
		}

		return self::$templateTokens;
	}
	
	/**
	 * method to retrieve a balance instance
	 * 
	 * @return Billrun_Balance
	 * @deprecated since version 4.0
	 */
	static public function balance($params = array()) {
		$balanceSettings = self::config()->getConfigValue('balance', array());
		return Billrun_Balance::getInstance(array_merge($balanceSettings, $params));
	}

	/**
	 * method to retrieve the tariff instance
	 * 
	 * @return Billrun_Subscriber
	 */
	static public function tariff() {
		if (!self::$tariff) {
			$tariffSettings = self::config()->getConfigValue('tariff', array());
			self::$tariff = Billrun_Tariff::getInstance($tariffSettings);
		}

		return self::$tariff;
	}

	/**
	 * method to retrieve the plan instance
	 * 
	 * @return Billrun_Plan
	 */
	static public function plan($params) {

		if (isset($params['disableCache']) && $params['disableCache']) {
			return new Billrun_Plan($params);
		}
		// unique stamp per plan
		$stamp = Billrun_Util::generateArrayStamp($params,['name','time','id','data']);

		if (!isset(self::$plan[$stamp])) {
			self::$plan[$stamp] = new Billrun_Plan($params);
		}
		self::$plan[$stamp]->init();
		return self::$plan[$stamp];
	}

	/**
	 * method to retrieve the service instance
	 *
	 * @return Billrun_Plan
	 */
	static public function service($params) {

		if (isset($params['disableCache']) && $params['disableCache']) {
			return new Billrun_Service($params);
		}
		// unique stamp per plan
		$stamp = Billrun_Util::generateArrayStamp($params);

		if (!isset(self::$service[$stamp])) {
			self::$service[$stamp] = new Billrun_Service($params);
		}
		return self::$service[$stamp];
	}

	/**
	 * method to retrieve a billrun instance
	 * 
	 * @return Billrun_Billrun
	 */
	static public function billrun($params = array()) {
		$billrunSettings = self::config()->getConfigValue('billrun', array());
		return new Billrun_Billrun(array_merge($billrunSettings, $params));
	}

	/**
	 * Receive a billrun user
	 * @param string $username
	 * @return Billrun_User
	 */
	public static function user($username = null) {
		if (is_null($username)) {
			$username = Billrun_Factory::auth()->getIdentity();
		}

		if (empty($username)) {
			return FALSE;
		}

		$stamp = Billrun_Util::generateArrayStamp($username);
		if (!isset(self::$users[$stamp])) {
			$read = Billrun_Factory::auth()->getStorage()->read();
			if(!isset($read['current_user'])) {
				return FALSE;
			}
			$entity = new Mongodloid_Entity($read['current_user']);
			self::$users[$stamp] = new Billrun_User($entity);
		}
		return self::$users[$stamp];
	}

	protected static function setSessionTimeout($defaultTimeout) {
		$session_timeout = Billrun_Factory::config()->getConfigValue('admin.session.timeout', $defaultTimeout);
		ini_set('session.gc_maxlifetime', $session_timeout);
		session_set_cookie_params($session_timeout);
	}

	public static function auth() {
		if (!isset(self::$auth)) {
			if (php_sapi_name() === 'cli') {
				/*
					Why generateService hangs (AggregatorTest:testDiscountOnAnAccountLevelService)
					Unlike generatePlan/generateSubscriber (which go through the HTTP API, so they run in the app's fpm process), generateService with the default $byApi = false creates the entity in-process inside the codeception CLI run (BillRunAPI.php:513-524):

					Models_Entity::create() → trackChanges() (Entity.php:457)
					→ Billrun_AuditTrail_Util::getUser() (Util.php:82)
					→ Billrun_Factory::user() → Billrun_Factory::auth() (Factory.php:554)
					→ new Zend_Auth_Storage_Yaf → Yaf_session::getInstance() (Yaf.php:8)
					Step 4 is the exact yaf 3.3.7 / PHP 8.5 breakage we proved with gdb in the BRCD-3318 investigation (corrupted HashTable → zend_hash_find spins at 100% CPU, or segfault at shutdown). It also explains why the earlier hangs "clustered around AggregatorTest" — that suite is full of generateService calls.
					Why only sometimes: Billrun_Factory::auth() caches self::$auth, so only the first in-process entity-create of the run initializes Yaf_Session — and the yaf bug itself is intermittent (sometimes it corrupts memory, sometimes it survives).
					now uses Zend_Auth_Storage_NonPersistent when running under CLI, and keeps the original Zend_Auth_Storage_Yaf behavior for web requests. 
				*/
				// no HTTP session in CLI (cron/tests); Yaf_Session hangs/segfaults on PHP 8.5 (BRCD-3318)
				self::$auth = Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
			} else {
				Billrun_Util::setHttpSessionTimeout(null, 'Lax');
				self::$auth = Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_Yaf(Billrun_Factory::config()->getTenant()));
			}
		}
		return self::$auth;
	}

	/**
	 * factory for importer class
	 * 
	 * @param array $options options of the importer
	 * 
	 * @return mixed instance of importer if success, else false
	 */
	static public function importer(array $options = array()) {
		if (!isset($options)) {
			Billrun_Factory::log('Importer trying to initizilized without type', Zend_Log::ERR);
			return false;
		}
		$stamp = md5(serialize($options)); // unique stamp per db connection
		if (!isset(self::$importer[$stamp])) {
			$class_name = 'Billrun_Importer_' . $options['type'];
			self::$importer[$stamp] = new $class_name($options);
		}

		return self::$importer[$stamp];
	}

	/**
	 * method to retrieve a payment gateway by name
	 * 
	 * @return Billrun_PaymentGateway
	 */
	public static function paymentGateway($name) {
		try {
			$gateway = Billrun_PaymentGateway::getInstance($name);
		} catch (Exception $e) {
			Billrun_Factory::log($e->getMessage(), Zend_Log::ALERT);
			return FALSE;
		}
		return $gateway;
	}
	
	public static function remoteClient($param) {
		return new SoapClient($param);
	}
	
	/**
	 * 
	 * @param array $params
	 * @return Billrun_EventsManager
	 */
	public static function eventsManager($params = array()) {
		return Billrun_EventsManager::getInstance($params);
	}
	
	/**
	 * 
	 * @param array $params
	 * @return Billrun_FraudManager
	 */
	public static function fraudManager($params = array()) {
		return Billrun_FraudManager::getInstance($params);
	}
	
	/**
	 * 
	 * @param array $params
	 * @return Billrun_EmailSenderManager
	 */
	public static function emailSenderManager($params = array()) {
		return Billrun_EmailSenderManager::getInstance($params);
	}
	
	public static function clearInstance($instanceName, array $options = array(),$clearAll = FALSE) {
		$stamp = md5(serialize($options)); // unique stamp per db connection
		
		if($clearAll) {
			self::${$instanceName} = is_array(self::${$instanceName})  ? array() : null;
		}
		if (!isset(self::${$instanceName}[$stamp])) {
			return;
		}
		unset(self::${$instanceName}[$stamp]);
	}
	
	/**
	 * method to retrieve the account instance
	 * 
	 * @return Billrun_Subscriber
	 */
	static public function collection() {
		if (!self::$collection) {
			self::$collection = Billrun_Collection::getInstance();
		}

		return self::$collection;
	}
	
	/**
	 * method to retrieve a payment gateway by name
	 * 
	 * @return Billrun_PaymentGateway
	 */
	public static function paymentGatewayConnection($connectionDetails) {
		return Billrun_PaymentGateway_Connection::getInstance($connectionDetails);
	}
	
	/**
	 * method to receive the oauth2 authenticator instance
	 * 
	 * @param array $params oauth2 server params; see OAuth2\Server constructor
	 * 
	 * @return OAuth2\Server
	 */
	public static function oauth2($params = array()) {
		$stamp = Billrun_Util::generateArrayStamp($params);
		if (!isset(self::$oauth2[$stamp])) {
			$configParams = Billrun_Factory::config()->getConfigValue('oauth2', array()); // see OAuth2\Server constructor for available options
			forEach ($configParams as $key => $value) {
				if (!isset($params[$key])) {
					$params[$key] = is_numeric($value) ? (int) $value : $value;
				}
			}
			OAuth2\Autoloader::register();
			$storage = new Billrun_OAuth2_Storage_MongoDB(Billrun_Factory::db()->getDb());
			self::$oauth2[$stamp] = new OAuth2\Server($storage, $params);
			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
			// Future compatibility
//			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
//			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\JwtBearer($storage));
//			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\RefreshToken($storage));
//			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
		}
		return self::$oauth2[$stamp];
	}
	
	/**
	 * method to receive jobs queue
	 * 
	 * @param string $name name of the queue; default name is jobs
	 * 
	 * @return Zend_Queue
	 */
	public static function queue($name = null, $timeout = null) {
		if (empty($name)) {
			$name = 'jobs';
		}
		if (!isset(self::$queues[$name])) {
			$options = array(
				'db' => Billrun_Factory::db()->getDb(),
				'queueCollection' => Billrun_Factory::db()->getCollection($name . '_queues')->getMongoCollection(),
				'messageCollection' => Billrun_Factory::db()->getCollection($name . '_messages')->getMongoCollection(),
				'name' => $name,
				'timeout' => $timeout,
			);
			self::$queues[$name] = new Zend_Queue('mongodb', $options);
		} elseif (!empty($timeout)) {
			self::$queues[$name]->setOption('timeout', $timeout);
		}
		return self::$queues[$name];
	}
	
	public static function cleanQueue($name = null) {
		if (empty($name)) {
			$name = 'jobs';
		}
		self::$queues[$name] = null;
	}


}
