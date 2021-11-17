<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Input processor for payment gateways files
 */
class Billrun_Processor_PaymentGateway_Custom extends Billrun_Processor_Updater {
	
	protected $configByType;
	protected $bills;
	protected $fileType;
	protected $receiverSource;
	protected $gatewayName;
	protected $headerRows;
	protected $trailerRows;
	protected $correlatedValue;
	protected $linkToInvoice = true;
        protected $informationArray = [];
        
        
	protected $billSavedFields = array();
	
	public function __construct($options) {
		$this->configByType = !empty($options[$options['type']]) ? $options[$options['type']] : array();
		$this->gatewayName = $options['name']; 
		$this->receiverSource = str_replace('_', '', ucwords($options['name'], '_')) . str_replace('_', '', ucwords($options['type'], '_'));
		$this->bills = Billrun_Factory::db()->billsCollection();
		$this->log = Billrun_Factory::db()->logCollection();
		$this->informationArray['payments_file_type'] = !empty($options['type']) ? $options['type'] : null;
		$this->informationArray['type'] = 'custom_payment_gateway';
		$this->informationArray['creation_type'] = new MongoDate();
		$this->informationArray['fileType'] = 'received';
		$this->informationArray['total_denied_amount'] = 0;
		$this->informationArray['total_confirmed_amount'] = 0;
		$this->informationArray['total_rejected_amount'] = 0;
		$this->informationArray['transactions']['confirmed'] = 0;
		$this->informationArray['transactions']['rejected'] = 0;
		$this->informationArray['transactions']['denied'] = 0;
		$this->informationArray['last_file'] = false;
	}

/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	protected function processLines() {
		$currentProcessor = current(array_filter($this->configByType, function($settingsByType) {
			return $settingsByType['file_type'] === $this->fileType;
		}));
		if (isset($currentProcessor['parser']) && $currentProcessor['parser'] != 'none') {
			$this->setParser($currentProcessor['parser']);
		} else {
                        $message = "Parser definition missing";
                        $this->informationArray['errors'][] = $message;
			throw new Exception($message);
		}
		if (!$this->mapProcessorFields($currentProcessor)) { // if missing mapping fields in conf
			return false;
		}
		$this->linkToInvoice = isset($currentProcessor['processor']['link_to_invoice']) ? $currentProcessor['processor']['link_to_invoice'] : $this->linkToInvoice;
		$headerStructure = isset($currentProcessor['parser']['header_structure']) ? $currentProcessor['parser']['header_structure'] : array();
		$dataStructure = isset($currentProcessor['parser']['data_structure']) ? $currentProcessor['parser']['data_structure'] : array();
		$parser = $this->getParser();
		$parser->setHeaderStructure($headerStructure);
		$parser->setDataStructure($dataStructure);
                try{
		$parser->parse($this->fileHandler);
                } catch(Exception $ex){
                    Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
                    Billrun_Factory::log()->log('Something went wrong while processing the file.', Zend_Log::ALERT);
                    return false;
                } 
		$this->headerRows = $parser->getHeaderRows();
		$this->trailerRows = $parser->getTrailerRows();
		$parsedData = $parser->getDataRows();
		$rowCount = 0;

		foreach ($parsedData as $line) {
                        $line = $this->formatLine($line,$dataStructure);
			$row = $this->getBillRunLine($line);
			if (!$row){
				return false;
			}
			$row['row_number'] = ++$rowCount;
			$this->addDataRow($row);
		}
		$this->data['header'] = array('header' => TRUE); //TODO
        $this->data['trailer'] = array('trailer' => TRUE); //TODO

		return true;
	}
        
	public function initProcessorFields($processor_fields, $processor) {
		$var_names = Billrun_Util::parseBillrunConventionToCamelCase($processor_fields);
		foreach ($var_names as $var_name => $config_name) {
			if (isset($processor['processor'][$config_name])) {
				$this->{$var_name} = is_array($processor['processor'][$config_name]) ? $processor['processor'][$config_name] : array(
					'source' => 'data',
					'field' => $processor['processor'][$config_name]
				);
			} else {
				$this->{$var_name} = null;
			}
		}
	}

