<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing log class
 * Based on Zend Log class
 *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Log extends Zend_Log {

	protected static $instances = array();

	public static function getInstance(array $options = array()) {

		$stamp = md5(serialize($options));
		if (!isset(self::$instances[$stamp])) {
			if (empty($options)) {
				$config = Yaf_Application::app()->getConfig();
				$options = $config->log->toArray();
			}
			self::$instances[$stamp] = Billrun_Log::factory($options);
		}

		return self::$instances[$stamp];
	}

	/**
	 * Log a message at a priority
	 *
	 * @param  string   $message   Message to log
	 * @param  integer  $priority  Priority of message
	 * @param  mixed    $extras    Extra information to log in event
	 * @return void
	 * @throws Zend_Log_Exception
	 */
	public function log($message, $priority = Zend_Log::DEBUG, $extras = null) {
		parent::log($message, $priority, $extras);
	}

}