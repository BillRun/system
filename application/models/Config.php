<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/library/vendor/autoload.php';

/**
 * Configmodel class
 *
 * @package  Models
 * @since    2.1
 */
class ConfigModel {

	/**
	 * the collection the config run on
	 * 
	 * @var Mongodloid Collection
	 */
	protected $collection;

	/**
	 * the config values
	 * @var array
	 */
	protected $data;
	
	/**
	 * options of config
	 * @var array
	 */
	protected $options;
	protected $fileClassesOrder = array('file_type', 'parser', 'processor', 'customer_identification_fields', 'rate_calculators', 'receiver');
	protected $ratingAlgorithms = array('match', 'longestPrefix');

	public function __construct() {
		// load the config data from db
		$this->collection = Billrun_Factory::db()->configCollection();
		$this->options = array('receive', 'process', 'calculate');
		$this->loadConfig();
	}

	public function getOptions() {
		return $this->options;
	}

	protected function loadConfig() {
		$ret = $this->collection->query()
			->cursor()
			->sort(array('_id' => -1))
			->limit(1)
			->current()
			->getRawData();
		$this->data = $ret;
	}

	public function getConfig() {
		return $this->data;
	}

	/**
	 * 
	 * @param int $data
	 * @return type
	 * @deprecated since version Now
	 * @todo Remove this function?
	 */
	public function setConfig($data) {
		$updatedData = array_merge($this->getConfig(), $data);
		unset($updatedData['_id']);
		foreach ($this->options as $option) {
			if (!isset($data[$option])) {
				$data[$option] = 0;
			}
		}
		return $this->collection->insert($updatedData);
	}

	public function getFromConfig($category, $data) {
		$currentConfig = $this->getConfig();

		// TODO: Create a config class to handle just file_types.
		if ($category == 'file_types') {
			if (!is_array($data)) {
				Billrun_Factory::log("Invalid data for file types.");
				return 0;
			}
			if (empty($data['file_type'])) {
				return $currentConfig['file_types'];
			}
			if ($fileSettings = $this->getFileTypeSettings($currentConfig, $data['file_type'])) {
				return $fileSettings;
			}
			throw new Exception('Unknown file type ' . $data['file_type']);
		} else if ($category == 'subscribers') {
			return $currentConfig['subscribers'];
		} else if ($category == 'payment_gateways') {
 			if (!is_array($data)) {
 				Billrun_Factory::log("Invalid data for payment_gateways.");
 				return 0;
 			}
 			if (empty($data['name'])) {
 				return $currentConfig['payment_gateways'];
 			}
 			if ($pgSettings = $this->getPaymentGatewaySettings($currentConfig, $data['name'])) {
 				return $pgSettings;
 			}
 			throw new Exception('Unknown payment gateway ' . $data['name']);
		}

		return $this->_getFromConfig($currentConfig, $category, $data);
	}

	/**
	 * Internal getFromConfig function, recursively extracting values and handling
	 * any complex values.
	 * @param type $currentConfig
	 * @param type $category
	 * @param array $data
	 * @return mixed value
	 * @throws Exception
	 */
	protected function _getFromConfig($currentConfig, $category, $data) {
		if (is_array($data) && !empty($data)) {
			$dataKeys = array_keys($data);
			foreach ($dataKeys as $key) {
				$result[] = $this->_getFromConfig($currentConfig, $category . "." . $key, null);
			}
			return $result;
		}

		$valueInCategory = Billrun_Utils_Mongo::getValueByMongoIndex($currentConfig, $category);

		if ($valueInCategory === null) {
			throw new Exception('Unknown category ' . $category);
		}
		
		$translated = Billrun_Config::translateComplex($valueInCategory);
		return $translated;
	}

	protected function extractComplexFromArray($array) {
		$returnData = array();
		// Check for complex objects.
		foreach ($array as $key => $value) {
			if (Billrun_Config::isComplex($value)) {
				// Get the complex object.
				$returnData[$key] = Billrun_Config::getComplexValue($value);
			} else {
				$returnData[$key] = $value;
			}
		}

		return $returnData;
	}

