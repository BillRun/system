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
	protected $trailers = array();
	protected $localDir;
	protected $logFile;
	protected $fileName;
	protected $transactionsTotalAmount = 0;
	protected $gatewayLogName;

	public function __construct($options) {
		if (!isset($options['file_type'])) {
			throw new Exception('Missing file type');
		}
		$fileType = $options['file_type'];
		$configByType = !empty($options[$options['type']]) ? $options[$options['type']] : array();
		$this->configByType = current(array_filter($configByType, function($settingsByType) use ($fileType) {
			return $settingsByType['file_type'] === $fileType;
		}));
		$this->gatewayLogName = str_replace('_', '', ucwords($options['name'], '_'));
		$this->gatewayName = $options['name'];
		$this->bills = Billrun_Factory::db()->billsCollection();
	}

	public function generate() {
		$className = $this->getGeneratorClassName();
		$generatorOptions = $this->buildGeneratorOptions();
		$generator = new $className($generatorOptions);
		$generator->generate();
	}
	
	protected function getDataLine($params) {
		$dataLine = array();
		$this->transactionsTotalAmount += $params['amount'];
		$dataStructure = $this->configByType['generator']['data_structure'];
		foreach ($dataStructure as $dataField) {
			if (!isset($dataField['path'])) {
				Billrun_Factory::log("Exporter " . $this->configByType['file_type'] . " data structure is missing a path", Zend_Log::DEBUG);
				continue;
			}			
			if (isset($dataField['predefined_values']) && $dataField['predefined_values'] == 'now') {
				$dateFormat = isset($dataField['format']) ? $dataField['format'] : Billrun_Base::base_datetimeformat;
				$dataLine[$dataField['path']] = date($dateFormat,  time());
			}
			if (isset($dataField['hard_coded_value'])) {
				$dataLine[$dataField['path']] = $dataField['hard_coded_value'];
			}
			if (isset($dataField['linked_entity'])) {
				$dataLine[$dataField['path']] = $this->getLinkedEntityData($dataField['linked_entity']['entity'], $params, $dataField['linked_entity']['field_name']);
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
					Billrun_Factory::log("Couldn't convert date string when generating file type " . $this->configByType['file_type'], Zend_Log::NOTICE);
				}
			}
                        if(isset($dataField['attributes'])){
                            for($i = 0; $i < count($dataField['attributes']); $i++){
                                $attributes[] = $dataField['attributes'][$i];
                            }                          
                        }else{
                            $attributes = [];
                        }
			if (!isset($dataLine[$dataField['path']])) {
				$configObj = $dataField['name'];
				throw new Exception("Field name " . $configObj . " config was defined incorrectly when generating file type " . $this->configByType['file_type']);
			}
			$dataLine[$dataField['path']] = $this->prepareLineForGenerate($dataLine[$dataField['path']], $dataField, $attributes);
		}

		if ($this->configByType['generator']['type'] == 'fixed' || $this->configByType['generator']['type'] == 'separator') {
			ksort($dataLine);
		}
		return $dataLine;
	}
	
	protected function getHeaderLine() {
		$headerStructure = $this->configByType['generator']['header_structure'];
		return $this->buildLineFromStructure($headerStructure);
	}
	
	protected function getTrailerLine() {
		$trailerStructure = $this->configByType['generator']['trailer_structure'];
		return $this->buildLineFromStructure($trailerStructure);
	}

	protected function getLinkedEntityData($entity, $params, $field) {
		switch ($entity) {
			case 'account':
				 if (!isset($params['aid'])) {
					 throw new Exception('Missing account id');
				}
				$account = Billrun_Factory::account();
				$account->load(array('aid' => $params['aid']));
				$accountData = $account->getCustomerData();
				if (!isset($accountData[$field])) {
					Billrun_Factory::log("Field name $field does not exists under entity " . $entity, Zend_Log::DEBUG);
				}
				return $accountData[$field];

			case 'payment_request':
				if (!isset($params[$field])) {
					 throw new Exception('Unknown field in payment_request');
				}
				
				return $params[$field];
			default:
				Billrun_Factory::log("Unknown entity: " . $entity, Zend_Log::DEBUG);
		}
	}
	
	protected function buildGeneratorOptions() {
		$options['data'] = $this->data;
		$options['headers'] = $this->headers;
		$options['trailers'] = $this->trailers;
		$options['type'] = $this->configByType['generator']['type'];
		if (isset($this->configByType['generator']['separator'])) {
			$options['delimiter'] = $this->configByType['generator']['separator'];
		}
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
	
	protected function prepareLineForGenerate($lineValue, $addedData, $attributes) {
		$newLine = array();
		$newLine['value'] = isset($addedData['number_format']['decimals']) && is_numeric($lineValue) ? number_format($lineValue, $addedData['number_format']['decimals']) : $lineValue;
		$newLine['name'] = $addedData['name'];
                if(count($attributes) > 0){
                    for($i = 0; $i<count($attributes); $i++){
                        $newLine['attributes'][] = $attributes[$i];
                    }
                }
		if (isset($addedData['padding'])) {
			$newLine['padding'] = $addedData['padding'];
		}
		return $newLine;
	}
	
	public function shouldFileBeMoved() {
		$localPath = $this->localDir . '/' . $this->getFilename();
		if (file_exists($localPath) && !empty(file_get_contents($localPath))) {
			return true;
		}
		if (file_exists($localPath)) {
			Billrun_Factory::log("Removing empty generated file", Zend_Log::DEBUG);
			$this->removeEmptyFile();
		}
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
				$fakeCollectionName = '$pgf' . $this->gatewayName . '_' . $action . '_' . $this->configByType['file_type'] . '_' . $date;
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
	
	protected function buildLineFromStructure($structure) {
		$line = array();
		foreach ($structure as $field) {
			if (!isset($field['path'])) {
				Billrun_Factory::log("Exporter " . $this->configByType['file_type'] . " header/trailer structure is missing a path", Zend_Log::DEBUG);
				continue;
			}
			if (isset($field['predefined_values']) && $field['predefined_values'] == 'transactions_num') {
				$line[$field['path']] = count($this->customers);
			}
			if (isset($field['predefined_values']) && $field['predefined_values'] == 'now') {
				$dateFormat = isset($field['format']) ? $field['format'] : Billrun_Base::base_datetimeformat;
				$line[$field['path']] = date($dateFormat,  time());
			}
			if (isset($field['predefined_values']) && $field['predefined_values'] == 'transactions_amount') {
				$line[$field['path']] = $this->transactionsTotalAmount;
			}
			if (isset($field['hard_coded_value'])) {
				$line[$field['path']] = $field['hard_coded_value'];
			}
			if (isset($field['parameter_name‎']) && in_array($field['parameter_name‎'], $this->extraParamsNames) && isset($this->options[$field['parameter_name‎']])) {
				$line[$field['path']] = $this->options[$field['parameter_name‎']];
			}	
			if ((isset($field['type']) && $field['type'] == 'date') && (!isset($field['predefined_values']) && $field['predefined_values'] !== 'now')) {
				$dateFormat = isset($field['format']) ? $field['format'] : Billrun_Base::base_datetimeformat;
				$date = strtotime($line[$field['path']]);
				if ($date) {
					$line[$field['path']] = date($dateFormat, $date);
				} else {
					Billrun_Factory::log("Couldn't convert date string when generating file type " . $this->configByType['file_type'], Zend_Log::NOTICE);
				}
			}	
			if (!isset($line[$field['path']])) {
				$configObj = $field['name'];
				throw new Exception("Field name " . $configObj . " config was defined incorrectly when generating file type " . $this->configByType['file_type']);
			}
                        if(isset($dataField['attributes'])){
                            for($i = 0; $i < count($dataField['attributes']); $i++){
                                $attributes[] = $dataField['attributes'][$i];
                            }                          
                        }else{
                            $attributes = [];
                        }
			$line[$field['path']] = $this->prepareLineForGenerate($line[$field['path']], $field, $attributes);
		}
		if ($this->configByType['generator']['type'] == 'fixed' || $this->configByType['generator']['type'] == 'separator') {
			ksort($line);
		}
		return $line;
	}
	
}

