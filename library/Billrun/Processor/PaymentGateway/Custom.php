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

	public function __construct($options) {
		$this->configByType = !empty($options[$options['type']]) ? $options[$options['type']] : array();
		$this->gatewayName = str_replace('_', '', ucwords($options['name'], '_'));
		$this->receiverSource = $this->gatewayName . str_replace('_', '', ucwords($options['type'], '_'));
		$this->bills = Billrun_Factory::db()->billsCollection();
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
		$fileStatus = isset($this->configByType['file_status']) ? $this->configByType['file_status'] : null;
		$data = $this->getData();
		if (!empty($fileStatus) && static::$type == 'transactions_response') {
			switch ($fileStatus) {
				case 'only_rejections':
					$this->updateByRejectionsFiles($data);
					break;
				case 'only_acceptance':
					$this->updateByAcceptanceFiles($data);
					break;
				case 'mixed':
					$this->updatePaymentsByRows($data);
					break;
				default:
					$this->updatePaymentsByRows($data);
					break;
			}
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