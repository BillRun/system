<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract exporter class
 *
 * @package  Billing
 * @since    2.8
 */
abstract class Billrun_Exporter extends Billrun_Base {

	/**
	 * Type of exporter
	 *
	 * @var string
	 */
	static protected $type = 'exporter';
	
	/**
	 * configuration for internal use of the exporter
	 * 
	 * @var array
	 */
	protected $config = array();
	
	/**
	 * additional options
	 * @var array 
	 */
	protected $options = array();
	
	protected $logCollection = null;

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->options = $options;
		$this->logCollection = Billrun_Factory::db()->logCollection();
		$this->loadConfig();
	}

	/**
	 * general function to handle the export
	 *
	 * @return array list of lines exported
	 */
	abstract function handleExport();
	
	/**
	 * optional actions to perform before export begins
	 */
	function beforeExport() {
	}
	
	/**
	 * optional actions to perform after export ends
	 */
	function afterExport() {
	}
	
	/**
	 * general function to handle the export
	 *
	 * @return array list of lines exported
	 */
	function export() {
		$this->beforeExport();
		$exportedData = $this->handleExport();
		$this->afterExport();
		return $exportedData;
	}
	
	/**
	 * loads configuration files for exporter internal use
	 */
	protected function loadConfig() {
		$configPath = Billrun_Factory::config()->getConfigValue(static::$type . '.exporter.config_path', '');
		$this->config = (new Yaf_Config_Ini($configPath))->toArray();
	}
	
	/**
	 * get value from exporter configuration
	 * 
	 * @param mixed $keys - array of keys or dot (".") separated keys
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	protected function getConfig($keys, $defaultValue = null) {
		if (!$this->config) {
			return $defaultValue;
		}
		
		if (!is_array($keys)) {
			$keys = explode('.', $keys);
		}
		
		$ret = $this->config;
		foreach ($keys as $key) {
			if (!isset($ret[$key])) {
				return $defaultValue;
			}
			$ret = $ret[$key];
		}
		
		return $ret;
	}
	
	/**
	 * get fields mapping for 1 line of the exporter
	 * 
	 * @param array $row
	 * @return type
	 */
	protected function getFieldsMapping($row) {
		return $this->getConfig('fields_mapping', array());
	}
	
	/**
	 * translate row to the format it should be exported
	 * 
	 * @param array $row
	 * @return array
	 */
	protected function getRecordData($row) {
		$fieldsMapping = $this->getFieldsMapping($row);
		return $this->mapFields($fieldsMapping, $row);
	}
	
	protected function mapFields($fieldsMapping, $row = array()) {
		$data = array();
		$allowNull = $this->getConfig('allow_null', false);
		$nullVal = $this->getConfig('null_value', '');
		
		
		foreach ($fieldsMapping as $field => $fieldMapping) {
			if (!is_array($fieldMapping)) {
				$val = Billrun_Util::getIn($row, $fieldMapping, null);
			} else if (isset($fieldMapping['func'])) {
				$functionName = $fieldMapping['func'];
				if (!method_exists($this, $functionName)) {
					Billrun_Log::getInstance()->log('Bulk exporter: mapping function "' . $functionName . '" does not exist', Zend_log::WARN);
					break;
				}
				$val = $this->{$functionName}($row, $fieldMapping);
			} else if(isset ($fieldMapping['value'])) {
				$val = $fieldMapping['value'];
			} else if (isset($fieldMapping['fields'])) {
				foreach ($fieldMapping['fields'] as $fieldOption) {
					$val = Billrun_Util::getIn($row, $fieldOption, null);
					if (empty($val)) {
						$val = null;
					} else {
						break;
					}
				}
			} else {
				Billrun_Log::getInstance()->log('Bulk exporter: invalid mapping: ' . print_R($fieldMapping, 1), Zend_log::NOTICE);
				$val = '';
			}
			
			if (is_null($val) && $allowNull) {
				$val = $nullVal;
			}
			
			if (!is_null($val) || $allowNull) {
				Billrun_Util::setIn($data, explode('>', $field), $val);
			}
		}
		
		return $data;
	}
	
	/**
	 * method to log the export process
	 */
	protected function logDB($stamp, $data) {
		if (empty($stamp)) {
			Billrun_Factory::log()->log("Billrun_Exporter::logDB - got export with empty stamp. data: " . print_R($data, 1), Zend_Log::NOTICE);
			return false;
		}
		$log = Billrun_Factory::db()->logCollection();
		Billrun_Factory::dispatcher()->trigger('beforeLogExport', array(&$data, $stamp, $this));
		
		$query = array(
			'stamp' =>  $stamp,
			'source' => 'export',
			'type' => static::$type,
		);

		$update = array(
			'$set' => $data,
		);

		$result = $this->logCollection->update($query, $update, array('w' => 1));
		$success = $result == true || ($result['n'] == 1 && $result['ok'] == 1);

		if (!$success) {
			Billrun_Factory::log()->log("Billrun_Exporter::logDB - Failed when trying to update an export log record with stamp of : {$stamp}. data: " . print_R($data, 1), Zend_Log::NOTICE);
			return false;
		}
		
		return true;
	}
	
	/**
	 * creates basic log in DB
	 * 
	 * @param string $stamp
	 * @return type
	 */
	protected function createLogDB($stamp, $data = array()) {		
		$basicLogData = array(
			'stamp' =>  $stamp,
			'source' => 'export',
			'type' => static::$type,
			'export_hostname' => Billrun_Util::getHostName(),
			'export_time' => date(self::base_dateformat),
		);
		$logData = array_merge($basicLogData, $data);

		$result = $this->logCollection->insert($logData);
		$success = $result == true || ($result['n'] == 1 && $result['ok'] == 1);

		if (!$success) {
			Billrun_Factory::log()->log("Billrun_Exporter::createLogDB - Failed when trying to insert an export log record" . print_r($logData, 1) . " with stamp of : {$stamp}", Zend_Log::NOTICE);
			return false;
		}
		
		return true;
	}

}
