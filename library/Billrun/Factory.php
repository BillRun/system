<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
	protected static $db = null;

	/**
	 * Cache instance
	 * 
	 * @var Billrun Cache
	 */
	protected static $cache = null;

	/**
	 * Dispatcher instance
	 * 
	 * @var Billrun Dispatcher
	 */
	protected static $dispatcher = null;

	/**
	 * Chain instance
	 * 
	 * @var Billrun Chain
	 */
	protected static $chain = null;

	/**
	 * Subscriber instance
	 * 
	 * @var Billrun Subscriber
	 */
	protected static $subscriber = null;

	/**
	 * Tariff instance
	 * 
	 * @var Billrun Tariff
	 */
	protected static $tariff = null;

	/**
	 * Tariff instance
	 * 
	 * @var Billrun Tariff
	 */
	protected static $plan = null;

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
		if (empty($options)) {
			$options = Billrun_Factory::config()->getConfigValue('db'); // the stdclass force it to return object
		}
		
		// unique stamp per db connection
		$stamp = md5(serialize($options));
		
		if (!isset(self::$db[$stamp])) {
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
		if (!self::$cache) {
			$args = self::config()->getConfigValue('cache', array());
			if (empty($args)) {
				return false;
			}
			self::$cache = Billrun_Cache::getInstance($args);
		}

		return self::$cache;
	}

	/**
	 * method to retrieve the a mailer instance
	 * 
	 * @return Billrun_Db
	 */
	static public function mailer() {
		try {
			$mail = new Zend_Mail();
			//TODO set common configuration.
			$fromName = Billrun_Factory::config()->getConfigValue('mailer.from.address', 'no-reply');
			$fromAddress = Billrun_Factory::config()->getConfigValue('mailer.from.name', 'Billrun');
			$mail->setFrom($fromName, $fromAddress);
			//$mail->setDefaultTransport($transport);
			return $mail;
		} catch (Exception $e) {
			self::log("Can't instantiat mail object. Please check your settings", Zend_Log::ALERT);
			return false;
		}
	}

	/**
	 * method to retrieve the dispatcher instance
	 * 
	 * @return Billrun_Dispatcher
	 */
	static public function dispatcher() {
		if (!self::$dispatcher) {
			self::$dispatcher = Billrun_Dispatcher::getInstance();
		}

		return self::$dispatcher;
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
	static public function subscriber() {
		if (!self::$subscriber) {
			$subscriberSettings = self::config()->getConfigValue('subscriber', array());
			self::$subscriber = Billrun_Subscriber::getInstance($subscriberSettings);
		}

		return self::$subscriber;
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

		// unique stamp per plan
		$stamp = md5(serialize($params));
		
		if (!isset(self::$plan[$stamp])) {
			self::$plan[$stamp] = new Billrun_Plan($params);
		}

		return self::$plan[$stamp];
	}

}