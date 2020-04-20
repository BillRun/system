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
class Billrun_Generator_PaymentGateway_CreditGuard_Transactions extends Billrun_Generator_Csv {
	
	use Billrun_Traits_Api_OperationsLock;

	protected static $type = 'CreditGuard';

	protected $customers;
	protected $subscribers;
	protected $cgLogFile; 
	protected $gatewayCredentials; 
	protected $gateway;
	protected $extractionDateFormat;
	protected $generateStructure;
	protected $exportDefinitions;
	protected $filterParams = array('aids', 'invoices', 'exclude_accounts', 'billrun_key', 'min_invoice_date', 'mode', 'pay_mode');
	protected $chargeOptions = array();
	protected $startingString;
	protected $endingString;

	public function __construct($options) {
		$this->initPaymentGatwayDetails();
		$configPath = APPLICATION_PATH . "/conf/PaymentGateways/" . self::$type . "/struct.ini";
		$this->loadConfig($configPath);
		$options = array_merge($options, $this->getAllExportDefinitions());
		$this->subscribers = Billrun_Factory::db()->subscribersCollection();
		$this->extractionDateFormat = isset($this->exportDefinitions['extraction_date_format']) ? date($this->exportDefinitions['extraction_date_format']) : '';
		$this->startingString = isset($this->exportDefinitions['file_starting_string']) ?$this->exportDefinitions['file_starting_string'] : '';
		$this->endingString = isset($this->exportDefinitions['file_ending_string']) ?$this->exportDefinitions['file_ending_string'] : '';
		$this->initChargeOptions($options);
		$this->initLogFile();
		parent::__construct($options);
		$this->export_dir = $options['export']['dir'];
	}