	/**
	 * 
	 * @param string $category
	 * @param int $data
	 * @param boolean $set
	 * @return type
	 */
	public function updateConfig($category, $data) {
		$updatedData = $this->getConfig();
		unset($updatedData['_id']);

		// TODO: Create a config class to handle just file_types.
		if ($category === 'file_types') {
			if (!is_array($data)) {
				Billrun_Factory::log("Invalid data for file types.");
				return 0;
			}
			if (empty($data['file_type'])) {
				throw new Exception('Couldn\'t find file type name');
			}
			$rawFileSettings = $this->getFileTypeSettings($updatedData, $data['file_type']);
			if ($rawFileSettings) {
				$fileSettings = array_merge($rawFileSettings, $data);
			} else {
				$fileSettings = $data;
			}
			$this->setFileTypeSettings($updatedData, $fileSettings);
			$fileSettings = $this->validateFileSettings($updatedData, $data['file_type']);
		} else if ($category === 'payment_gateways') {
			if (!is_array($data)) {
				Billrun_Factory::log("Invalid data for payment gateways.");
				return 0;
			}
			if (empty($data['name'])) {
				throw new Exception('Couldn\'t find payment gateway name');
			}
			$supported = Billrun_Factory::config()->getConfigValue('PaymentGateways.' . $data['name'] . '.supported');
			if (is_null($supported) || !$supported) {
				throw new Exception('Payment gateway is not supported');
			}
			$gatewaysSettings = Billrun_Factory::config()->getConfigValue('PaymentGateways'); // TODO: Remove when finished to do more generic
			$omnipay_supported = array_filter($gatewaysSettings, function($paymentGateway){
				return $paymentGateway['omnipay_supported'] == true;
				});
			if (in_array($data['name'], array_keys($omnipay_supported))) {
					$gateway = Omnipay\Omnipay::create($data['name']);
					$defaultParameters = $gateway->getDefaultParameters();
			}
			else{
				$defaultParameters = array('terminal_id' => "", 'user'=>"", 'password'=>"");
			}
			$releventParameters = array_intersect_key($defaultParameters, $data['params']); 
			$neededParameters = array_keys($releventParameters);
			foreach ($data['params'] as $key => $value) {
				if (!in_array($key, $neededParameters)){
					unset($data['params'][$key]);
				}
			}
			$rawPgSettings = $this->getPaymentGatewaySettings($updatedData, $data['name']);
			if ($rawPgSettings) {
				$pgSettings = array_merge($rawPgSettings, $data);
			} else {
				$pgSettings = $data;
			}
			$this->setPaymentGatewaySettings($updatedData, $pgSettings);
 			$pgSettings = $this->validatePaymentGatewaySettings($updatedData, $data);
 			if (!$pgSettings){
 				return 0;
 			}
		} else {
			if (!$this->_updateConfig($updatedData, $category, $data)) {
				return 0;
			}
		}
		
		$ret = $this->collection->insert($updatedData);
		$saveResult = !empty($ret['ok']);
		if ($saveResult) {
			// Reload timezone.
			Billrun_Config::getInstance()->refresh();
		}

		return $saveResult;
	}

	/**
	 * Load the config template.
	 * @return array The array representing the config template
	 */
	protected function loadTemplate() {
		// Load the config template.
		// TODO: Move the file path to a constant
		$templateFileName = APPLICATION_PATH . "/conf/config/template.ini";
		return parse_ini_file($templateFileName, 1);
	}
	
	protected function _updateConfig(&$currentConfig, $category, $data) {
		// TODO: if it's possible to receive a non-associative array of associative arrays, we need to also check isMultidimentionalArray
		if (Billrun_Util::isAssoc($data)) {
			foreach ($data as $key => $value) {
				if (!$this->_updateConfig($currentConfig, $category . "." . $key, $value)) {
					return 0;
				}
			}
			return 1;
		}

		$valueInCategory = Billrun_Utils_Mongo::getValueByMongoIndex($currentConfig, $category);

		if ($valueInCategory === null) {
			$result = $this->handleNewCategory($category, $data, $currentConfig);
			return $result;
		}

		// Check if complex object.
		if (!Billrun_Config::isComplex($valueInCategory)) {
			// TODO: Do we allow setting?
			return Billrun_Utils_Mongo::setValueByMongoIndex($data, $currentConfig, $category);
		}
		// Set the value for the complex object,
		$valueInCategory['v'] = $data;

		// Validate the complex object.
		if (!Billrun_Config::isComplexValid($valueInCategory)) {
			Billrun_Factory::log("Invalid complex object " . print_r($valueInCategory, 1), Zend_Log::NOTICE);
			$invalidFields[] = Billrun_Utils_Mongo::mongoArrayToInvalidFieldsArray($category, ".");
			throw new Billrun_Exceptions_InvalidFields($invalidFields);
		}

		// Update the config.
		if (!Billrun_Utils_Mongo::setValueByMongoIndex($valueInCategory, $currentConfig, $category)) {
			return 0;
		}

		return 1;
	}
	
