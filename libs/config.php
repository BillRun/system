<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Configuration class
 *
 * @package  configuration
 * @since    1.0
 */
class config {
	
	/**
	 * the environment the application run on
	 * @var string
	 */
	static protected $env = 'dev';
	
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

	protected function __construct(array $options) {
		foreach ($options as $key => $value) {
			$this->{$key} = $value;
		}
	}
	
    protected function __clone()
    {
		// prevent clone
    }

	
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
	
	static protected function getOptions() {
		$file_path = LIBSDIR . '/config/' . self::$env . '/config.ini';
		return parse_ini_file($file_path);
	}
	
	public function __get($name) {
		if (isset($this->properties[$name])) {
			return $this->properties[$name];
		}
		return FALSE;
	}

	public function __set($name, $value) {
		$this->properties[$name] = $value;
	}
}