	protected function formatLine($row, $dataStructure) {
		foreach ($dataStructure as $index => $paramObj) {
			if (isset($paramObj['decimals'])) {
				$value = intval($row[$paramObj['name']]);
				$row[$paramObj['name']] = (float) ($value / pow(10, $paramObj['decimals']));
			}
			if (isset($paramObj['substring'])) {
				if (!isset($paramObj['substring']['offset']) || !isset($paramObj['substring']['length'])) {
					$message = "Field name " . $paramObj['name'] . " config was defined incorrectly when generating file type " . $this->configByType['file_type'];
					$this->logFile->updateLogFileField('errors', $message);
					throw new Exception($message);
				}
				$row[$paramObj['name']] = substr($row[$paramObj['name']], $paramObj['substring']['offset'], $paramObj['substring']['length']);
			}
		}
		return $row;
	}

	protected function getBillRunLine($rawLine) {
		$row = $rawLine;
		$row['stamp'] = md5(serialize($row));
		return $row;
	}

	protected function updateData() {
		$data = $this->getData();
		$currentProcessor = current(array_filter($this->configByType, function($settingsByType) {
			return $settingsByType['file_type'] === $this->fileType;
		}));
		
		$fileStatus = isset($currentProcessor['file_status']) ? $currentProcessor['file_status'] : null;
		$fileConfCount = isset($currentProcessor['response_files_count']) ? $currentProcessor['response_files_count'] : null;
		$fileCorrelationObj = isset($currentProcessor['correlation']) ? $currentProcessor['correlation'] : null;
		if (!empty($fileStatus) && in_array($fileStatus, array('only_rejections', 'only_acceptance'))) {
			if (empty($fileConfCount) || empty($fileCorrelationObj)) {
				throw new Exception("Couldn't find file's correlation value, or number of expected response files.");
			}
			$this->updateLogCollection($fileCorrelationObj);
		}
                if(isset($currentProcessor['file_status']) && !empty($currentProcessor['file_status'])){
                if ($currentProcessor['file_status'] == 'only_rejections' || $currentProcessor['file_status'] == 'only_acceptance') {
                        $currentFileCount = $this->getCurrentFileCount() + 1;
                    $this->informationArray['file_count'] = $currentFileCount;
                        if (($currentFileCount) > $fileConfCount){
                        $message = 'Too many files were received for correlatedValue: ' . $this->correlatedValue . '. Only the first ' . $fileConfCount . ' files were updated in the Data Base.';
                        Billrun_Factory::log($message , Zend_Log::ALERT);
                        $this->informationArray['errors'] = $message;
                        return False;
                    }else{
                            if($currentFileCount == $fileConfCount){
                            $this->informationArray['last_file'] = true;
                            }
                        }
                    }
                }
				$this->informationArray = array_merge($this->informationArray, $this->getCustomPaymentGatewayFields());
		$this->updatePaymentsByRows($data, $currentProcessor);
		$this->informationArray['process_time'] = new MongoDate(time());
                $this->updateLogFile();
	}

	protected function getRowDateTime($dateStr) {
		$datetime = new DateTime();
		$date = $datetime->createFromFormat('ymdHis', $dateStr);
		return $date;
	}
	
	public function skipQueueCalculators() {
		return true;
	}

	protected function setPgFileType($fileType) {
		$this->fileType = $fileType;
	}
	
	protected function getCurrentFileCount() {
		if (empty($this->correlatedValue)) {
			throw new Exception("Missing correlated value");
		}
		$query = array(
			'related_request_file' => $this->correlatedValue,
			'process_time' => array('$exists' => true),
		);
		
		return $this->log->query($query)->cursor()->count();
	}
	
