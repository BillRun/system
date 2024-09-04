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
	
	public $now;
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
	protected $ignoreDuplicates = false;
        
        
	protected $billSavedFields = array();
	
	public function __construct($options) {
		$this->configByType = !empty($options[$options['type']]) ? $options[$options['type']] : array();
		$this->gatewayName = $options['name']; 
		$this->receiverSource = str_replace('_', '', ucwords($options['name'], '_')) . str_replace('_', '', ucwords($options['type'], '_'));
		$this->setPgFileType($options['file_type']);
		$this->bills = Billrun_Factory::db()->billsCollection();
		$this->log = Billrun_Factory::db()->logCollection();
		$this->informationArray['payments_file_type'] = !empty($options['type']) ? $options['type'] : null;
		$this->informationArray['type'] = 'custom_payment_gateway';
		$this->informationArray['creation_time'] = new Mongodloid_Date();
		$this->resetInformationArray();
		$this->now = time();
	}

/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	protected function processLines() {
		Billrun_Factory::log("Processing custom payments file' lines", Zend_Log::DEBUG);
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
		Billrun_Factory::log("Checking 'ignore duplicates' and 'link to invoice' configuration" . $this->ignoreDuplicates, Zend_Log::DEBUG);
		$this->ignoreDuplicates = isset($currentProcessor['ignore_duplicates']) ? $currentProcessor['ignore_duplicates'] : $this->ignoreDuplicates;
		$this->linkToInvoice = $this->getLinkToInvoiceValue($currentProcessor['processor']);
		Billrun_Factory::log("Pulling header & data structure", Zend_Log::DEBUG);
		$headerStructure = isset($currentProcessor['parser']['header_structure']) ? $currentProcessor['parser']['header_structure'] : array();
		$dataStructure = isset($currentProcessor['parser']['data_structure']) ? $currentProcessor['parser']['data_structure'] : array();
		Billrun_Factory::log("Parsing data...", Zend_Log::DEBUG);
		$parser = $this->getParser();
		$parser->resetData();
		$parser->setHeaderStructure($headerStructure);
		$parser->setDataStructure($dataStructure);
		try{
			$parser->parse($this->fileHandler);
		} catch(Exception $ex) {
			Billrun_Factory::log()->log($ex->getMessage(), Zend_Log::ERR);
			Billrun_Factory::log()->log('Something went wrong while processing the file', Zend_Log::ALERT);
			return false;
		}
		Billrun_Factory::log("Finished parsing", Zend_Log::DEBUG);
		$this->headerRows = $parser->getHeaderRows();
		$this->trailerRows = $parser->getTrailerRows();
		$parsedData = $parser->getDataRows();
		$rowCount = 0;
		Billrun_Factory::log("Formating parsed data, and adding stamp field", Zend_Log::DEBUG);
		foreach ($parsedData as $index => $line) {
            $line = $this->formatLine($line,$dataStructure);
			$row = $this->getBillRunLine($line, $index);
			if (!$row){
				return false;
			}
			$row['row_number'] = ++$rowCount;
			$this->addDataRow($row);
		}
		$this->data['header'] = array('header' => TRUE); //TODO
        $this->data['trailer'] = array('trailer' => TRUE); //TODO
		$this->resetInformationArray();
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
				if (isset($paramObj['value_mult'])) {
					$row[$paramObj['name']] = floatval($row[$paramObj['name']]) * floatval($paramObj['value_mult']);
				}
		if(isset($paramObj['decimals'])){
				$value = intval($row[$paramObj['name']]);
			$row[$paramObj['name']] = (float)($value/pow(10,$paramObj['decimals']));
			}
			if (isset($paramObj['type']) && $paramObj['type'] == "date") {
				if (!isset($paramObj['format'])) {
					$message = $paramObj['name'] . ' field was defined as date field, but without date format. Default BillRun format was taken';
					Billrun_Factory::log($message, Zend_Log::WARN);
					$this->informationArray['warnings'][] = $message;
					$paramObj['format'] = Billrun_Base::base_datetimeformat;
				}
				$row[$paramObj['name']] = Billrun_Processor_Util::getRowDateTime($row, $paramObj['name'], $paramObj['format'])->format(Billrun_Base::base_datetimeformat);
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

	protected function getBillRunLine($rawLine, $line_index) {
		$row = $this->ignoreDuplicates ? $rawLine : array_merge($rawLine, ['parser_record_number' => $line_index]);
		$row['stamp'] = md5(serialize($row));
		return $row;
	}

	protected function updateData() {
		Billrun_Factory::log("Updating file' data...", Zend_Log::DEBUG);
		$data = $this->getData();
		$currentProcessor = current(array_filter($this->configByType, function($settingsByType) {
			return $settingsByType['file_type'] === $this->fileType;
		}));
		
		$fileStatus = isset($currentProcessor['file_status']) ? $currentProcessor['file_status'] : null;
		$fileConfCount = isset($currentProcessor['response_files_count']) ? $currentProcessor['response_files_count'] : null;
		$fileCorrelationObj = isset($currentProcessor['correlation']) ? $currentProcessor['correlation'] : null;
		if (!empty($fileStatus) && in_array($fileStatus, array('only_rejections', 'only_acceptance'))) {
			if (empty($fileConfCount) || empty($fileCorrelationObj)) {
				throw new Exception("Couldn't find file's correlation value, or number of expected response files");
			}
			$this->updateLogCollection($fileCorrelationObj);
		}
        if(isset($currentProcessor['file_status']) && !empty($currentProcessor['file_status'])){
			Billrun_Factory::log("File status is " . $currentProcessor['file_status'], Zend_Log::DEBUG);
			if ($currentProcessor['file_status'] == 'only_rejections' || $currentProcessor['file_status'] == 'only_acceptance') {
				$currentFileCount = $this->getCurrentFileCount() + 1;
				Billrun_Factory::log("Current file count is " . $currentFileCount . ", out of " . $fileConfCount, Zend_Log::DEBUG);
				$this->informationArray['file_count'] = $currentFileCount;
				if (($currentFileCount) > $fileConfCount) {
					$message = 'Too many files were received for correlatedValue: ' . $this->correlatedValue . '. Only the first ' . $fileConfCount . ' files were updated in the Data Base';
					Billrun_Factory::log($message , Zend_Log::ALERT);
					$this->informationArray['errors'] = $message;
					return False;
				} else {
					if($currentFileCount == $fileConfCount) {
						$this->informationArray['last_file'] = true;
					}
				}
			}
		}
		$this->informationArray = array_merge($this->informationArray, $this->getCustomPaymentGatewayFields());
		Billrun_Factory::log("Updating records by rows...", Zend_Log::DEBUG);
		$this->updatePaymentsByRows($data, $currentProcessor);
		$this->informationArray['process_time'] = new Mongodloid_Date($this->now);
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
		Billrun_Factory::log("Updating lef payments by file status", Zend_Log::DEBUG);
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
		Billrun_Factory::log("Using " . $correlatedField . " as correlated field", Zend_Log::DEBUG);
		if (!empty($fileConfCount) && !empty($currentFileCount) && $currentFileCount != $fileConfCount) {
			Billrun_Factory::log("Processed file is not the last one, continue", Zend_Log::DEBUG);
			return;
		}
		$origFileStamp = $this->getOriginalFileStamp($correlatedField);
		Billrun_Factory::log("Found original file' stamp : " . $origFileStamp . ". Pulling relevant bills...", Zend_Log::DEBUG);
		$relevantBills = $this->getOrigFileBills($origFileStamp);
		Billrun_Factory::log("Updating " . count($relevantBills) . " relevant bills", Zend_Log::DEBUG);
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
				$this->informationArray['process_time'] = new Mongodloid_Date($this->now);
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
		foreach ($data['data'] as $index => $row) {
			$txid_from_file = "";
			if (isset($this->tranIdentifierField)) {
				//TODO : support multiple header/footer lines
				$txid_from_file = in_array($this->tranIdentifierField['source'], ['header', 'trailer']) ? $this->{$this->tranIdentifierField['source'] . 'Rows'}[0][$this->tranIdentifierField['field']] : $row[$this->tranIdentifierField['field']];
				if (($txid_from_file === "") && (static::$type != 'payments')) {
					$no_txid_counter++;
					continue;
				}
				Billrun_Factory::log("Searching for bill with txid: " . $row[$this->tranIdentifierField] , Zend_Log::DEBUG);
				$bill = (static::$type != 'payments') ? Billrun_Bill_Payment::getInstanceByid($txid_from_file) : null;
			} else if (!is_null($this->tranIdentifierFields) && (static::$type != 'payments')) {
				Billrun_Factory::log("Searching for bills using configured query, for line number " . $row['row_number'] , Zend_Log::DEBUG);
				$query = $this->processIdentifierFields($row);
				$bills = Billrun_Bill_Payment::queryPayments($query);
				if (!empty($bills)) {
					Billrun_Factory::log("Found " . count($bills) . " relevant bills" , Zend_Log::DEBUG);
					if (count($bills) > 1) {
						Billrun_Factory::log("Found more than one bill, taking 1 or none, according to the configuration" , Zend_Log::DEBUG);
						$bill = $this->take_first ? Billrun_Bill_Payment::getInstanceByData(current(Billrun_Bill_Payment::queryPayments($query))) : null;
					} else {
						$bill = Billrun_Bill_Payment::getInstanceByData(current(Billrun_Bill_Payment::queryPayments($query)));
					}
				}
			}
			if (is_null($bill) && static::$type != 'payments') {
				Billrun_Factory::log('Unknown transaction ' . $txid_from_file . ' in file ' . $this->filePath, Zend_Log::ALERT);
				continue;
			}
			$this->billSavedFields = $this->getBillSavedFields($row, $billSavedFieldsNames);
			$this->updatePayments($row, $bill, $currentProcessor);
		}
		if ($no_txid_counter > 0) {
			Billrun_Factory::log()->log('In ' . $no_txid_counter . ' lines, ' . $txid_from_file . ' field is empty. No update was made for these lines', Zend_Log::ALERT);
		}
	}

	public function processIdentifierFields($row) {
		$res = [];
		foreach($this->tranIdentifierFields as $field_conf) {
			$row_val = Billrun_Util::getIn($row, $field_conf['file_field'], "");
			$res[$field_conf['field']] = [$field_conf['op'] => ($field_conf['type'] == 'int') ? intval($row_val) : (($field_conf['type'] == 'float') ? floatval($row_val) : $row_val)];
		}
		$res['generated_pg_file_log'] = ['$exists' => true];
		return $res;
	}

	protected function updateLogCollection($fileCorrelation) {
		Billrun_Factory::log("Updating log collection in 'updateLogCollection' function", Zend_Log::DEBUG);
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
		Billrun_Factory::log("Adding request file identifier to response file' log", Zend_Log::DEBUG);
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

	protected function updateLogFile() {
		Billrun_Factory::log("Updating log collection in 'updateLogFile' function", Zend_Log::DEBUG);
		$current_stamp = $this->getStamp();
		$log = Billrun_Factory::db()->logCollection()->setReadPreference('RP_PRIMARY');
		if ($current_stamp instanceof Mongodloid_Entity || $current_stamp instanceof Mongodloid_Id) {
			Billrun_Factory::log("Updating log - id : " . $current_stamp, Zend_Log::DEBUG);
			$resource = $log->findOne($current_stamp);
			if (!empty($resource)) {
				Billrun_Factory::log("Found relevant log object, pulling log' data", Zend_Log::DEBUG);
			}
			$entityData = $resource->getRawData();
			$data = array_merge($entityData, $this->informationArray);
			Billrun_Factory::log("Setting new information", Zend_Log::DEBUG);
			$resource->setRawData($data);
			Billrun_Factory::log("Saving updated log object", Zend_Log::DEBUG);
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
	
	public function getCustomPaymentGatewayFields($row = null) {
		$res = [
			'cpg_name' => [!empty($this->gatewayName) ? $this->gatewayName : ""],
			'cpg_type' => [!empty($type = $this->getType()) ? $type : ""],
				'cpg_file_type' => [!empty($this->fileType) ? $this->fileType : ""],
				'file' => trim($this->filename, DIRECTORY_SEPARATOR)
		];
		if (!is_null($row) && isset($row['parser_record_number'])) {
			$res['record_number'] = $row['parser_record_number'];
		}
		return $res;
	}

	public function getPaymentUrt($row) {
		$date = in_array($this->dateField['source'], ['header', 'trailer']) ? $this->{$this->dateField['source'] . 'Rows'}[$this->dateField['field']] : $row[$this->dateField['field']];
		if (!empty($date)) {
			return $date;
		} else {
			$message = "Couldn't find date field: " . $this->dateField['field'] . " in the relevant " . $this->dateField['source'] . " row, Current time was taken";
			$this->informationArray['warnings'][] = $message;
			Billrun_Factory::log()->log($message, Zend_Log::WARN);
			return date(Billrun_Base::base_datetimeformat, time());
		}
	}
	
	public function getLinkToInvoiceValue($processor) {
		if(isset($processor['identifier_field']) && is_array($processor['identifier_field'])){
			$this->linkToInvoice = (($processor['identifier_field']['field'] === 'invoice_id') && (isset($processor['identifier_field']['link_to_invoice']))) ? $processor['identifier_field']['link_to_invoice'] : $this->linkToInvoice;
		}
	}
	
	public function handleLogMessages($message, $level, $type) {
		Billrun_Factory::log($message, $level);
		$this->informationArray[$type][] = $message;
	}

	protected function resetInformationArray() {
		$this->informationArray['fileType'] = 'received';
		$this->informationArray['total_denied_amount'] = 0;
		$this->informationArray['total_confirmed_amount'] = 0;
		$this->informationArray['total_rejected_amount'] = 0;
		$this->informationArray['transactions']['confirmed'] = 0;
		$this->informationArray['transactions']['rejected'] = 0;
		$this->informationArray['transactions']['denied'] = 0;
		$this->informationArray['last_file'] = false;
		$this->informationArray['errors'] = [];
		$this->informationArray['warnings'] = [];
		$this->informationArray['info'] = [];
	}

	protected function getLogFileQuery($adoptThreshold) {
		$query = parent::getLogFileQuery($adoptThreshold);
		$query['pg_file_type'] = $this->fileType;
		return $query;
	}
}
