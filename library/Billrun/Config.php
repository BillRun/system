<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
	 * the name of the tenant (or null if not running with tenant)
	 * 
	 * @var string
	 */
	protected $tenant = null;
	
	/**
	 * path for tenants config file
	 * 
	 * @var type 
	 */
	protected static $multitenantDir = null;
	
	/**
	 * save all available values for environment while running in production
	 * 
	 * @var array
	 */
	protected $productionValues = array('prod', 'product', 'production');

	/**
	 * Keeps track of already loaded configuration files
	 * @var array 
	 */
	protected $loadedFiles = [];

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
		
		if (defined('APPLICATION_TENANT')) { // specific defined tenant
			$this->tenant = APPLICATION_TENANT;
			$this->loadTenantConfig();
		} else if (defined('APPLICATION_MULTITENANT') && php_sapi_name() != "cli") { // running from web and with multitenant
			$this->initTenant();
			$this->loadTenantConfig();
		} else {
			$this->tenant = $this->getEnv();
		}
	}
	
	public function addConfig($path) {
		if (!array_key_exists($path, $this->loadedFiles)) {
			if (file_exists($path)) {
				if (preg_match('/\.json$/', $path)) {
					$addedConf = json_decode(file_get_contents($path), TRUE);
				} else {
					$addedConf = (new Yaf_Config_Ini($path))->toArray();
				}
				if (is_array($addedConf)) {
					$this->config = new Yaf_Config_Simple(self::mergeConfigs($this->config->toArray(), $addedConf));
					$this->loadedFiles[$path] = true;
				} else {
					error_log("Couldn't load Configuration File {$path} !!");
				}
			} else {
				error_log("Configuration File {$path} doesn't exists or BillRun lack access permissions!!");
			}
		}
	}

	/**
	 * Merge to  configuration into one overiding  the  less important config  with  a newer config
	 * @param type $lessImportantConf the configuration array to merge into and override
	 * @param type $moreImportantConf the  configuration array to merge from.
	 * @return type array containing the  overriden values.
	 */
	public static function mergeConfigs($lessImportantConf, $moreImportantConf) {
		// If the config value is not an array, or is a complex object then we
		// there is no further level to retrieve.
		// Return the conf value.
		if (!is_array($moreImportantConf)) {
			return $moreImportantConf;
		}
		$NewlessImportantConf = null;
		if (is_array($moreImportantConf) && !is_array($lessImportantConf)) {
			$NewlessImportantConf = [];
		}

		foreach ($moreImportantConf as $key => $value) {
			if (!isset($moreImportantConf[$key])) {
				continue;
			}

			// If the key exists in the less important config array then we have
			// another level of config values to process.
			if(isset($lessImportantConf[$key])) {
				$confValue = self::mergeConfigs($lessImportantConf[$key], $moreImportantConf[$key]);
			} else {
				$confValue = $moreImportantConf[$key];
			}
			if (is_array($NewlessImportantConf)) {
				$NewlessImportantConf[$key] = $confValue;
			} else {
				$lessImportantConf[$key] = $confValue;
			}
		}

		return !empty($NewlessImportantConf) ? $NewlessImportantConf : $lessImportantConf;
	}

	/**
	 * magic method for backward compatibility (Yaf_Config style)
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
	
	public function getFileTypeSettings($fileType, $enabledOnly = false) {
		$fileType = array_filter($this->getConfigValue('file_types'), function($fileSettings) use ($fileType, $enabledOnly) {
			return $fileSettings['file_type'] === $fileType &&
				(!$enabledOnly || Billrun_Config::isFileTypeConfigEnabled($fileSettings));
		});
		if ($fileType) {
			$fileType = current($fileType);
		}
		return $fileType;
	}
	
	public function getExportGeneratorSettings($exportGenerator, $enabledOnly = true) {
		$exportGenerator = array_filter($this->getConfigValue('export_generators'), function($exportGeneratorSettings) use ($exportGenerator, $enabledOnly) {
			return $exportGeneratorSettings['name'] === $exportGenerator &&
				(!$enabledOnly || Billrun_Config::isExportGeneratorConfigEnabled($exportGeneratorSettings));
		});
		if ($exportGenerator) {
			$exportGenerator = current($exportGenerator);
		}
		return $exportGenerator;
	}

	public function getFileTypes($enabledOnly = false) {
		return array_filter(array_map(function($fileSettings) use($enabledOnly) {
			return ((!$enabledOnly || Billrun_Config::isFileTypeConfigEnabled($fileSettings)) ? $fileSettings['file_type'] : null);
		}, $this->getConfigValue('file_types')));
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
				$this->translateComplex($dbConfig);
				$this->config = new Yaf_Config_Simple(self::mergeConfigs($iniConfig, $dbConfig));
				
				// Set the timezone from the config.
				$this->setTenantTimezone($dbConfig);
			}
		} catch (MongoException $e) {
			// TODO: Exception should be thrown and handled by the error controller.
			error_log('cannot load database config. Message: ' . $e->getMessage());
//			Billrun_Factory::log('Cannot load database config', Zend_Log::CRIT);
//			Billrun_Factory::log($e->getCode() . ": " . $e->getMessage(), Zend_Log::CRIT);
			throw $e;
			}

		return true;
		}

	/**
	 * Refresh the values from the config in the DB.
	 */
	public function refresh() {
		$this->setTenantTimezone($this->toArray());
	}
	
	protected function setTenantTimezone($dbConfig) {
		if(!isset($dbConfig['billrun']['timezone'])){
			return;
		}
		
		// Get the timezone.
		$timezone = $dbConfig['billrun']['timezone'];
		if(empty($timezone)) {
			return;
		}
		
		// Setting the default timezone.
		$setTimezone = @date_default_timezone_set($timezone);
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
	 * Return a wrapper for input data.
	 * @param mixed $complex - Data to wrap with complex wrapper.
	 * @return \Billrun_DataTypes_Conf_Base
	 */
	public static function getComplexWrapper (&$complex) {
		// Get complex wrapper.
		$name = "Billrun_DataTypes_Conf_" . ucfirst(strtolower($complex['t']));
		if(!@class_exists($name)) {
			return null;
		}
		
		return new $name($complex);
	}
	
	/**
	 * Translate all complex values in a config array
	 * @param array $config - Config array, changed by reference.
	 */
	public static function translateComplex(&$config) {
		if(self::isComplex($config)) {
			return self::getComplexValue($config);
		}
		if(!Billrun_Util::isMultidimentionalArray($config)) {
			// Check if it is a complex value.
			return $config;
		}
		
		// Go through the config values.
		foreach ($config as $key => $value) {
			$config[$key] = self::translateComplex($value);
		}
		
		return $config;
	}
	
	/**
	 * Check if complex data set is valid by creating a wrapper and validating.
	 * @param mixed $complex - Complex data
	 * @return boolean - True if valid.
	 */
	public static function isComplexValid(&$complex) {
		$wrapper = self::getComplexWrapper($complex);
		if(!$wrapper) {
			return false;
		}
		if (!$wrapper->validate()) {
			return false;
		}
		return true;
	}
	
	/**
	 * Get the complex value from a complex record
	 * @param array $complex - Complex record.
	 * @return type
	 */
	public static function getComplexValue(&$complex) {
		$wrapper = self::getComplexWrapper($complex);
		if(!$wrapper) {
			return null;
		}
		return $wrapper->value();
	}
	
	/**
	 * Check if an object is complex (not primitive or array).
	 * @return true if complex.
	 */
	public static function isComplex($obj) {
		if(empty($obj) || is_scalar($obj) || $obj instanceof MongoDate) {
			return false;
		}
		
		if(!is_array($obj)) {
			return true;
		}
		
		// TODO: that means that 't' is a sensitive value! If a simple array 
		// will have a 't' field, we will treat it as a complex object.
		return isset($obj['t']);
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
	 * method to retrieve the tenant name
	 * 
	 * @return string
	 */
	public function getTenant() {
		if (empty($this->tenant)) {
			return $this->getEnv();
		}
		return $this->tenant;
	}

	/**
	 * method to set the tenant support
	 */
	protected function loadTenantConfig() {
		if (isset($this->config['billrun']['multitenant']['basedir'])) {
			$multitenant_basedir = $this->config['billrun']['multitenant']['basedir'] . DIRECTORY_SEPARATOR;
		} else {
			$multitenant_basedir = APPLICATION_PATH . '/conf/tenants/';
		}
		self::$multitenantDir = $multitenant_basedir;
		if (file_exists($tenant_conf = $multitenant_basedir . $this->tenant . '.ini')) {
			$this->addConfig($tenant_conf);
		}
	}
	
	/**
	 * method to initialize tenanat
	 */
	protected function initTenant() {
		if(!isset($_SERVER['HTTP_HOST'])) {
			return die('no tenant declare');
		}

		$server = $_SERVER['HTTP_HOST'];

		$subDomains = explode(".", $server);

		if (!isset($subDomains[0])) {
			return die('no tenant declare');
		}
		$this->tenant = $subDomains[0];
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
	
	public static function getMultitenantConfigPath() {
		return self::$multitenantDir;
	}
		
	public static function isFileTypeConfigEnabled($fileTypeSettings) {
		return (!isset($fileTypeSettings['enabled']) || $fileTypeSettings['enabled']);
	}
		
	public static function isExportGeneratorConfigEnabled($exportGeneratorSettings) {
		return (!isset($exportGeneratorSettings['enabled']) || $exportGeneratorSettings['enabled']);
	}

	public static function getParserStructure($fileTypeName) {
		$fileType = Billrun_Factory::config()->getFileTypeSettings($fileTypeName);
		if (!empty($fileType)) {
			return $fileType['parser']['structure'];
		}
		return array();
	}
	
	public function getCustomFieldType($customFieldsKey, $fieldName) {
		$customFields = $this->getConfigValue("{$customFieldsKey}.fields", []);
		foreach ($customFields as $customField) {
			if ($customField['field_name'] == $fieldName) {
				return isset($customField['type']) ? $customField['type'] : 'string';
			}
		}
		return 'string';
	}
	
	/**
	 * method to get all input processors settings
	 * 
	 * @param boolean $enabledOnly - indicates if input processor enabled or not
	 * @return array - input processors settings
	 */
	public function getFileTypesSettings($enabledOnly = false) {		
		$fileTypes = array_filter($this->getConfigValue('file_types'), function($fileSettings) use ($enabledOnly) {
			return (!$enabledOnly || Billrun_Config::isFileTypeConfigEnabled($fileSettings));
		});

		return $fileTypes;
	}

	/**
	 * method to get monthly invoice's display config
	 * @return invoice display options if was configured, else returns null.
	 */
	public function getInvoiceDisplayConfig() {		
		return $this->getConfigValue('invoice_export.invoice_display_options', null);
	}

	/**
	 * method to check the cycle's mode
	 * @return boolean true if it's multi day cycle mode, false otherwise.
	 */
	public function isMultiDayCycle() {
		return $this->getConfigValue('billrun.multi_day_cycle', false);
	}
	
	/**
	 * 
	 * @return returns the default charging/invoicing day from the config.
	 */
	public function getConfigChargingDay() {
		return !is_null($this->getConfigValue('billrun.invoicing_day', null)) ? $this->getConfigValue('billrun.invoicing_day', 1) : $this->getConfigValue('billrun.charging_day', 1);
	}

}
