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
		return $this->startingString. $this->extractionDateFormat . $this->endingString;
	}
	
	protected function prepareLineForGenerate($lineValue, $addedData) {
		$newLine = array();
		$newLine['value'] = $lineValue;
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
		$connection->export();
	}
	
}