	public function load() {
		$paymentParams = array(
			'dd_stamp' => $this->getStamp(),
		);
		$filtersQuery = Billrun_Bill_Payment::buildFilterQuery($this->chargeOptions);
		$payMode = isset($this->chargeOptions['pay_mode']) ? $this->chargeOptions['pay_mode'] : 'one_payment';
		$this->customers = iterator_to_array(Billrun_Bill::getBillsAggregateValues($filtersQuery, $payMode));
		Billrun_Factory::log()->log('generator entities loaded: ' . count($this->customers), Zend_Log::INFO);
		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
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
			$paymentParams = array();
			$account = $subscribers_in_array[$customer['aid']];
			if (!$this->isActiveGatewayCreditGuard($account)) {
				continue;
			}
			$options = array('collect' => false, 'file_based_charge' => true);
			if (!Billrun_Util::isEqual($customer['left_to_pay'], 0, Billrun_Bill::precision) && !Billrun_Util::isEqual($customer['left'], 0, Billrun_Bill::precision)) {
				Billrun_Factory::log("Wrong payment! left and left_to_pay fields are both set, Account id: " . $customer['aid'], Zend_Log::ALERT);
				continue;
			}
			if (Billrun_Util::isEqual($customer['left_to_pay'], 0, Billrun_Bill::precision) && Billrun_Util::isEqual($customer['left'], 0, Billrun_Bill::precision)) {
				Billrun_Factory::log("Can't pay! left and left_to_pay fields are missing, Account id: " . $customer['aid'], Zend_Log::ALERT);
				continue;
			} else if (!Billrun_Util::isEqual($customer['left_to_pay'], 0, Billrun_Bill::precision)) {
				$paymentParams['amount'] = $customer['left_to_pay'];
				$paymentParams['dir'] = 'fc';
			} else if (!Billrun_Util::isEqual($customer['left'], 0, Billrun_Bill::precision)) {
				$paymentParams['amount'] = $customer['left'];
				$paymentParams['dir'] = 'tc';
			}
			if (!empty($customer['invoices']) && is_array($customer['invoices'])) {
				foreach ($customer['invoices'] as $invoice) {
					$id = isset($invoice['invoice_id']) ? $invoice['invoice_id'] : $invoice['txid'];
					$amount = isset($invoice['left']) ? $invoice['left'] : $invoice['left_to_pay'];
					if (Billrun_Util::isEqual($amount, 0, Billrun_Bill::precision)) {
						continue;
					}
					$payDir = isset($invoice['left']) ? 'paid_by' : 'pays';
					$paymentParams[$payDir][$invoice['type']][$id] = $amount;
				}
			}
			if (Billrun_Util::isEqual($paymentParams['amount'], 0, Billrun_Bill::precision)) {
				continue;
			}	
			if (($this->isChargeMode() && $paymentParams['amount'] < 0) || ($this->isRefundMode() && $paymentParams['amount'] > 0)) {
				continue;
			}
			$paymentParams['aid'] = $customer['aid'];
			$paymentParams['billrun_key'] = $customer['billrun_key'];
			$paymentParams['source'] = $customer['source'];
			try {
				$payment = Billrun_Bill::pay($customer['payment_method'], array($paymentParams), $options);
			} catch (Exception $e) {
				Billrun_Factory::log()->log('Error paying debt for account ' . $paymentParams['aid'] . ' when generating Credit Guard file, ' . $e->getMessage(), Zend_Log::ALERT);
				continue;
			}
			$currentPayment = $payment[0];
			$currentPayment->save();
			$params['amount'] = $this->convertAmountToSend($paymentParams['amount']);
			$params['aid'] = $currentPayment->getAid();
			$params['txid'] = $currentPayment->getId();
			$params['deal_type'] = !Billrun_Util::isEqual($customer['left'], 0, Billrun_Bill::precision) ? '51' : '01'; // credit or debit
			$params['card_token'] = $account['payment_gateway']['active']['card_token'];
			$params['card_expiration'] = $account['payment_gateway']['active']['card_expiration'];
			$line = $this->getDataLine($params);
			$this->data[] = $line;
		}
		$this->buildHeader();
	}

	protected function setFilename() {
		$this->filename = $this->startingString. $this->extractionDateFormat . $this->endingString;
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
		$line = $this->getHeaderLine();
		$this->headers[0] = $line;
	}

	public function generate() {
		if (!$this->lock()) {
			Billrun_Factory::log("Generator is already running", Zend_Log::NOTICE);
			return;
		}
		parent::generate();
		$this->cgLogFile->setProcessTime();
		$this->cgLogFile->save();
		if (!$this->reslease()) {
			Billrun_Factory::log("Problem in releasing operation", Zend_Log::ALERT);
			return;
		}
	}

	protected function initLogFile() {
		$this->cgLogFile = new Billrun_LogFile_CreditGuard($this->chargeOptions);
		$this->cgLogFile->setSequenceNumber();
		$this->setFilename();
		$this->cgLogFile->setFileName($this->filename);
		$this->cgLogFile->setStamp();
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
		$this->removeEmptyFile();
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

	protected function getDataLine($params) {
		return array(
			0 => '001',
			1 => $this->gatewayCredentials['charging_terminal'],
			2 => $params['amount'],
			3 => 'ILS',
			4 => $params['card_token'],
			5 => $params['card_expiration'],
			6 => $params['deal_type'],
			7 => 1,
			8 => '',
			9 => $params['txid'],
			10 => '',
			11 => $params['aid'],
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
	}
	
	protected function getHeaderLine() {
		return array(
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
			10 => $this->generateUniqueId(),
			11 => '',
			12 => '',
			13 => '',
		);
	}
	
	protected function generateUniqueId() {
		return round(microtime(true) * 1000) . rand(100000, 999999);
	}
	
	protected function getHeaderRowContent($entity,$pad_length = array()) {
		$this->pad_type = STR_PAD_LEFT;
		$row_contents = '';
		if (!empty($pad_length)){
			$this->pad_length = $pad_length;
		}
		$header_numeric_fields = $this->generateStructure['header']['numeric_fields'];
		for ($key = 0; $key < count($this->pad_length); $key++) {
			if (in_array($key,$header_numeric_fields)){
				$this->pad_string = '0';
			}
			else{
				$this->pad_string = ' ';
			}
			$row_contents.=str_pad((isset($entity[$key]) ? substr($entity[$key], 0, $this->pad_length[$key]) : ''), $this->pad_length[$key], $this->pad_string, $this->pad_type);
		}
		return $row_contents;
	}
	
	protected function getRowContent($entity,$pad_length = array()) {
		$this->pad_type = STR_PAD_LEFT;
		$row_contents = '';
		if (!empty($pad_length)){
			$this->pad_length = $pad_length;
		}
		$data_numeric_fields = $this->generateStructure['data']['numeric_fields'];
		for ($key = 0; $key < count($this->pad_length); $key++) {
			if (in_array($key, $data_numeric_fields)){
				$this->pad_string = '0';
			}
			else{
				$this->pad_string = ' ';
			}
			$row_contents.=str_pad((isset($entity[$key]) ? substr($entity[$key], 0, $this->pad_length[$key]) : ''), $this->pad_length[$key], $this->pad_string, $this->pad_type);
		}
			return $row_contents;
	}
	
	protected function initChargeOptions($options) {
		foreach ($options as $paramName => $option) {
			if (in_array($paramName, $this->filterParams)) {
				$this->chargeOptions[$paramName] = $option;
			}
		}
	}
	
	protected function isRefundMode() {
		return isset($this->chargeOptions['mode']) && $this->chargeOptions['mode'] == 'refund';
	}

	protected function isChargeMode() {
		return isset($this->chargeOptions['mode']) && $this->chargeOptions['mode'] == 'charge';
	}
	
	protected function removeEmptyFile() {
		$ret = unlink($this->file_path);
		if ($ret) {
			Billrun_Factory::log()->log('Empty file ' .  $this->file_path . ' was removed successfully', Zend_Log::INFO);
			return;
		}
		Billrun_Factory::log()->log('Failed removing empty file ' . $this->file_path, Zend_Log::INFO);
	}
	
	protected function getConflictingQuery() {
		return array();
	}

	protected function getInsertData() {
		return array(
			'action' => 'generate_pg_file',
			'filtration' => 'all',
		);
	}

	protected function getReleaseQuery() {
		return array(
			'action' => 'generate_pg_file',
			'filtration' => 'all',
			'end_time' => array('$exists' => false)
		);
	}
	
}
