<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Csv generator class
 *
 * @package  Billing
 * @since    5.7
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Pay.php';
require_once APPLICATION_PATH . '/application/controllers/Action/Collect.php';

/**
 * Billing CreditGuard csv generator class
 * 
 * @package  Billing
 * @since    5.0
 */
class Billrun_Generator_CGcsv extends Billrun_Generator_Csv {

	protected $customers;
	protected $subscribers;
	protected $dd_log_file; 
	protected $gatewayCredentials; 
	protected $gateway;
	protected $extractionDateFormat;
	protected $generateStructure;
	protected $exportDefinitions;

	public function __construct($options) {
		$this->initPaymentGatwayDetails();
		$this->loadConfig(Billrun_Factory::config()->getConfigValue('CGcsv.config_path'));
		$options = array_merge($options, $this->getAllExportDefinitions());
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$this->extractionDateFormat = date('YmdHis');
		$this->initLogFile($options['stamp']);

		parent::__construct($options);
		$this->export_dir = $options['export']['dir'];
	}

	public function load() {
		$paymentParams = array(
			'dd_stamp' => $this->getStamp(),
		);
		$this->customers = iterator_to_array(Billrun_Bill::getBillsAggregateValues());
		
		Billrun_Factory::log()->log('generator entities loaded: ' . count($this->customers), Zend_Log::INFO);
		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
		$involvedAccounts = array();
		$this->data = array();	
		$customersAids = array_map(function($ele){ 
			return $ele['aid'];
		}, $this->customers);
		
		$newAccount = Billrun_Factory::account();
		$accountQuery = $newAccount->getQueryActiveAccounts($customersAids);
		$accounts = $newAccount->getAccountsByQuery($accountQuery);
		foreach ($accounts as $account){
			$subscribers_in_array[$account['aid']] = $account;
		}
		
		foreach ($this->customers as $customer) {
			$account = $subscribers_in_array[$customer['aid']];
			if (!$this->isActiveGatewayCreditGuard($account)) {
				continue;
			}
			$options = array('collect' => false, 'file_based_charge' => true);
			$involvedAccounts[] = $paymentParams['aid'] = $customer['aid'];
			$paymentParams['billrun_key'] = $customer['billrun_key'];
			$paymentParams['amount'] = abs($customer['due']);
			$paymentParams['source'] = $customer['source'];
			$payment = Billrun_Bill::pay($customer['payment_method'], array($paymentParams), $options);
			$amount = $this->convertAmountToSend($paymentParams['amount']);
			$dealType = $customer['due'] < 0 ? '51' : '01'; // credit or debit
			$line = array(
				0 => '001',
				1 => $this->gatewayCredentials['charging_terminal'],
				2 => $amount,
				3 => 'ILS',
				4 => $account['payment_gateway']['active']['card_token'],
				5 => $account['payment_gateway']['active']['card_expiration'],
				6 => $dealType,
				7 => 1,
				8 => '',
				9 => $payment[0]->getId(),
				10 => '',
				11 => '',
				12 => 4,
				13 => '',
				14 => '',
				15 => '',
				16 => '',
				17 => '',
				18 => '',
				19 => '',
				20 => '',
				21 => '',
				22 => '',
				23 => '',
				24 => '',
				25 => '',
				26 => '',
				27 => '',
				28 => '',
				29 => '',
				30 => '',
			);
			$this->data[] = $line;
		}
		$this->buildHeader();
	}

	protected function setFilename() {
		$this->filename = 'billing_SYSTEM_' . $this->extractionDateFormat . '.in';
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
	

	protected function buildHeader() {
		$line = array(
			0 => '000',
			1 => '',
			2 => date('ymdHis'),
			3 => '',
			4 => '',
			5 => '',
			6 => '',
			7 => '',
			8 => '',
			9 => count($this->customers),
			10 => round(microtime(true) * 1000) . rand(100000, 999999),
			11 => '',
			12 => '',
			13 => '',
		);
		$this->headers[0] = $line;
	}

	public function generate() {
		parent::generate();
		$this->dd_log_file->setProcessTime();
		$this->dd_log_file->save();
	}

	protected function initLogFile($stamp) {
		$this->dd_log_file = new Billrun_LogFile_DD(array('stamp' => $stamp));
		$this->dd_log_file->setSequenceNumber();
		$this->setFilename();
		$this->dd_log_file->setFileName($this->filename);
		$this->dd_log_file->setStamp();
	}

	protected function initPaymentGatwayDetails() {
		$this->gateway = Billrun_Factory::paymentGateway('CreditGuard');
		$this->gatewayCredentials = $this->gateway->getGatewayCredentials();
	}
	
	protected function isActiveGatewayCreditGuard($account) {
		return $account['payment_gateway']['active']['name'] == 'CreditGuard';
	}
	
	
	/**
	 * the structure configuration
	 * @param type $path
	 */
	protected function loadConfig($path) {
		$structConfig = (new Yaf_Config_Ini($path))->toArray();
		$this->generateStructure = $structConfig['generator'];
		$this->exportDefinitions = $structConfig['export'];
	}
	
	protected function convertAmountToSend($amount) {
		$amount = round($amount, 2);
		return $amount * 100;
	}
	
	public function shouldFileBeMoved() {
		$localPath = $this->export_directory . '/' . $this->filename;
		if (!empty(file_get_contents($localPath))) {
			return true;
		}
		return false;
	}
	
	protected function getAllExportDefinitions() {
		$exportDefinitions = array();
		foreach ($this->exportDefinitions  as $key => $value) {
			$exportDefinitions[$key] = $value;
		}
		$dbExportDefinitions = $this->gateway->getGatewayExport();
		foreach ($dbExportDefinitions as $key => $value) { // db definitions ran over ini configuration + add new definitions
			$exportDefinitions[$key] = $value;
		}
		return array('export' => $exportDefinitions);
	}

}
