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
		if ($category == 'file_types') {
			if (empty($data['file_type'])) {
				throw new Exception('No file type supplied');
			}
			if ($fileSettings = $this->getFileTypeSettings($currentConfig, $data['file_type'])) {
				return $fileSettings;
			}
			throw new Exception('Unknown file type ' . $data['file_type']);
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
		if ($category === 'file_types') {
			if (isset($data['file_type'])) {
				if ($fileSettings = $this->getFileTypeSettings($updatedData, $data['file_type'])) {
					$fileSettings = array_merge($fileSettings, $data);
				} else {
					$fileSettings = $data;
				}
				$fileSettings = $this->validateFileSettings($fileSettings);
				$this->setFileTypeSettings($updatedData, $fileSettings);
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
					$fileSettings = $this->validateFileSettings($fileSettings);
					$this->setFileTypeSettings($updatedData, $fileSettings);
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

	protected function validateFileSettings($fileSettings) {
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
		return $updatedFileSettings;
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
					return $length == intval($length);
				})) {
				throw new Exception('Duplicate field names found');
			}
		}
		if ($customKeys != array_unique($customKeys)) {
			throw new Exception('Duplicate field names found');
		}
		if ($customKeys != array_filter($customKeys, array('Billrun_Util', 'isValidCustomJsonKey'))) {
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
		return $processorSettings;
	}

	protected function validateCustomerIdentificationConfiguration($customerIdentificationSettings) {
		return $customerIdentificationSettings;
	}

	protected function validateRateCalculatorsConfiguration($rateCalculatorsSettings) {
		return $rateCalculatorsSettings;
	}

	protected function validateReceiverConfiguration($receiverSettings) {
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
