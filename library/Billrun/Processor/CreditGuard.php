<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Processor for Credit Guard files.
 * @package  Billing
 * @since    5.7
 */
class Billrun_Processor_CreditGuard extends Billrun_Processor_Updater {

	/**
	 *
	 * @var string
	 */

	protected static $type = 'CreditGuard';

	protected $structConfig;
	protected $headerStructure;
	protected $dataStructure;
	protected $deals_num;
	protected $bills;
	protected $processorDefinitions;
	protected $parserDefinitions;
	protected $workspace;


	public function __construct($options) {
		$this->loadConfig(Billrun_Factory::config()->getConfigValue(self::$type . '.' . $options['version']. '.config_path'));
		$options = array_merge($options, $this->getProcessorDefinitions());
		if (!isset($options['version'])) {
			throw new Exception('Please pass Credit Guard version for processing files');
		}
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
			$row['amount'] = $line['amount'];
			$row['transaction_id'] = $line['deal_id'];
			$row['ret_code'] = $line['deal_status'];
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
		foreach ($data['data'] as $row) {
			$bill = Billrun_Bill_Payment::getInstanceByid($row['transaction_id']);
			if (is_null($bill)) {
				Billrun_Factory::log('Unknown transaction ' . $row['transaction_id'], Zend_Log::ALERT);
				continue;
			}	
			$paymentResponse = $this->getPaymentResponse($row);
			$bill->setPending(false);
			Billrun_Bill_Payment::updateAccordingToStatus($paymentResponse, $bill, 'CreditGuard');
			if ($paymentResponse['stage'] == 'Completed') {
				$bill->markApproved($paymentResponse['stage']);
				$billData = $bill->getRawData();
				if (isset($billData['left_to_pay']) && $billData['due']  > (0 + Billrun_Bill::precision)) {
					Billrun_Factory::dispatcher()->trigger('afterRefundSuccess', array($billData));
				}
				if (isset($billData['left']) && $billData['due'] < (0 - Billrun_Bill::precision)) {
					Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($billData));
				}
			}
		}
	}

	/**
	 * the structure configuration
	 * @param type $path
	 */
	protected function loadConfig($path) {
		$this->structConfig = (new Yaf_Config_Ini($path))->toArray();
		$this->headerStructure = $this->structConfig['header'];
		$this->dataStructure = $this->structConfig['data'];
		$this->processorDefinitions = $this->structConfig['processor'];
		$this->parserDefinitions = $this->structConfig['parser'];
		$this->workspace = $this->structConfig['config']['workspace'];
	}
	
	
	protected function isValidTransaction($row){
		if ($row['ret_code'] == '000') { // 000 - Good Deal
			return true;
		} else{
			return false;
		}
	}
	
	protected function getRowDateTime($dateStr) {
		$datetime = new DateTime();
		$date = $datetime->createFromFormat('ymdHis', $dateStr);
		return $date;
	}

	protected function getPaymentResponse($row) {
		$stage = 'Rejected';
		if ($this->isValidTransaction($row)) {
			$stage = 'Completed';
		}

		return array('status' => $row['ret_code'], 'stage' => $stage);
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