	/**
	 * Handle the scenario of a category that doesn't exist in the database
	 * @param string $category - The current category.
	 * @param array $data - Data to set.
	 * @param array $currenConfig - Current configuration data.
	 */
	protected function handleNewCategory($category, $data, &$currentConfig) {
		$splitCategory = explode('.', $category);

		$template = $this->loadTemplate();
		
		$found = true;
		$ptrTemplate = &$template;
		$newConfig = $currentConfig;
		$newValueIndex = &$newConfig;
		
		// Go through the keys
		foreach ($splitCategory as $key) {
			if(!isset($newValueIndex[$key])) {
				$newValueIndex[$key] = array();
			}
			$newValueIndex = &$newValueIndex[$key];
			if(!isset($ptrTemplate[$key])) {
				$found = false;
				break;
			}
			$ptrTemplate = &$ptrTemplate[$key];
		}
		
		// Check if the value exists in the settings template ini.
		if(!$found) {
			Billrun_Factory::log("Unknown category", Zend_Log::NOTICE);
			return 0;
		}
		
		// Set the data
		$currentConfig = $newConfig;

		$result = Billrun_Utils_Mongo::setValueByMongoIndex($data, $currentConfig, $category);
		return $result;
	}
	
	protected function setConfigValue(&$config, $category, $toSet) {
		// Check if complex object.
		if (Billrun_Config::isComplex($toSet)) {
			return $this->setComplexValue($toSet);
		}

		if (is_array($toSet)) {
			return $this->setConfigArrayValue($toSet);
		}

		return Billrun_Utils_Mongo::setValueByMongoIndex($toSet, $config, $category);
	}

	protected function setConfigArrayValue($toSet) {
		
	}

	protected function setComplexValue($toSet) {
		// Check if complex object.
		if (!Billrun_Config::isComplex($valueInCategory)) {
			// TODO: Do we allow setting?
			Billrun_Factory::log("Encountered a problem", Zend_Log::NOTICE);
			return 0;
		}
		// Set the value for the complex object,
		$valueInCategory['v'] = $data;

		// Validate the complex object.
		if (!Billrun_Config::isComplexValid($valueInCategory)) {
			Billrun_Factory::log("Invalid complex object " . print_r($valueInCategory, 1), Zend_Log::NOTICE);
			$invalidFields = Billrun_Utils_Mongo::mongoArrayToInvalidFieldsArray($category, ".", false);
			throw new Billrun_Exceptions_InvalidFields($invalidFields);
		}

		// Update the config.
		if (!Billrun_Utils_Mongo::setValueByMongoIndex($valueInCategory, $currentConfig, $category)) {
			return 0;
		}

		if (Billrun_Config::isComplex($toSet)) {
			// Get the complex object.
			return Billrun_Config::getComplexValue($toSet);
		}

		if (is_array($toSet)) {
			return $this->extractComplexFromArray($toSet);
		}

		return $toSet;
	}

