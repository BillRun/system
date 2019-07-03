<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Processor for payment gateway files.
 * @package  Billing
 * @since    5.9
 */
class Billrun_Processor_PaymentGateway_PaymentGateway extends Billrun_Processor_Updater {

	/**
	 * Name of the payment gateway in Billrun.
	 * @var string
	 */
	protected $gatewayName;
	
	
	/**
	 * Name of the receiver related action.
	 * @var string
	 */
	protected $actionType;

	protected $structConfig;
	protected $headerStructure;
	protected $dataStructure;
	protected $deals_num;
	protected $bills;
	protected $processorDefinitions;
	protected $parserDefinitions;
	protected $workspace;
	protected $configPath = APPLICATION_PATH . "/conf/PaymentGateways/CreditGuard/struct.ini";


	public function __construct($options) {
		$this->loadConfig($this->configPath);
		$options = array_merge($options, $this->getProcessorDefinitions());
		parent::__construct($options);
		$this->bills = Billrun_Factory::db()->billsCollection();
	}
	
	/**
	 * @see Billrun_Plugin_Interface_IProcessor::processData
	 */
	protected function processLines() {
		$parser = $this->getParser();
		$parser->setHeaderStructure($this->headerStructure);
		$parser->setDataStructure($this->dataStructure);
		$parser->parse($this->fileHandler);
		$parsedData = $parser->getDataRows();
		$rowCount = 0;

		foreach ($parsedData as $line) {
			$row = $this->getBillRunLine($line);
			if (!$row){
				return false;
			}
			$newRow = array_merge($row, $this->addFields($row));
			$newRow['row_number'] = ++$rowCount;
			$this->addDataRow($newRow);
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
		foreach ($data['data'] as $row) {
			$bill = Billrun_Bill_Payment::getInstanceByid($row['transaction_id']);
			if (is_null($bill)) {
				Billrun_Factory::log('Unknown transaction ' . $row['transaction_id'] . ' in file ' . $this->filePath, Zend_Log::ALERT);
				continue;
			}
			$this->updatePayments($row, $bill);
		}
	}

	/**
	 * the structure configuration
	 * @param type $path
	 */
	protected function loadConfig($path) {
		$this->structConfig = (new Yaf_Config_Ini($path))->toArray();
		$this->headerStructure = isset($this->structConfig['header'][$this->actionType]) ? $this->structConfig['header'][$this->actionType] : array();
		$this->dataStructure = isset($this->structConfig['data'][$this->actionType]) ? $this->structConfig['data'][$this->actionType] : array();
		$this->processorDefinitions = isset($this->structConfig['processor'][$this->actionType]) ? $this->structConfig['processor'][$this->actionType] : array();
		$this->parserDefinitions = isset($this->structConfig['parser'][$this->actionType]) ? $this->structConfig['parser'][$this->actionType] : array();
		$this->workspace = $this->structConfig['config']['workspace'];
	}
	
	protected function getRowDateTime($dateStr) {
		$datetime = new DateTime();
		$date = $datetime->createFromFormat('ymdHis', $dateStr);
		return $date;
	}
	
	public function skipQueueCalculators() {
		return true;
	}

	protected function getProcessorDefinitions() {
		$processorDefinitions = array();
		$parserDefinitions = array();
		foreach ($this->processorDefinitions  as $key => $value) {
			$processorDefinitions[$key] = $value;
		}
		foreach ($this->parserDefinitions as $key => $value) {
			$parserDefinitions[$key] = $value;
		}
		
		return array('processor' => $processorDefinitions, 'parser' => $parserDefinitions, 'workspace' => $this->workspace);
	}

}
