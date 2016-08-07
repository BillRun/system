<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	 * save all available values for environment while running in production
	 * 
	 * @var array
	 */
	protected $productionValues = array('prod', 'product', 'production');

	/**
	 * constructor of the class
	 * protected for converting this class to singleton design pattern
	 */
	protected function __construct($config) {
		$this->config = $config;
		$configInclude = $config['configuration']['include'];
		if (!empty($configInclude) && $configInclude->valid()) {
			foreach ($config->toArray()['configuration']['include'] as $filePath) {
				$this->addConfig($filePath);
			}
		}
		if (!isset($config['disableHostConfigLoad']) && file_exists($env_conf = APPLICATION_PATH . '/conf/' . Billrun_Util::getHostName() . '.ini')) {
			$this->addConfig($env_conf);
		}
	}

	public function addConfig($path) {
		if (file_exists($path)) {
			$addedConf = new Yaf_Config_Ini($path);
			$this->config = new Yaf_Config_Simple($this->mergeConfigs($this->config->toArray(), $addedConf->toArray()));
		} else {
			error_log("Configuration File {$path} doesn't exists or BillRun lack access permissions!!");
		}
	}

	/**
	 * Merge to  configuration into one overiding  the  less important config  with  a newer config
	 * @param type $lessImportentConf the configuration array to merge into and override
	 * @param type $moreImportantConf the  configuration array to merge from.
	 * @return type array containing the  overriden values.
	 */
	protected function mergeConfigs($lessImportentConf, $moreImportantConf) {
		if (!is_array($moreImportantConf)) {
			return $moreImportantConf;
		}

		foreach ($moreImportantConf as $key => $value) {
			if (!isset($moreImportantConf[$key])) {
				continue;
			}

			$lessImportentConf[$key] = isset($lessImportentConf[$key]) ?
				$this->mergeConfigs($lessImportentConf[$key], $moreImportantConf[$key]) :
				$moreImportantConf[$key];
		}

		return $lessImportentConf;
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
	 * @param type $config
	 * @return Billrun_Config
	 */
	static public function getInstance($config = null) {
		$stamp = Billrun_Util::generateArrayStamp($config);
		if (empty(self::$instance[$stamp])) {
			if (empty($config)) {
				$config = Yaf_Application::app()->getConfig();
			}
			self::$instance[$stamp] = new self($config);
			self::$instance[$stamp]->loadDbConfig();
		}
		return self::$instance[$stamp];
	}
	
	public function getFileTypeSettings($fileType) {
		$fileType = array_filter($this->getConfigValue('file_types'), function($fileSettings) use ($fileType) {
			return $fileSettings['file_type'] === $fileType;
		});
		if ($fileType) {
			$fileType = current($fileType);
		}
		return $fileType;
	}

	public function getFileTypes() {
		return array_map(function($fileSettings) {
			return $fileSettings['file_type'];
		}, $this->getConfigValue('file_types'));
	}

	public function loadDbConfig() {
		try {
			$configColl = Billrun_Factory::db()->configCollection();
			if ($configColl) {
				$dbCursor = $configColl->query()
					->cursor()->setReadPreference('RP_PRIMARY')
					->sort(array('_id' => -1))
					->limit(1)
					->current();
				if ($dbCursor->isEmpty()) {
					return true;
				}
				$dbConfig = $dbCursor->getRawData();

				unset($dbConfig['_id']);
				$iniConfig = $this->config->toArray();
				$this->config = new Yaf_Config_Simple($this->mergeConfigs($iniConfig, $dbConfig));
			}
		} catch (Exception $e) {
			Billrun_Factory::log('Cannot load database config', Zend_Log::CRIT);
			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Zend_Log::CRIT);
			return false;
		}
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
	 * 
	 * @todo add cache for the config get method
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

		if ($currConf instanceof Yaf_Config_Ini || $currConf instanceof Yaf_Config_Simple) {
			$currConf = $currConf->toArray();
		}

		if (isset($retType) && $retType) {
			settype($currConf, $retType);
		} else if (strtoupper($type = gettype($defVal)) != 'NULL') {
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
		return APPLICATION_ENV;
	}

	/**
	 * method to check if the environment is set under some specific environment
	 * 
	 * @param string $env the environment to check
	 * 
	 * @return boolean true if the environment is the one that supplied, else false
	 */
	public function checkEnv($env) {
		if (is_array($env) && in_array($this->getEnv(), $env)) {
			return true;
		}
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
		if ($this->checkEnv($this->productionValues)) {
			return true;
		}
		if ($this->isCompanyInProd()) {
			return true;
		}
		return false;
	}

	public function toArray() {
		return $this->config->toArray();
	}
	
	protected function isCompanyInProd() {
		return in_array($this->getInstance()->getConfigValue("environment"), $this->productionValues);
	}

}