	public function unsetFromConfig($category, $data) {
		$updatedData = $this->getConfig();
		unset($updatedData['_id']);
		if ($category === 'file_types') {
			if (isset($data['file_type'])) {
				if (count($data) == 1) {
					$this->unsetFileTypeSettings($updatedData, $data['file_type']);
				} else {
					if (!$fileSettings = $this->getFileTypeSettings($updatedData, $data['file_type'])) {
						throw new Exception('Unkown file type ' . $data['file_type']);
					}
					foreach (array_keys($data) as $key) {
						if ($key != 'file_type') {
							unset($fileSettings[$key]);
						}
					}
					if (!$this->isLegalFileSettingsKeys(array_keys($fileSettings))) {
						throw new Exception('Operation will result in illegal file settings. Aborting.');
					}
					$this->setFileTypeSettings($updatedData, $fileSettings);
					$fileSettings = $this->validateFileSettings($updatedData, $data['file_type']);
				}
			}
		}
		if ($category === 'payment_gateways') {
 			if (isset($data['name'])) {
 				if (count($data) == 1) {
 					$this->unsetPaymentGatewaySettings($updatedData, $data['name']);
 				} else {
 					if (!$pgSettings = $this->getPaymentGatewaySettings($updatedData, $data['name'])) {
 						throw new Exception('Unkown payment gateway ' . $data['name']);
 					}
 					foreach (array_keys($data) as $key) {
 						if ($key != 'name') {
 							unset($pgSettings[$key]);
 						}
 					}
 					$this->setPaymentGatewaySettings($updatedData, $pgSettings);
 				}
 			}
 		}
 
		$ret = $this->collection->insert($updatedData);
		return !empty($ret['ok']);
	}

	protected function getFileTypeSettings($config, $fileType) {
		if ($filtered = array_filter($config['file_types'], function($fileSettings) use ($fileType) {
			return $fileSettings['file_type'] === $fileType;
		})) {
			return current($filtered);
		}
		return FALSE;
	}
	
	protected function getPaymentGatewaySettings($config, $pg) {
 		if ($filtered = array_filter($config['payment_gateways'], function($pgSettings) use ($pg) {
 			return $pgSettings['name'] === $pg;
 		})) {
 			return current($filtered);
 		}
 		return FALSE;
 	}
 

	protected function setFileTypeSettings(&$config, $fileSettings) {
		$fileType = $fileSettings['file_type'];
		foreach ($config['file_types'] as &$someFileSettings) {
			if ($someFileSettings['file_type'] == $fileType) {
				$someFileSettings = $fileSettings;
				return;
			}
		}
		$config['file_types'] = array_merge($config['file_types'], array($fileSettings));
	}
	
	
	protected function setPaymentGatewaySettings(&$config, $pgSettings) {
 		$paymentGateway = $pgSettings['name'];
 		foreach ($config['payment_gateways'] as &$somePgSettings) {
 			if ($somePgSettings['name'] == $paymentGateway) {
 				$somePgSettings = $pgSettings;
 				return;
 			}
 		}
 		$config['payment_gateways'] = array_merge($config['payment_gateways'], array($pgSettings));
 	}
 

	protected function unsetFileTypeSettings(&$config, $fileType) {
		$config['file_types'] = array_filter($config['file_types'], function($fileSettings) use ($fileType) {
			return $fileSettings['file_type'] !== $fileType;
		});
	}
	
	
	protected function unsetPaymentGatewaySettings(&$config, $pg) {
 		$config['payment_gateways'] = array_filter($config['payment_gateways'], function($pgSettings) use ($pg) {
 			return $pgSettings['name'] !== $pg;
 		});
 	}
 

