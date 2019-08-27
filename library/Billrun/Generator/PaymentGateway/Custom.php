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
		
//		$this->generateStructure = $structConfig['generator'];
//		$this->exportDefinitions = $structConfig['export'];
			

		
	}

	protected function buildHeader() {
		$line = $this->getHeaderLine();
		$this->headers[0] = $line;
	}
	
	protected function setFilename() {
		$this->filename = $this->startingString. $this->extractionDateFormat . $this->endingString;
	}
	
	protected function writeHeaders() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->headers as $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$padLength = $this->generateStructure['pad_length_header'];
			$fileContents .= $this->getHeaderRowContent($entity, $padLength);
			$fileContents .= PHP_EOL;
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($fileContents);
	}
		
	protected function writeRows() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->data as $index => $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$padLength = $this->generateStructure['pad_length_data'];
			$fileContents .= $this->getRowContent($entity, $padLength);
			if ($index < count($this->customers)-1){
				$fileContents.= PHP_EOL;
			}
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($fileContents);
	}
	
	public function generate() {
		$className = $this->getGeneratorClassName();
		$generatorOptions = $this->buildGeneratorOptions();
		$generator = new $className($generatorOptions);
		
		$generator->generate();
		
		
//		if (count($this->data)) {
//			$this->writeHeaders();
//			$this->writeRows();
//		}
	//	$this->cgLogFile->setProcessTime();
	//	$this->cgLogFile->save();
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
		}
		if ($this->configByType['generator']['type'] == 'fixed' || $this->configByType['generator']['type'] == 'separator') {
			ksort($headerLine);
		}
		return $headerLine;
	}
	
	protected function generateUniqueId() {
		return round(microtime(true) * 1000) . rand(100000, 999999);
	}
	
	protected function getHeaderRowContent($entity,$pad_length = array()) {
		$this->pad_type = STR_PAD_LEFT;
		$row_contents = '';
		if (!empty($pad_length)){
			$this->pad_length = $pad_length;
		}
		$header_numeric_fields = $this->generateStructure['header']['numeric_fields'];
		for ($key = 0; $key < count($this->pad_length); $key++) {
			if (in_array($key,$header_numeric_fields)){
				$this->pad_string = '0';
			}
			else{
				$this->pad_string = ' ';
			}
			$row_contents.=str_pad((isset($entity[$key]) ? substr($entity[$key], 0, $this->pad_length[$key]) : ''), $this->pad_length[$key], $this->pad_string, $this->pad_type);
		}
		return $row_contents;
	}
	
	protected function getRowContent($entity,$pad_length = array()) {
		$this->pad_type = STR_PAD_LEFT;
		$row_contents = '';
		if (!empty($pad_length)){
			$this->pad_length = $pad_length;
		}
		$data_numeric_fields = $this->generateStructure['data']['numeric_fields'];
		for ($key = 0; $key < count($this->pad_length); $key++) {
			if (in_array($key, $data_numeric_fields)){
				$this->pad_string = '0';
			}
			else{
				$this->pad_string = ' ';
			}
			$row_contents.=str_pad((isset($entity[$key]) ? substr($entity[$key], 0, $this->pad_length[$key]) : ''), $this->pad_length[$key], $this->pad_string, $this->pad_type);
		}
		return $row_contents;
	}
	
	public function shouldFileBeMoved() {
		$localPath = $this->export_directory . '/' . $this->filename;
		if (!empty(file_get_contents($localPath))) {
			return true;
		}
		$this->removeEmptyFile();
		return false;
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
				break;
		}
		
		$className = "Billrun_Generator_PaymentGateway_" . $generatorType;
		return $className;
	}
	
}

