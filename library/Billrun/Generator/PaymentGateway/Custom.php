<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generator for payment gateways files
 */
abstract class Billrun_Generator_PaymentGateway_Custom {

	protected $configByType;
	protected $exportDefinitions;
	protected $generatorDefinitions;
	protected $gatewayName;
	protected $chargeOptions = array();
	protected $data = array();
	protected $headers = array();
	protected $localDir;
	protected $logFile;
	protected $fileName;

	public function __construct($options) {
		if (!isset($options['file_type'])) {
			throw new Exception('Missing file type');
		}
		$fileType = $options['file_type'];
		$configByType = !empty($options[$options['type']]) ? $options[$options['type']] : array();
		$this->configByType = current(array_filter($configByType, function($settingsByType) use ($fileType) {
			return $settingsByType['file_type'] === $fileType;
		}));
		$this->gatewayName = str_replace('_', '', ucwords($options['name'], '_'));
		$this->bills = Billrun_Factory::db()->billsCollection();
	}

	public function generate() {
		$className = $this->getGeneratorClassName();
		$generatorOptions = $this->buildGeneratorOptions();
		$generator = new $className($generatorOptions);
		$generator->generate();
		
		if (!empty($logFile)) {
			$this->logFile->setProcessTime();
			$this->logFile->save();
		}
	}
	
	protected function getDataLine($params) {
		$dataLine = array();
		$dataStructure = $this->configByType['generator']['data_structure'];
		foreach ($dataStructure as $dataField) {
			if (!isset($dataField['path'])) {
				Billrun_Factory::log("Exporter " . $this->configByType['file_type'] . " data structure is missing a path", Zend_Log::DEBUG);
				continue;
			}
			if (isset($dataField['name']) && $dataField['name'] == 'transaction_id') {
				$dataLine[$dataField['path']] = $params['txid'];
			}
			if (isset($dataField['name']) && $dataField['name'] == 'amount') {
				$dataLine[$dataField['path']] = $params['amount'];
			}
			if (isset($dataField['name']) && $dataField['name'] == 'token') {
				$dataLine[$dataField['path']] = $params['token'];
			}
			if (isset($dataField['name']) && $dataField['name'] == 'card_expiration') {
				$dataLine[$dataField['path']] = $params['card_expiration'];
			}
			if (isset($dataField['name']) && $dataField['name'] == 'account_id') {
				$dataLine[$dataField['path']] = $params['aid'];
			}
			if (isset($dataField['hard_coded_value'])) {
				$dataLine[$dataField['path']] = $dataField['hard_coded_value'];
			}
			if (isset($dataField['linked_entity']) && isset($params['aid']) && $dataField['linked_entity']['entity'] == 'account') {
				$dataLine[$dataField['path']] = $this->getLinkedEntityData($dataField['linked_entity']['entity'], $params['aid'], $dataField['linked_entity']['field_name']);
			}
			if (isset($dataField['parameter_name‎']) && in_array($dataField['parameter_name‎'], $this->extraParamsNames) && isset($this->options[$dataField['parameter_name‎']])) {
				$dataLine[$dataField['path']] = $this->options[$dataField['parameter_name‎']];
			}
			if (isset($dataField['type']) && $dataField['type'] == 'date') {
				$dateFormat = isset($dataField['format']) ? $dataField['format'] : Billrun_Base::base_datetimeformat;
				$date = strtotime($dataLine[$dataField['path']]);
				if ($date) {
					$dataLine[$dataField['path']] = date($dateFormat, $date);
				} else {
					Billrun_Factory::log("Couldn't covert date string when generating file type " . $this->configByType['file_type'], Zend_Log::NOTICE);
				}
			}
			$dataLine[$dataField['path']] = $this->prepareLineForGenerate($dataLine[$dataField['path']], $dataField);
		}

		if ($this->configByType['generator']['type'] == 'fixed' || $this->configByType['generator']['type'] == 'separator') {
			ksort($dataLine);
		}
		return $dataLine;
	}
	
