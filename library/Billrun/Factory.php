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
	
	static public function log() {
		if (!self::$log) {
			self::$log = Billrun_Log::getInstance();			
		}
		
		return self::$log;
	}
	
	static public function config() {
		if (!self::$config) {
			self::$config = Yaf_Application::app()->getConfig();
		}
		
		return self::$config;
	}
	
	static public function db() {
		if (!self::$db) {
//			$conn = Mongodloid_Connection::getInstance(self::config()->db->host, self::config()->db->port);
//			self::$db = $conn->getDB(self::config()->db->name);
			self::$db = Billrun_Db::getInstance();
		}
		
		return self::$db;
	}
	
	static public function dispatcher() {
		if (!self::$db) {
			self::$dispatcher = Billrun_Dispatcher::getInstance();
		}
		
		return self::$dispatcher;
	}
		
}