	protected function validateFileSettings(&$config, $fileType) {
		$fileSettings = $this->getFileTypeSettings($config, $fileType);
		if (!$this->isLegalFileSettingsKeys(array_keys($fileSettings))) {
			throw new Exception('Incorrect file settings keys.');
		}
		$updatedFileSettings = array();
		$updatedFileSettings['file_type'] = $fileSettings['file_type'];
		if (isset($fileSettings['parser'])) {
			$updatedFileSettings['parser'] = $this->validateParserConfiguration($fileSettings['parser']);
			if (isset($fileSettings['processor'])) {
				$updatedFileSettings['processor'] = $this->validateProcessorConfiguration($fileSettings['processor']);
				if (isset($fileSettings['customer_identification_fields'])) {
					$updatedFileSettings['customer_identification_fields'] = $this->validateCustomerIdentificationConfiguration($fileSettings['customer_identification_fields']);
					if (isset($fileSettings['rate_calculators'])) {
						$updatedFileSettings['rate_calculators'] = $this->validateRateCalculatorsConfiguration($fileSettings['rate_calculators']);
						if (isset($fileSettings['receiver'])) {
							$updatedFileSettings['receiver'] = $this->validateReceiverConfiguration($fileSettings['receiver']);
						}
					}
				}
			}
		}
		$this->setFileTypeSettings($config, $updatedFileSettings);
		return $this->checkForConflics($config, $fileType);
	}
	
	
	protected function validatePaymentGatewaySettings(&$config, $pg) {
 		$connectionParameters = array_keys($pg['params']);
 		$name = $pg['name'];
		$gatewaysSettings = Billrun_Factory::config()->getConfigValue('PaymentGateways');
		$supportedGateways = array_filter($gatewaysSettings, function($paymentGateway){
 			return $paymentGateway['supported'] == true;
 		});
		if (!in_array($name, array_keys($supportedGateways))){
			Billrun_Factory::log("Unsupported Payment Gateway: ", $name);
			return false;
		}
		$omnipay_supported = array_filter($gatewaysSettings, function($paymentGateway){
 			return $paymentGateway['omnipay_supported'] == true;
 		});
		if (in_array($name, array_keys($omnipay_supported))) {
			$gateway = Omnipay\Omnipay::create($name);
			$defaultParameters = $gateway->getDefaultParameters();
			$defaultParametersKeys = array_keys($defaultParameters);
			$diff = array_diff($defaultParametersKeys, $connectionParameters);
			if (!empty($diff)) {
				Billrun_Factory::log("Wrong parameters for connection to", $name);
				return false;
			}
			// TODO: check Auth to gateway through Omnipay
		}
		
 		else if ($name == "CreditGuard"){
			$defaultParameters = array('terminal_id' => "", 'user'=>"", 'password'=>"");
			$defaultParametersKeys = array_keys($defaultParameters);
			$diff = array_diff($defaultParametersKeys, $connectionParameters);
			if (!empty($diff)) {
				Billrun_Factory::log("Wrong parameters for connection to", $name);
				return false;
			}
		// meanewhile credentials of credit guard, TODO generic for all payemnt gateways not ompipay supported and functions for identical code.
		}
		
		
		
 		return true;
 	}
 

	protected function checkForConflics($config, $fileType) {
		$fileSettings = $this->getFileTypeSettings($config, $fileType);
		if (isset($fileSettings['processor'])) {
			$customFields = $fileSettings['parser']['custom_keys'];
			$uniqueFields[] = $dateField = $fileSettings['processor']['date_field'];
			$uniqueFields[] = $volumeField = $fileSettings['processor']['volume_field'];
			$useFromStructure = $uniqueFields;
			$usagetMappingSource = array_map(function($mapping) {
				return $mapping['src_field'];
			}, array_filter($fileSettings['processor']['usaget_mapping'], function($mapping) {
					return isset($mapping['src_field']);
				}));
			if (array_diff($usagetMappingSource, $customFields)) {
				throw new Exception('Unknown fields used for usage type mapping: ' . implode(', ', $usagetMappingSource));
			}
			$usagetTypes = array_map(function($mapping) {
				return $mapping['usaget'];
			}, $fileSettings['processor']['usaget_mapping']);
			if (isset($fileSettings['processor']['default_usaget'])) {
				$usagetTypes[] = $fileSettings['processor']['default_usaget'];
				$usagetTypes = array_unique($usagetTypes);
			}
			if (isset($fileSettings['customer_identification_fields'])) {
				$customerMappingSource = array_map(function($mapping) {
					return $mapping['src_key'];
				}, $fileSettings['customer_identification_fields']);
				$useFromStructure = $uniqueFields = array_merge($uniqueFields, array_unique($customerMappingSource));
				$customerMappingTarget = array_map(function($mapping) {
					return $mapping['target_key'];
				}, $fileSettings['customer_identification_fields']);
				$subscriberFields = array_map(function($field) {
					return $field['field_name'];
				}, array_filter($config['subscribers']['subscriber']['fields'], function($field) {
						return !empty($field['unique']);
					}));
				if ($subscriberDiff = array_unique(array_diff($customerMappingTarget, $subscriberFields))) {
					throw new Exception('Unknown subscriber fields ' . implode(',', $subscriberDiff));
				}
				if (isset($fileSettings['rate_calculators'])) {
					$ratingUsageTypes = array_keys($fileSettings['rate_calculators']);
					foreach ($fileSettings['rate_calculators'] as $usageRules) {
						foreach ($usageRules as $rule) {
							$ratingLineKeys[] = $rule['line_key'];
						}
					}
					$useFromStructure = array_merge($useFromStructure, $ratingLineKeys);
					if ($unknownUsageTypes = array_diff($ratingUsageTypes, $usagetTypes)) {
						throw new Exception('Unknown usage type(s) in rating: ' . implode(',', $unknownUsageTypes));
					}
					if ($usageTypesMissingRating = array_diff($usagetTypes, $ratingUsageTypes)) {
						throw new Exception('Missing rating rules for usage types(s): ' . implode(',', $usageTypesMissingRating));
					}
				}
			}
			if ($uniqueFields != array_unique($uniqueFields)) {
				throw new Exception('Cannot use same field for different configurations');
			}
			if ($diff = array_diff($useFromStructure, $customFields)) {
				throw new Exception('Unknown source field(s) ' . implode(',', $diff));
			}
		}
		return true;
	}

