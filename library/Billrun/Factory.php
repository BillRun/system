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
 * @since    1.0
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
	 * Smser instance
	 * 
	 * @var Billrun Smser
	 */
	protected static $smser = null;
	
	/**
	 * method to retrieve the log instance and can send automatically to msg to log
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
	static public function db() {
		if (!self::$db) {
			self::$db = Billrun_Db::getInstance();
		}

		return self::$db;
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
	 * method to retrieve the dispatcher instance
	 * 
	 * @return Billrun_Dispatcher
	 */
	static public function chain() {
		if (!self::$chain) {
			self::$chain = Billrun_Dispatcher::getInstance(array('type' => 'chain'));
		}

		return self::$chain;
	}
	
	/**
	 * method to retrieve the a mailer instance
	 * 
	 * @return Billrun_Sms
	 */
	static public function smser($options = array()) {
		if (empty($options)) {
			$options = Billrun_Factory::config()->getConfigValue('sms');
		}
		$stamp = Billrun_Util::generateArrayStamp($options);
		if (!isset(self::$smser[$stamp])) {
			self::$smser[$stamp] = new Billrun_Sms($options);
		}
		
		return self::$smser[$stamp];
	}

}