	protected function updateLeftPaymentsByFileStatus() {
		$currentProcessor = current(array_filter($this->configByType, function($settingsByType) {
			return $settingsByType['file_type'] === $this->fileType;
		}));
		if ($currentProcessor['file_status'] == 'only_rejections' || $currentProcessor['file_status'] == 'only_acceptance') {
		$currentFileCount = $this->getCurrentFileCount();
		$fileStatus = isset($currentProcessor['file_status']) ? $currentProcessor['file_status'] : null;
		$fileConfCount = isset($currentProcessor['response_files_count']) ? $currentProcessor['response_files_count'] : null;
		$fileCorrelationObj = isset($currentProcessor['correlation']) ? $currentProcessor['correlation'] : null;
		if (!empty($fileStatus) && in_array($fileStatus, array('only_rejections', 'only_acceptance'))) {
			if (empty($fileConfCount) || empty($fileCorrelationObj)) {
				throw new Exception('Missing file response definitions');
			}
		}
		$correlatedField =  $fileCorrelationObj['file_field'];
		if (!empty($fileConfCount) && !empty($currentFileCount) && $currentFileCount != $fileConfCount) {
			return;
		}
		$origFileStamp = $this->getOriginalFileStamp($correlatedField);
		$relevantBills = $this->getOrigFileBills($origFileStamp);
		foreach ($relevantBills as $bill) {
			if (!($bill instanceof Billrun_Bill)) {
				$bill = Billrun_Bill::getInstanceByData($bill);
			} 
			if ($fileStatus == 'only_rejections') {
				$customFields = $this->getCustomPaymentGatewayFields();
				$bill->setExtraFields($customFields, array_keys($customFields));
				$bill->markApproved('Completed');
				$bill->setPending(false);
				$bill->updateConfirmation();
				$bill->save();
                $this->informationArray['transactions']['confirmed']++;
				$billData = $bill->getRawData();
				if (isset($billData['left_to_pay']) && $billData['due']  > (0 + Billrun_Bill::precision)) {
					Billrun_Factory::dispatcher()->trigger('afterRefundSuccess', array($billData));
				}
				if (isset($billData['left']) && $billData['due'] < (0 - Billrun_Bill::precision)) {
					Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($billData));
				}
			} else if ($fileStatus == 'only_acceptance') {
				$billData = $bill->getRawData();
				$billData['method'] = isset($billData['payment_method']) ? $billData['payment_method'] : (isset($billData['method']) ? $billData['method'] : 'automatic');
				$billToReject = Billrun_Bill_Payment::getInstanceByData($billData);
				$customFields = $this->getCustomPaymentGatewayFields();
				$billToReject->setExtraFields($customFields, array_keys($customFields));
				Billrun_Factory::log('Rejecting transaction ' . $billToReject->getId(), Zend_Log::INFO);
				$rejection = $billToReject->getRejectionPayment(array('status' => 'acceptance_file'));
				$rejection->setConfirmationStatus(false);
				$rejection->save();
				$billToReject->markRejected();
				Billrun_Factory::dispatcher()->trigger('afterRejection', array($billToReject->getRawData()));
                $this->informationArray['transactions']['rejected']++;
				$this->informationArray['process_time'] = new MongoDate(time());
			}
		}
	}
	}
	
        protected function getOriginalFileStamp($correlatedField) {
		$query = array(
			$correlatedField => $this->correlatedValue,
		);
		$fileLog = $this->log->query($query)->cursor()->current();
		$logData = $fileLog->getRawData();
		return $logData['stamp'];
	}
	
	protected function updatePaymentsByRows($data, $currentProcessor) {
		$no_txid_counter = 0;
		$billSavedFieldsNames = $this->getBillSavedFieldsNames($currentProcessor['parser']);
		foreach ($data['data'] as $row) {
			if (isset($this->tranIdentifierField)) {
				//TODO : support multiple header/footer lines
				$txid_from_file = in_array($this->tranIdentifierField['source'], ['header', 'trailer']) ?  $this->{$this->tranIdentifierField['source'].'Rows'}[0][$this->tranIdentifierField['field']] : $row[$this->tranIdentifierField['field']];
				if (($txid_from_file === "") && (static::$type != 'payments')) {
					$no_txid_counter++;
					continue;
				}
			}
			//TODO : support multiple header/footer lines
			$txid_from_file = in_array($this->tranIdentifierField['source'], ['header', 'trailer']) ?  $this->{$this->tranIdentifierField['source'].'Rows'}[0][$this->tranIdentifierField['field']] : $row[$this->tranIdentifierField['field']];
			$bill = (static::$type != 'payments') ? Billrun_Bill_Payment::getInstanceByid($txid_from_file) : null;
			if (is_null($bill) && static::$type != 'payments') {
				Billrun_Factory::log('Unknown transaction ' . $txid_from_file . ' in file ' . $this->filePath, Zend_Log::ALERT);
				continue;
			}
			$this->billSavedFields = $this->getBillSavedFields($row, $billSavedFieldsNames);
			$this->updatePayments($row, $bill, $currentProcessor);
		}
		if ($no_txid_counter > 0) {
			Billrun_Factory::log()->log('In ' . $no_txid_counter . ' lines, ' . $txid_from_file . ' field is empty. No update was made for these lines.', Zend_Log::ALERT);
		}
	}

	protected function updateLogCollection($fileCorrelation) {
		$source = isset($fileCorrelation['source']) ? $fileCorrelation['source'] : null;
		$correlationField = isset($fileCorrelation['field']) ? $fileCorrelation['field'] : null;
		$logField = isset($fileCorrelation['file_field']) ? $fileCorrelation['file_field'] : null;
		if (empty($source) || empty($correlationField) || empty($logField)) {
			throw new Exception('Missing correlaction definitions');
		}
		$relevantRow = ($source == 'header') ? current($this->headerRows) : current($this->trailerRows); // TODO: support in more than one header/trailer
		$query = array(
			'stamp' => $this->getFileStamp()
		);
		
		$update = array (
			'$set' => array(
				'related_request_file' => $relevantRow[$correlationField],
				'response_file' => true,
			)
		);
		$this->log->update($query, $update);
		$this->correlatedValue = $relevantRow[$correlationField];
	}
	
	protected function getOrigFileBills($fileStamp) {
		$nonRejectedOrCanceled = Billrun_Bill::getNotRejectedOrCancelledQuery();
		$query = array(
			'generated_pg_file_log' => $fileStamp,
			'confirmation_time' => array('$exists' => false)
		);
		$query = array_merge($query, $nonRejectedOrCanceled);
		return $this->bills->query($query)->cursor();
	}

        protected function updateLogFile(){
            $current_stamp = $this->getStamp();
            $log = Billrun_Factory::db()->logCollection();
            if ($current_stamp instanceof Mongodloid_Entity || $current_stamp instanceof Mongodloid_Id) {
                $resource = $log->findOne($current_stamp);
                $entityData = $resource->getRawData();
                $data = array_merge($entityData, $this->informationArray);
                $resource->setRawData($data);
                $log->save($resource);
            }
        }
		
	protected function getBillSavedFieldsNames($parserDef) {
		$savedFieldsNames = array();
		$dataStructure = $parserDef['data_structure'];
		foreach ($dataStructure as $field) {
			if (empty($field['save_to_bill'])) {
				continue;
			}
			$savedFieldsNames[] = $field['name'];
		}
		
		return $savedFieldsNames;
	}
		
	protected function getBillSavedFields($row, $fieldNames) {
		$savedFields = array();
		foreach ($row as $field => $fieldValue) {
			if (!in_array($field, $fieldNames)) {
				continue;
			}
			$savedFields[$field] = $fieldValue;
		}
		
		return $savedFields;
	}
	
	public function getCustomPaymentGatewayFields () {
		return [
				'cpg_name' => [!empty($this->gatewayName) ? $this->gatewayName : ""],
				'cpg_type' => [!empty($type = $this->getType()) ? $type : ""], 
				'cpg_file_type' => [!empty($this->fileType) ? $this->fileType : ""] ];
        }
}