	protected function validateParserConfiguration($parserSettings) {
		if (empty($parserSettings['type'])) {
			throw new Exception('No parser type selected');
		}
		$allowedParsers = array('separator', 'fixed');
		if (!in_array($parserSettings['type'], $allowedParsers)) {
			throw new Exception('Parser must be one of: ' . implode(',', $allowedParsers));
		}
		if (empty($parserSettings['structure']) || !is_array($parserSettings['structure'])) {
			throw new Exception('No file structure supplied');
		}
		if ($parserSettings['type'] == 'separator') {
			$customKeys = $parserSettings['structure'];
			if (empty($parserSettings['separator'])) {
				throw new Exception('Missing CSV separator');
			}
			if (!(is_scalar($parserSettings['separator']) && !is_bool($parserSettings['separator']))) {
				throw new Exception('Illegal seprator ' . $parserSettings['separator']);
			}
		} else {
			$customKeys = array_keys($parserSettings['structure']);
			$customLengths = array_values($parserSettings['structure']);
			if ($customLengths != array_filter($customLengths, function($length) {
					return Billrun_Util::IsIntegerValue($length);
				})) {
				throw new Exception('Duplicate field names found');
			}
		}
		$parserSettings['custom_keys'] = $customKeys;
		if ($customKeys != array_unique($customKeys)) {
			throw new Exception('Duplicate field names found');
		}
		if ($customKeys != array_filter($customKeys, array('Billrun_Util', 'isValidCustomLineKey'))) {
			throw new Exception('Illegal field names');
		}
		foreach (array('H', 'D', 'T') as $rowKey) {
			if (empty($parserSettings['line_types'][$rowKey])) {
				$parserSettings['line_types'][$rowKey] = $rowKey == 'D' ? '//' : '/^none$/';
			} else if (!Billrun_Util::isValidRegex($parserSettings['line_types'][$rowKey])) {
				throw new Exception('Invalid regex ' . $parserSettings['line_types'][$rowKey]);
			}
		}
		return $parserSettings;
	}

	protected function validateProcessorConfiguration($processorSettings) {
		$processorSettings['type'] = 'Usage';
		if (isset($processorSettings['date_format'])) {
			if (isset($processorSettings['time_field']) && !isset($processorSettings['time_format'])) {
				throw new Exception('Missing processor time format (in case date format is set, and timedate are in separated fields)');
			}
			// TODO validate date format
		}
		if (!isset($processorSettings['date_field'])) {
			throw new Exception('Missing processor date field');
		}
		if (!isset($processorSettings['volume_field'])) {
			throw new Exception('Missing processor volume field');
		}
		if (!(isset($processorSettings['usaget_mapping']) || isset($processorSettings['default_usaget']))) {
			throw new Exception('Missing processor usage type mapping rules');
		}
		if (isset($processorSettings['usaget_mapping'])) {
			if (!$processorSettings['usaget_mapping'] || !is_array($processorSettings['usaget_mapping'])) {
				throw new Exception('Missing mandatory processor configuration');
			}
			$processorSettings['usaget_mapping'] = array_values($processorSettings['usaget_mapping']);
			foreach ($processorSettings['usaget_mapping'] as $index => $mapping) {
				if (isset($mapping['src_field']) && !(isset($mapping['pattern']) && Billrun_Util::isValidRegex($mapping['pattern'])) || empty($mapping['usaget'])) {
					throw new Exception('Illegal usaget mapping at index ' . $index);
				}
			}
		}
		if (!isset($processorSettings['orphan_files_time'])) {
			$processorSettings['orphan_files_time'] = '6 hours';
		}
		return $processorSettings;
	}

