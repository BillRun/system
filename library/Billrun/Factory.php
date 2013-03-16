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
	 * @var Billrun_Log
	 */
	public static $log = null;
	
	/**
	 * Config instance
	 * @var Yaf config
	 */
	public static $config = null;
	
	/**
	 * DB instance
	 * @var Mongoloid db
	 */
	public static $db = null;
	
	/**
	 * Dispatcher instance
	 * @var Billrun Dispatcher
	 */
	public static $dispatcher = null;
	
	/**
	 * Chain instance
	 * @var Billrun Chain
	 */
	public static $chain = null;
	
	/**
	 * method to retreive the log instance
	 * 
	 * @return Billrun_Log
	 */
	static public function log() {
		if (!self::$log) {
			self::$log = Billrun_Log::getInstance();			
		}
		
		return self::$log;
	}
	
	/**
	 * method to retreive the config instance
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
	 * method to retreive the db instance
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
	 * method to retreive the a mailer instance
	 * 
	 * @return Billrun_Db
	 */
	static public function mailer() {
		$mail = new Zend_Mail();
		//TODO set common configuration.
		$mail->setFrom(	Billrun_Factory::config()->getConfigValue('mailer.from.address', 'no-reply'),
						Billrun_Factory::config()->getConfigValue('mailer.from.name', 'Billrun'));
		
		//$mail->setDefaultTransport($transport);
		return $mail;
	}
	
	/**
	 * method to retreive the dispatcher instance
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
	 * method to retreive the dispatcher instance
	 * 
	 * @return Billrun_Dispatcher
	 */
	static public function chain() {
		if (!self::$chain) {
			self::$chain = Billrun_Dispatcher::getInstance(array('type' => 'chain'));
		}
		
		return self::$chain;
	}

}