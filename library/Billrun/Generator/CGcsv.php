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

	public function __construct($options) {
		$this->initPaymentGatwayDetails();
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$this->extractionDateFormat = date('YmdHis');
		$this->initLogFile($options['stamp']);

		parent::__construct($options);
		$this->export_dir = $options['export']['dir'];
	}

	public function load() {
		$this->loadConfig(Billrun_Factory::config()->getConfigValue('CGcsv.config_path'));
		$today = new MongoDate();
		$paymentParams = array(
			'dd_stamp' => $this->getStamp(),
		);
		if (!Billrun_Bill_Payment::removePayments($paymentParams)) { // removePayments if this is a rerun
			throw new Exception('Error removing payments before rerun');
		}
		$this->customers = iterator_to_array($this->gateway->getCustomers());
		
		Billrun_Factory::log()->log('generator entities loaded: ' . count($this->customers), Zend_Log::INFO);
		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
		$involvedAccounts = array();
		$this->data = array();	
		$customersAids = array_map(function($ele){ 
			return $ele['aid'];
		}, $this->customers);
		
		$accounts = $this->subscribers->query(array('aid' => array('$in' => $customersAids), 'from' => array('$lte' => $today), 'to' => array('$gte' => $today), 'type' => "account"))->cursor();
		foreach ($accounts as $account){
			$subscribers_in_array[$account['aid']] = $account;
		}
		
		foreach ($this->customers as $customer) {
			$account = $subscribers_in_array[$customer['aid']];
			if (!$this->isActiveGatewayCreditGuard($account)) {
				continue;
			}
			$involvedAccounts[] = $paymentParams['aid'] = $customer['aid'];
			$paymentParams['billrun_key'] = $customer['billrun_key'];
			$paymentParams['amount'] = $customer['due'];
			$paymentParams['source'] = $customer['source'];
			
	//		$payment = payAction::pay('credit', array($paymentParams), $options)[0];
			$amount = $this->convertAmountToSend($paymentParams['amount']);
			$line = array(
				0 => '001',
				1 => $this->gatewayCredentials['redirect_terminal'],
				2 => $amount,
				3 => 1,
				4 => $account['payment_gateway']['active']['card_token'],
				5 => $account['payment_gateway']['active']['card_expiration'],
				6 => '01',
				7 => 1,
				8 => '',
				9 => '',
				10 => '',
				11 => '',
				12 => 4,
				13 => '',
				14 => '',
				15 => '',
				16 => '',
			);
			$this->data[] = $line;
		}
		$this->buildHeader();
	}

	protected function setFilename() {
		$this->filename = 'c' . $this->extractionDateFormat . '.' . $this->gatewayCredentials['redirect_terminal'];
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
	}
	
	protected function convertAmountToSend($amount) {
		$amount = round($amount, 2);
		return $amount * 100;
	}

}
