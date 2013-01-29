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

	/**
	 * the config instance (for singleton)
	 * 
	 * @var Billrun_Config 
	 */
	static $instance = null;

	/**
	 * the config container
	 * 
	 * @var Yaf_Config
	 */
	protected $config;

	/**
	 * constructor of the class
	 * protected for converting this class to singleton design pattern
	 */
	protected function __construct($config) {
		$this->config = $config;
	}

	/**
	 * magic method for backward compatability (Yaf_Config style)
	 * 
	 * @param string $key the key in the config container (Yaf_Config)
	 * 
	 * @return mixed the value in the config
	 */
	public function __get($key) {
		return $this->getConfigValue($key);
	}
	/**
	 * method to get the instance of the class (singleton)
	 */
	static public function getInstance() {
		if (is_null(self::$instance)) {
			$config = Yaf_Application::app()->getConfig();
			self::$instance = new self($config);
		}
		return self::$instance;
	}

	/**
	 * method to get config value
	 * 
	 * @param mixed $keys array of keys or string divided by period
	 * @param mixed $defVal the value return if the keys not found in the config
	 * @return mixed the config value
	 */
	public function getConfigValue($keys, $defVal = null) {
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