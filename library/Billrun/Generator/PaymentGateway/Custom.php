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

	public $now;
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
	protected $file_transactions_counter = 0;
    protected $gatewayLogName;
    protected $fileGenerator;
	protected $billSavedFields = array();
	protected $mandatory_fields_per_entity = [];
    
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
		$this->now = time();
    }

    public function generate() {
        $fileName = $this->getFilename();
        $this->fileGenerator->setFileName($fileName);
        $this->fileGenerator->setFilePath($this->localDir);
        $this->fileGenerator->setHeaderRows($this->headers);
        $this->fileGenerator->setDataRows($this->data);
        $this->fileGenerator->setTrailerRows($this->trailers);
        $this->fileGenerator->generate();
        $this->logFile->updateLogFileField('transactions', $this->fileGenerator->getTransactionsCounter());
		$this->logFile->updateLogFileField('process_time', new Mongodloid_Date($this->now));
        $this->logFile->saveLogFileFields();
    }

	protected function setFileMandatoryFields() {
		$dataStructure = $this->configByType['generator']['data_structure'];
		foreach($dataStructure as $dataField) {
			if (isset($dataField['linked_entity'])) {
				$this->mandatory_fields_per_entity[$dataField['linked_entity']['entity']][] = $dataField['linked_entity']['field_name'];
            }
		}
	}
	
	protected function validateMandatoryFieldsExistence($entity, $entity_type = 'account') {
		$data = $entity->getRawData();
		if(isset($this->mandatory_fields_per_entity[$entity_type])) {
			foreach($this->mandatory_fields_per_entity[$entity_type] as $field_name) {
				if(isset($data[$field_name])) {
					continue;
				} else {
					return false;
				}
			}
		}
		return true;
	}

	protected function getDataLine($params) {
        $dataLine = array();
        $this->transactionsTotalAmount += $params['amount'];
        $dataStructure = $this->configByType['generator']['data_structure'];
		$this->billSavedFields = array();
        foreach ($dataStructure as $dataField) {
            try{
            if (!isset($dataField['path'])) {
                $message = "Exporter " . $this->configByType['file_type'] . " data structure is missing a path";
                Billrun_Factory::log($message, Zend_Log::ERR);
                $this->logFile->updateLogFileField('warnings', $message);
                continue;
            }
            if (isset($dataField['predefined_values']) && $dataField['predefined_values'] == 'now') {
                $dateFormat = isset($dataField['format']) ? $dataField['format'] : Billrun_Base::base_datetimeformat;
                $dataLine[$dataField['path']] = date($dateFormat, $this->now);
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
            $warningMessages = [];
            $dataLine[$dataField['path']] = Billrun_Util::formattingValue($dataField, $dataLine[$dataField['path']], $warningMessages);
            foreach ($warningMessages as $warningMessage){
                $this->logFile->updateLogFileField('warnings', $warningMessage);
            }
            if ((isset($dataField['type']) && $dataField['type'] == 'autoinc')) {
                    $dataLine[$dataField['path']] = $this->getAutoincValue($dataField, 'cpf_generator_' . $this->getFilename());
            }
            $attributes = $this->getLineAttributes($dataField);
            if (!isset($dataLine[$dataField['path']])) {
                $configObj = $dataField['name'];
                $message = "Field name " . $configObj . " config was defined incorrectly when generating file type " . $this->configByType['file_type'];
                $this->logFile->updateLogFileField('errors', $message);
                throw new Exception($message);
            }
			if (!empty($dataField['save_to_bill'])) {
				$this->billSavedFields[$dataField['name']] = $dataLine[$dataField['path']];
			}
			$dataLine[$dataField['path']] = $this->prepareLineForGenerate($dataLine[$dataField['path']], $dataField, $attributes);
            } catch(Exception $ex){
                Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
                continue;
        }
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
                $account->loadAccountForQuery(array('aid' => $params['aid']));
                $accountData = $account->getCustomerData();
                if (is_null(Billrun_Util::getIn($accountData, $field))) {
                    $message = "Field name $field does not exist under entity " . $entity;
                    Billrun_Factory::log($message, Zend_Log::ERR);
                    $this->logFile->updateLogFileField('errors', $message);
                }
                return Billrun_Util::getIn($accountData, $field);

            case 'payment_request':
                if (!isset($params[$field])) {
                    $message = 'Unknown field in payment_request';
                    $this->logFile->updateLogFileField('errors', $message);
                    throw new Exception($message);
                }

                return $params[$field];
            default:
                $message = "Unknown entity: " . $entity . ", as 'linked entity' in the config.";
                $this->logFile->updateLogFileField('errors', $message);
                Billrun_Factory::log($message, Zend_Log::ERR);
        }
    }

    protected function buildGeneratorOptions() {
        $options['data'] = $this->data;
        $options['headers'] = $this->headers;
        $options['trailers'] = $this->trailers;
        $options['type'] = $this->configByType['generator']['type'];
        $options['configByType'] = $this->configByType;
        if (isset($this->configByType['generator']['separator'])) {
            $options['delimiter'] = $this->configByType['generator']['separator'];
        }
        $options['file_type'] = $this->configByType['file_type'];
        $options['local_dir'] = $this->localDir;
		$options['row_separator'] = Billrun_Util::getIn($this->configByType['generator'], 'row_separator', 'line_break');
        return $options;
    }

    protected function getGeneratorClassName() {
        if (!isset($this->configByType['generator']['type'])) {
            $message = 'Missing generator type for ' . $this->configByType['file_type'];
            $this->logFile->updateLogFileField('errors', $message);
            throw new Exception($message);
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
                $message = 'Unknown generator type for ' . $this->configByType['file_type'];
                $this->logFile->updateLogFileField('errors', $message);
                throw new Exception($message);
        }

        $className = "Billrun_Generator_PaymentGateway_" . $generatorType;
        return $className;
    }

    protected function getFilename() {
        if (!empty($this->fileName)) {
            return $this->fileName;
        }
        $translations = array();
        if(is_array($this->fileNameParams)){
            foreach ($this->fileNameParams as $paramObj) {
                $warningMessages = [];
                $translations[$paramObj['param']] = Billrun_util::formattingValue($paramObj, $this->getTranslationValue($paramObj), $warningMessages);
                foreach ($warningMessages as $warningMessage){
                    $this->logFile->updateLogFileField('warnings', $warningMessage);
                }              
            }
        }
        $this->fileName = Billrun_Util::translateTemplateValue($this->fileNameStructure, $translations, null, true);
        return $this->fileName;
    }

    protected function prepareLineForGenerate($lineValue, $addedData, $attributes) {
        $newLine = array();
		$newLine['value'] = $lineValue;
        $newLine['name'] = $addedData['name'];
        if (count($attributes) > 0) {
            for ($i = 0; $i < count($attributes); $i++) {
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
            Billrun_Factory::log()->log('Empty file ' . $localPath . ' was removed successfully', Zend_Log::INFO);
            return;
        }
        Billrun_Factory::log()->log('Failed removing empty file ' . $localPath, Zend_Log::WARN);
    }

    public function move() {
        $exportDetails = $this->configByType['export'];
        $connection = Billrun_Factory::paymentGatewayConnection($exportDetails);
        $fileName = $this->getFilename();
		$res = $connection->export($fileName);
		if (!$res) {
			Billrun_Factory::log()->log('Failed moving file ' . $fileName, Zend_Log::ALERT);
		}
	}

    protected function getTranslationValue($paramObj) {
        if (!isset($paramObj['type']) || !isset($paramObj['value'])) {
            $message = "Missing filename params definitions for file type " . $this->configByType['file_type'];
            Billrun_Factory::log($message, Zend_Log::ERR);
            $this->logFile->updateLogFileField('errors', $message);
        }
        switch ($paramObj['type']) {
            case 'date':
                return ($paramObj['value'] == 'now') ? $this->now : strtotime($paramObj['value']);
            case 'autoinc':
		return $this->getAutoincValue($paramObj, 'transactions_request');
            default:
                $message = "Unsupported filename_params type for file type " . $this->configByType['file_type'];
                Billrun_Factory::log($message, Zend_Log::ERR);
                $this->logFile->updateLogFileField('errors', $message);
                break;
        }
    }

    protected function buildLineFromStructure($structure) {
        $line = array();
        foreach ($structure as $field) {
            if (!isset($field['path'])) {
                $message = "Exporter " . $this->configByType['file_type'] . " header/trailer structure is missing a path";
                $this->logFile->updateLogFileField('errors', $message);
                Billrun_Factory::log($message, Zend_Log::ERR);
                continue;
            }
            if (isset($field['predefined_values']) && $field['predefined_values'] == 'transactions_num') {
                $line[$field['path']] = $this->file_transactions_counter;
            }
            if (isset($field['predefined_values']) && $field['predefined_values'] == 'now') {
                $dateFormat = isset($field['format']) ? $field['format'] : Billrun_Base::base_datetimeformat;
                $line[$field['path']] = date($dateFormat, $this->now);
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
            $warningMessages = [];
            $line[$field['path']] = Billrun_Util::formattingValue($field, $line[$field['path']], $warningMessages);
            foreach ($warningMessages as $warningMessage){
                $this->logFile->updateLogFileField('warnings', $warningMessage);
            }
			$attributes = $this->getLineAttributes($field);
            if (!isset($line[$field['path']])) {
                $configObj = $field['name'];
                $message = "Field name " . $configObj . " config was defined incorrectly when generating file type " . $this->configByType['file_type'];
                $this->logFile->updateLogFileField('errors', $message);
                throw new Exception($message);
            }
            $line[$field['path']] = $this->prepareLineForGenerate($line[$field['path']], $field, $attributes);
        }
        if ($this->configByType['generator']['type'] == 'fixed' || $this->configByType['generator']['type'] == 'separator') {
            ksort($line);
        }
        return $line;
    }

	/**
	 * Function to create the cpg log file
	 */
	protected function createLogFile() {
        $logOptions = $this->chargeOptions;
		$logOptions['source'] = "custom_payment_files";
		Billrun_Factory::log("Creating log file object", Zend_Log::DEBUG);
        $this->logFile = new Billrun_LogFile_CustomPaymentGateway($logOptions);
		$this->logFile->save();
	}
	
	/**
	 * Function to initialize the created log file, only if it was created successfully.
	 */
	protected function initLogFile() {
        $this->logFile->setSequenceNumber();
        $this->logFile->setFileName($this->getFilename());
        $this->generatedLogFileStamp = $this->logFile->getStamp();
		Billrun_Factory::log("Generated log file stamp that was saved: " . $this->generatedLogFileStamp, Zend_Log::DEBUG);
		Billrun_Factory::log("Saving initialized log object to db", Zend_Log::DEBUG);
        $this->logFile->save();
    }
    
    /**
     * Function returns line's attributes, if exists
     * @param type $field
     * @return array $attributes.
     */
    protected function getLineAttributes($field){
        if(isset($field['attributes'])){
            return $field['attributes'];
        } else {
            return [];
        }
    }

	protected function getAutoincValue($params, $action = 'transactions_request') {
		if (!isset($params['min_value']) || !isset($params['max_value'])) {
			$message = "Missing min/max values in " . $params['name'] . " params definitions for file type " . $this->configByType['file_type'];
			Billrun_Factory::log($message, Zend_Log::ERR);
			$this->logFile->updateLogFileField('errors', $message);
			return false;
		}
		$minValue = $params['min_value'];
		$maxValue = $params['max_value'];
		$dateGroup = isset($params['date_group']) ? $params['date_group'] : Billrun_Base::base_datetimeformat;
		$dateValue = ($params['value'] == 'now') ? time() : strtotime($params['value']);
		$date = date($dateGroup, $dateValue);
		$fakeCollectionName = '$pgf' . $this->gatewayName . '_' . $action . '_' . $this->configByType['file_type'] . '_' . $date;
		$seq = Billrun_Factory::db()->countersCollection()->createAutoInc(array(), $minValue, $fakeCollectionName);
		if ($seq > $maxValue) {
			$message = "Sequence exceeded max value when generating file for file type " . $this->configByType['file_type'];
			$this->logFile->updateLogFileField('errors', $message);
			return false;
		}
		return $seq;
	}

}
