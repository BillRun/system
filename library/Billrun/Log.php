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

	protected static $instance = null;

	public static function getInstance(array $options = array()) {

		if (is_null(self::$instance)) {
			if (empty($options)) {
				$config = Yaf_Application::app()->getConfig();
				$options = $config->log->toArray();
			}
			self::$instance = self::factory($options);
		}

		return self::$instance;
	}
	
}