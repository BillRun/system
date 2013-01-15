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
 * @since    1.0
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
			self::$instances[$stamp] = self::factory($options);
		}

		return self::$instances[$stamp];
	}
	
}