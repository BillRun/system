<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Configuration class
 *
 * @package  configuration
 * @since    1.0
 */
class Billrun_Config {

	/**
	 * the environment the application run on
	 * @var string
	 */
	protected $env = 'dev';

	/**
	 * instance for singleton config
	 * @var config class
	 */
	static protected $instance = null;

	/**
	 * config properties container
	 * @var array
	 */
	protected $properties = array();

	/**
	 * constructor
	 * @param array $options options for the config
	 */
	protected function __construct(array $options) {
		foreach ($options[$this->env] as $key => $value) {
			$this->{$key} = $value;
		}
	}

	/**
	 * prevent clone the object
	 */
	protected function __clone() {}

	static public function getInstance() {
		self::load();
		return self::$instance;
	}

	/**
	 * method to verifiy the instance is loaded
	 */
	protected static function load() {
		if (is_null(self::$instance)) {
			$options = self::getOptions();
			self::$instance = new config($options);				
		}
	}

	/**
	 * method to get the configuration options
	 * this method should be override once implement database configuration
	 * 
	 * @return array
	 */
	static protected function getOptions() {
		$file_path = LIBSDIR . '/config/config.ini';
		return parse_ini_file($file_path, TRUE);
	}

	/**
	 * get property of the config
	 * @param string $name the property name
	 * @return mixed if config contain property return the property value, else false
	 */
	public function __get($name) {
		if (isset($this->properties[$name])) {
			return $this->properties[$name];
		}
		return FALSE;
	}

	/**
	 * set property of the config
	 * @param string $name the property name
	 * @param mixed $value the property new value
	 */
	public function __set($name, $value) {
		$this->properties[$name] = $value;
	}

}