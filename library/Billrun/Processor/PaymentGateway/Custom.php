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

	public function __construct($options) {
		$this->configByType = !empty($options[$options['type']]) ? $options[$options['type']] : array();
		$this->gatewayName = str_replace('_', '', ucwords($options['name'], '_'));
		$this->receiverSource = $this->gatewayName . str_replace('_', '', ucwords($options['type'], '_'));
		$this->bills = Billrun_Factory::db()->billsCollection();
		$this->log = Billrun_Factory::db()->logCollection();
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
			throw new Exception("Parser definition missing");
		}
		if (!$this->mapProcessorFields($currentProcessor)) { // if missing mapping fields in conf
			return false;
		}
		$headerStructure = isset($currentProcessor['parser']['header_structure']) ? $currentProcessor['parser']['header_structure'] : array();
		$dataStructure = isset($currentProcessor['parser']['data_structure']) ? $currentProcessor['parser']['data_structure'] : array();
		$parser = $this->getParser();
		$parser->setHeaderStructure($headerStructure);
		$parser->setDataStructure($dataStructure);
		$parser->parse($this->fileHandler);
		$this->headerRows = $parser->getHeaderRows();
		$this->trailerRows = $parser->getTrailerRows();
		$parsedData = $parser->getDataRows();
		$rowCount = 0;

		foreach ($parsedData as $line) {
			$row = $this->getBillRunLine($line);
			if (!$row){
				return false;
			}
			$row['row_number'] = ++$rowCount;
			$this->addDataRow($row);
		}
		return true;
	}

	protected function getBillRunLine($rawLine) {
		$row = $rawLine;
		$row['stamp'] = md5(serialize($row));
		return $row;
	}

	protected function updateData() {
		$data = $this->getData();
		$fileStatus = isset($this->configByType['file_status']) ? $this->configByType['file_status'] : null;
		$fileConfCount = isset($this->configByType['file_response_count']) ? $this->configByType['file_response_count'] : null;
		$fileCorrelationObj = isset($this->configByType['correlation']) ? $this->configByType['correlation'] : null;
		if (!empty($fileStatus) && in_array($fileStatus, array('only_rejections', 'only_acceptance')) && (empty($fileConfCount) || empty($fileCorrelationObj))) {
			throw new Exception('Missing file response definitions');
		}
		$currentFileCount = $this->getCurrentFileCount($fileCorrelationObj);
		if (!empty($fileConfCount) && $currentFileCount == $fileConfCount) {
			$this->updatePaymentsByFileStatus($data);
		} else {
			$this->updatePaymentsByRows($data);
		}
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
	
	protected function getCurrentFileCount($fileCorrelation) {
		$source = isset($fileCorrelation['source']) ? $fileCorrelation['source'] : null;
		$correlationField = isset($fileCorrelation['field']) ? $fileCorrelation['field'] : null;
		$logField = isset($fileCorrelation['file_field']) ? $fileCorrelation['file_field'] : null;
		if (empty($source) || empty($correlationField) || empty($logField)) {
			throw new Exception('Missing correlaction definitions');
		}
		$relevantRow = ($source == 'header') ? current($this->headerRows) : current($this->trailerRows); // TODO: support in more than one header/trailer
		$query = array(
			$logField => $relevantRow[$correlationField]
		);
		
		return $this->log->query($query)->cursor()->count();
	}
	
	protected function updatePaymentsByFileStatus($data) {
		$originalFile = $this->getOriginalFile();
		$originalFileData = '';  // parse the original file and get his data and header.
		$originalFileHeader = '';
		
		foreach ($originalFileData as $dataRow) {
			// search each payment in transferred data and if not exists and not rejected already accept it.
		}
	}
	
	protected function getOriginalFile() {
		
	}
	
	protected function updatePaymentsByRows($data) {
		foreach ($data['data'] as $row) {
			$bill = (static::$type != 'payments') ?  Billrun_Bill_Payment::getInstanceByid($row[$this->tranIdentifierField]) : null;
			if (is_null($bill) && static::$type != 'payments') {
				Billrun_Factory::log('Unknown transaction ' . $row[$this->tranIdentifierField] . ' in file ' . $this->filePath, Zend_Log::ALERT);
				continue;
			}
			$this->updatePayments($row, $bill);
		}
	}

}