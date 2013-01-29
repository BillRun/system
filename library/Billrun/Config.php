<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing config class
 *
 * @package  Config
 * @since    1.0
 */
class Billrun_Config {

	static $instance = null;

	/**
	 * method to convert this class to singleton design pattern
	 */
	protected function __construct() {
		
	}
	/**
	 * method to get the instance of the class (singleton)
	 */
	public function getInstance() {
		if (!is_null(self::$instance)) {
			self::$instance = Yaf_Application::app()->getConfig();
		}
		return self::$config[$signature];
	}

	/**
	 * method to get config value
	 * 
	 * @param mixed $keys array of keys or string divided by period
	 * @param mixed $defVal the value return if the keys not found in the config
	 * @return mixed the config value
	 */
	public function getConfigValue($keys, $defVal) {
		$currConf = $this->config;

		if (!is_array($keys)) {
			$path = explode(".", $keys);
		}

		foreach ($path as $key) {
			if (!isset($currConf[$key])) {
				return $defVal;
			}
			$currConf = $currConf[$key];
		}

		return $currConf;
	}

}