	protected function getHeaderLine() {
		$headerLine = array();
		$headerStructure = $this->configByType['generator']['header_structure'];
		foreach ($headerStructure as $headerField) {
			if (!isset($headerField['path'])) {
				Billrun_Factory::log("Exporter " . $this->configByType['file_type'] . " header structure is missing a path", Zend_Log::DEBUG);
				continue;
			}
			if (isset($headerField['name']) && $headerField['name'] == 'date') {
				$dateFormat = isset($headerField['date_format']) ? $headerField['date_format'] : Billrun_Base::base_datetimeformat;
				$headerLine[$headerField['path']] = date($dateFormat);
			}
			if (isset($headerField['name']) && $headerField['name'] == 'unique_id') {
				$headerLine[$headerField['path']] = $this->generateUniqueId();
			}
			if (isset($headerField['name']) && $headerField['name'] == 'num_of_transactions') {
				$headerLine[$headerField['path']] = count($this->customers);
			}
			if (isset($headerField['hard_coded_value'])) {
				$headerLine[$headerField['path']] = $headerField['hard_coded_value'];
			}
			if (isset($headerField['parameter_name‎']) && in_array($headerField['parameter_name‎'], $this->extraParamsNames) && isset($this->options[$headerField['parameter_name‎']])) {
				$headerLine[$headerField['path']] = $this->options[$headerField['parameter_name‎']];
			}	
			if (isset($headerField['type']) && $headerField['type'] == 'date') {
				$dateFormat = isset($headerField['format']) ? $headerField['format'] : Billrun_Base::base_datetimeformat;
				$date = strtotime($headerLine[$headerField['path']]);
				if ($date) {
					$headerLine[$headerField['path']] = date($dateFormat, $date);
				} else {
					Billrun_Factory::log("Couldn't covert date string when generating file type " . $this->configByType['file_type'], Zend_Log::NOTICE);
				}
			}
			$headerLine[$headerField['path']] = $this->prepareLineForGenerate($headerLine[$headerField['path']], $headerField);
		}
		if ($this->configByType['generator']['type'] == 'fixed' || $this->configByType['generator']['type'] == 'separator') {
			ksort($headerLine);
		}
		return $headerLine;
	}
	
	protected function generateUniqueId() {
		return round(microtime(true) * 1000) . rand(100000, 999999);
	}

	protected function getLinkedEntityData($entity, $aid, $field) {
		$account = Billrun_Factory::account();
		$account->load(array('aid' => $aid));
		$accountData = $account->getCustomerData();
		if (!isset($accountData[$field])) {
			Billrun_Factory::log("Field name $field does not exists under entity " . $entity, Zend_Log::DEBUG);
		}
		
		return $accountData[$field];
	}
	
	protected function buildGeneratorOptions() {
		$options['data'] = $this->data;
		$options['headers'] = $this->headers;
		$options['type'] = $this->configByType['generator']['type'];
		$options['delimeter'] = $this->configByType['generator']['separator'];
		$options['file_type'] = $this->configByType['file_type'];
		$options['file_name'] = $this->getFilename();
		$options['local_dir'] = $this->localDir;
		return $options;
	}
	
	protected function getGeneratorClassName() {
		if (!isset($this->configByType['generator']['type'])) {
			throw new Exception('Missing generator type for ' . $this->configByType['file_type']);
		} 
		switch ($this->configByType['generator']['type']) {
			case 'fixed':
			case 'separator':
				$generatorType = 'Csv';
				break;
			case 'xml':
				$generatorType = 'Xml';
				break;
			default:
				throw new Exception('Unknown generator type for ' . $this->configByType['file_type']);
		}
		
		$className = "Billrun_Generator_PaymentGateway_" . $generatorType;
		return $className;
	}
	
