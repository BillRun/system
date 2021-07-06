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
	 * Account instance
	 * 
	 * @var Billrun_Billrun Account
	 */
	protected static $account = null;
	
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
	 * method to retrieve the db instance
	 * 
	 * @return Billrun_Db
	 */
	static public function db(array $options = array()) {
		$stamp = md5(serialize($options)); // unique stamp per db connection
		if (!isset(self::$db[$stamp])) {
			if (empty($options)) { // get the db settings from config
				$options = Billrun_Factory::config()->getConfigValue('db');
			}
			self::$db[$stamp] = Billrun_Db::getInstance($options);
		}

		return self::$db[$stamp];
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
			Billrun_Factory::log('Cache instance cannot be generated', Zend_Log::ALERT);
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
			self::$smser[$stamp] = new Billrun_Sms($options);
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
		if (!self::$account) {
			$settings = self::config()->getConfigValue('subscribers.account', array());
			if (!isset($settings['type'])) {
				$settings['type'] = 'db';
			}
			self::$account = Billrun_Account::getInstance($settings);
		}

		return self::$account;
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
		$stamp = Billrun_Util::generateArrayStamp($params);

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
			Billrun_Util::setHttpSessionTimeout();
			self::$auth = Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_Yaf(Billrun_Factory::config()->getTenant()));
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
			$storage = new OAuth2\Storage\MongoDB(Billrun_Factory::db()->getDb());
			self::$oauth2[$stamp] = new OAuth2\Server($storage, $params);
			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
			// Future compatibility
//			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
//			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\JwtBearer($storage));
//			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\RefreshToken($storage));
//			self::$oauth2[$stamp]->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
		}
		return self::$oauth2[$stamp];
	}


}
