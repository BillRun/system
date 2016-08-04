<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

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
		
		$valueInCategory = 
			Billrun_Util::getValueByMongoIndex($currentConfig, $category);
		
		if($valueInCategory === null) {
			throw new Exception('Unknown category ' . $category);
		}
		
		// TODO: Create a config class to handle just file_types.
		if ($category == 'file_types') {
			if(!is_array($data)) {
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
		}
		else if ($category == 'subscribers') {
			return $currentConfig['subscribers'];
		} else if(Billrun_Config::isComplex($valueInCategory)) {
			// Get the complex object.
			return Billrun_Config::getComplexValue($valueInCategory);
		}
		throw new Exception('Unknown category ' . $category);
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
		
		$valueInCategory = 
			Billrun_Util::getValueByMongoIndex($updatedData, $category);
		
		
		if($valueInCategory === null) {
			// TODO: Do we allow setting values with NEW keys into the settings?
			Billrun_Factory::log("Unknown category", Zend_Log::NOTICE);
			return 0;
		}
		
		// TODO: Create a config class to handle just file_types.
		if ($category === 'file_types') {
			if(!is_array($data)) {
				Billrun_Factory::log("Invalid data for file types.");
				return 0;
			}
			
			$rawFileSettings = $this->getFileTypeSettings($updatedData, $data['file_type']);
			if ($rawFileSettings) {
				$fileSettings = array_merge($rawFileSettings, $data);
			} else {
				$fileSettings = $data;
			}
			$this->setFileTypeSettings($updatedData, $fileSettings);
			$fileSettings = $this->validateFileSettings($updatedData, $data['file_type']);
		} else {			
			// Check if complex object.
			if(!Billrun_Config::isComplex($valueInCategory)) {
				// TODO: Do we allow setting?
				Billrun_Factory::log("Encountered a problem", Zend_Log::NOTICE);
				return 0;
			} else {
				// Set the value for the complex object,
				$valueInCategory['v'] = $data;
				
				// Validate the complex object.
				if(!Billrun_Config::isComplexValid($valueInCategory)) {
					Billrun_Factory::log("Invalid complex object " . print_r($valueInCategory,1), Zend_Log::NOTICE);
					return 0;
				}
				
				// Update the config.
				if(!Billrun_Util::setValueByMongoIndex($valueInCategory, $updatedData, $category)) {
					return 0;
				}
			}
		}
		
		$ret = $this->collection->insert($updatedData);
		return !empty($ret['ok']);
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

	protected function setFileTypeSettings(&$config, $fileSettings) {
		$fileType = $fileSettings['file_type'];
		foreach ($config['file_types'] as &$someFileSettings) {
			if ($someFileSettings['file_type'] == $fileType) {
				$someFileSettings = $fileSettings;
				return;
			}
		}
		$config['file_types'] = array($fileSettings);
	}

	protected function unsetFileTypeSettings(&$config, $fileType) {
		$config['file_types'] = array_filter($config['file_types'], function($fileSettings) use ($fileType) {
			return $fileSettings['file_type'] !== $fileType;
		});
	}

	protected function validateFileSettings($config, $fileType) {
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
		$updatedFileSettings = $this->checkForConflics($config, $fileType);
		return $updatedFileSettings;
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
			}, $fileSettings['processor']['usaget_mapping']);
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
	}

	protected function validateParserConfiguration($parserSettings) {
		if (empty($parserSettings['type']) || !in_array($parserSettings['type'], array('separator', 'fixed'))) {
			throw new Exception('Illegal parser type');
		}
		if ($parserSettings['type'] == 'separator')
			if (!is_array($parserSettings['structure']) || !$parserSettings['structure']) {
				throw new Exception('Illegal fields');
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
			// TODO validate date format
		}
		if (!isset($processorSettings['date_field'], $processorSettings['volume_field'], $processorSettings['volume_field'], $processorSettings['usaget_mapping'])) {
			throw new Exception('Missing mandatory processor configuration');
		}
		if (!$processorSettings['usaget_mapping'] || !is_array($processorSettings['usaget_mapping'])) {
			throw new Exception('Missing mandatory processor configuration');
		}
		$processorSettings['usaget_mapping'] = array_values($processorSettings['usaget_mapping']);
		foreach ($processorSettings['usaget_mapping'] as $index => $mapping) {
			if (!isset($mapping['src_field'], $mapping['pattern'], $mapping['usaget']) || !Billrun_Util::isValidRegex($mapping['pattern'])) {
				throw new Exception('Illegal usaget mapping at index' . $index);
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
			if (iss)
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
