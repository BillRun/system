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
 * @since    0.5
 */
class Billrun_Config {

	/**
	 * the config instance (for singleton)
	 * 
	 * @var Billrun_Config 
	 */
	protected static $instance = null;

	/**
	 * the config container
	 * 
	 * @var Yaf_Config
	 */
	protected $config;

	/**
	 * the environment field in config ini file
	 */

	const environment_field = 'environment';

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
		return $this->config->{$key};
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
	 * @param mixed  $keys array of keys or string divided by period
	 * @param mixed  $defVal the value return if the keys not found in the config
	 * @param string $retType the type of the return value (int, bool, string, float, array, object)
	 *               if null passed the return value type will be declare by the default value type
	 *               this argument is deprecated; the return value type is defined by the default value type
	 * @return mixed the config value
	 */
	public function getConfigValue($keys, $defVal = null, $retType = null) {
		$currConf = $this->config;

		if (!is_array($keys)) {
			$path = explode(".", $keys);
		}

		foreach ($path as $key) {
			if (!isset($currConf[$key])) {
				$currConf = $defVal;
				break;
			}
			$currConf = $currConf[$key];
		}
		
		if ($currConf instanceof Yaf_Config_Ini) {
			$currConf = $currConf->toArray();
		}
		
		if (isset($retType) && $retType ) {
			settype($currConf, $retType);
		} else if (strtoupper($type = gettype($defVal)) != 'NULL' ) {
			settype($currConf, $type);
		}

		return $currConf;
	}

	/**
	 * method to receive the environment the app running
	 * 
	 * @return string the environment (prod, test or dev)
	 */
	public function getEnv() {
		return $this->getConfigValue(self::environment_field, 'dev');
	}

	/**
	 * method to check if the environment is set under some specific environment
	 * 
	 * @param string $env the environment to check
	 * 
	 * @return boolean true if the environment is the one that supplied, else false
	 */
	public function checkEnv($env) {
		if ($this->getEnv() === $env) {
			return true;
		}
		return false;
	}

	/**
	 * method to check if the environment is production
	 * 
	 * @return boolean true if it's production, else false
	 */
	public function isProd() {
		if ($this->checkEnv('prod')) {
			return true;
		}
		return false;
	}

}