	protected function validateCustomerIdentificationConfiguration($customerIdentificationSettings) {
		if (!is_array($customerIdentificationSettings) || !$customerIdentificationSettings) {
			throw new Exception('Illegal customer identification settings');
		}
		$customerIdentificationSettings = array_values($customerIdentificationSettings);
		foreach ($customerIdentificationSettings as $index => $settings) {
			if (!isset($settings['src_key'], $settings['target_key'])) {
				throw new Exception('Illegal customer identification settings at index ' . $index);
			}
			if (array_key_exists('conditions', $settings) && (!is_array($settings['conditions']) || !$settings['conditions'] || !($settings['conditions'] == array_filter($settings['conditions'], function ($condition) {
					return isset($condition['field'], $condition['regex']) && Billrun_Util::isValidRegex($condition['regex']);
				})))) {
				throw new Exception('Illegal customer identification conditions field at index ' . $index);
			}
			if (isset($settings['clear_regex']) && !Billrun_Util::isValidRegex($settings['clear_regex'])) {
				throw new Exception('Invalid customer identification clear regex at index ' . $index);
			}
		}
		return $customerIdentificationSettings;
	}

	protected function validateRateCalculatorsConfiguration($rateCalculatorsSettings) {
		if (!is_array($rateCalculatorsSettings)) {
			throw new Exception('Rate calculators settings is not an array');
		}
		foreach ($rateCalculatorsSettings as $usaget => $rateRules) {
			foreach ($rateRules as $rule) {
				if (!isset($rule['type'], $rule['rate_key'], $rule['line_key'])) {
					throw new Exception('Illegal rating rules for usaget ' . $usaget);
				}
				if (!in_array($rule['type'], $this->ratingAlgorithms)) {
					throw new Exception('Illegal rating algorithm for usaget ' . $usaget);
				}
			}
		}
		return $rateCalculatorsSettings;
	}

	protected function validateReceiverConfiguration($receiverSettings) {
		if (!is_array($receiverSettings)) {
			throw new Exception('Receiver settings is not an array');
		}
		if (!array_key_exists('connections', $receiverSettings) || !is_array($receiverSettings['connections']) || !$receiverSettings['connections']) {
			throw new Exception('Receiver \'connections\' does not exist or is empty');
		}
		$receiverSettings['type'] = 'ftp';
		if (isset($receiverSettings['limit'])) {
			if (!Billrun_Util::IsIntegerValue($receiverSettings['limit']) || $receiverSettings['limit'] < 1) {
				throw new Exception('Illegal receiver limit value ' . $receiverSettings['limit']);
			}
			$receiverSettings['limit'] = intval($receiverSettings['limit']);
		} else {
			$receiverSettings['limit'] = 3;
		}
		if ($receiverSettings['type'] == 'ftp') {
			foreach ($receiverSettings['connections'] as $index => $connection) {
				if (!isset($connection['name'], $connection['host'], $connection['user'], $connection['password'], $connection['remote_directory'], $connection['passive'], $connection['delete_received'])) {
					throw new Exception('Missing receiver\'s connection field at index ' . $index);
				}
				if (filter_var($connection['host'], FILTER_VALIDATE_IP) === FALSE) {
					throw new Exception($connection['host'] . ' is not a valid IP addresss');
				}
				$connection['passive'] = $connection['passive'] ? 1 : 0;
				$connection['delete_received'] = $connection['delete_received'] ? 1 : 0;
			}
		}
		return $receiverSettings;
	}

	protected function isLegalFileSettingsKeys($keys) {
		$hole = FALSE;
		foreach ($this->fileClassesOrder as $class) {
			if (!in_array($class, $keys)) {
				$hole = TRUE;
			} else if ($hole) {
				return FALSE;
			}
		}
		return TRUE;
	}

	public function save($items) {
		$data = $this->getConfig();
		$saveData = array_merge($data, $items);
		$this->setConfig($saveData);
	}

}