	protected function getFilename() {
		if (!empty($this->fileName)) {
			return $this->fileName;
		}
		$translations = array();
		foreach ($this->fileNameParams as $paramObj) {
			$translations[$paramObj['param']] = $this->getTranslationValue($paramObj);
		}
		
		$this->fileName = Billrun_Util::translateTemplateValue($this->fileNameStructure, $translations, null, true);
		return $this->fileName;
	}
	
	protected function prepareLineForGenerate($lineValue, $addedData) {
		$newLine = array();
		$newLine['value'] = isset($addedData['number_format']['decimals']) && is_numeric($lineValue) ? number_format($lineValue, $addedData['number_format']['decimals']) : $lineValue;
		$newLine['name'] = $addedData['name'];
		if (isset($addedData['padding'])) {
			$newLine['padding'] = $addedData['padding'];
		}
		return $newLine;
	}
	
	public function shouldFileBeMoved() {
		$localPath = $this->localDir . '/' . $this->getFilename();
		if (!empty(file_get_contents($localPath))) {
			return true;
		}
		$this->removeEmptyFile();
		return false;
	}
	
	protected function removeEmptyFile() {
		$localPath = $this->localDir . '/' . $this->getFilename();
		$ret = unlink($localPath);
		if ($ret) {
			Billrun_Factory::log()->log('Empty file ' .  $localPath . ' was removed successfully', Zend_Log::INFO);
			return;
		}
		Billrun_Factory::log()->log('Failed removing empty file ' . $localPath, Zend_Log::INFO);
	}
	
	public function move() {
		$exportDetails = $this->configByType['export'];
		$connection = Billrun_Factory::paymentGatewayConnection($exportDetails);
		$fileName = $this->getFilename();
		$connection->export($fileName);
	}
	
	protected function getTranslationValue($paramObj) {
		if (!isset($paramObj['type']) || !isset($paramObj['value'])) {
			Billrun_Factory::log("Missing filename params definitions for file type " . $this->configByType['file_type'], Zend_Log::DEBUG);
		}
		switch ($paramObj['type']) {
			case 'date':
				$dateFormat = isset($paramObj['format']) ? $paramObj['format'] : Billrun_Base::base_datetimeformat;
				$dateValue = ($paramObj['value'] == 'now') ? time() : strtotime($paramObj['value']);
				return date($dateFormat, $dateValue);	
			case 'autoinc':
				if (!isset($paramObj['min_value']) && !isset($paramObj['max_value'])) {
					Billrun_Factory::log("Missing filename params definitions for file type " . $this->configByType['file_type'], Zend_Log::DEBUG);
					return;
				}
				$minValue = $paramObj['min_value'];
				$maxValue = $paramObj['max_value'];
				$dateGroup = isset($paramObj['date_group']) ? $paramObj['date_group'] : Billrun_Base::base_datetimeformat;
				$dateValue = ($paramObj['value'] == 'now') ? time() : strtotime($paramObj['value']);
				$date = date($dateGroup, $dateValue);
				$action = 'transactions_request';
				$fakeCollectionName = '$' . $action . '_' . $this->configByType['file_type'] . '_' . $date;
				$seq = Billrun_Factory::db()->countersCollection()->createAutoInc(array(), $minValue, $fakeCollectionName);
				if ($seq > $maxValue) {
					throw new Exception("Sequence exceeded max value when generating file for file type " . $this->configByType['file_type']);
				}
				if (isset($paramObj['padding'])) {
					$this->padSequence($seq, $paramObj);
				}
				return $seq;
			default:
				Billrun_Factory::log("Unsupported filename_params type for file type " . $this->configByType['file_type'], Zend_Log::DEBUG);
				break;
		}
	}
	
	protected function padSequence($seq, $padding) {
			$padDir = isset($padding['direction']) ? $padding['direction'] : STR_PAD_LEFT;
			$padChar = isset($padding['character']) ? $padding['character'] : '';
			$length = isset($padding['length']) ? $padding['length'] : strlen($seq);
			return str_pad(substr($seq, 0, $length), $length, $padChar, $padDir);
	}
		
	
}

