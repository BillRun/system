<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generator for payment gateways transactions files.
 *
 * @package  Billing
 * @since    5.10
 */

class Billrun_Generator_PaymentGateway_Custom_TransactionsRequest extends Billrun_Generator_PaymentGateway_Custom {
	
	protected static $type = 'transactions_request';
	protected $filterParams = array('aids', 'invoices', 'exclude_accounts', 'billrun_key', 'min_invoice_date', 'mode', 'pay_mode');
	protected $exportDir;
	protected $tokenField = null;
	protected $amountField = null;

	public function __construct($options) {
		parent::__construct($options);
		$this->extractionDateFormat = isset($this->configByType['export']['extraction_date_format']) ? date($this->configByType['export']['extraction_date_format']) : '';
		$this->startingString = isset($this->configByType['export']['file_starting_string']) ? $this->configByType['export']['file_starting_string'] : '';
		$this->endingString = isset($this->configByType['export']['file_ending_string']) ? $this->configByType['export']['file_ending_string'] : '';
		$this->initChargeOptions($options);
//		$this->initLogFile();
//		$this->export_dir = $options['export']['dir'];
	}

	public function load() {
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
			$subscribersInArray[$account['aid']] = $account;
		}
		foreach ($this->customers as $customer) {
			$paymentParams = array();
			$account = $subscribersInArray[$customer['aid']];
//			if (!$this->isGatewayActive($account)) {
//				continue;
//			}
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
			$params['amount'] = $paymentParams['amount'];
			$params['aid'] = $currentPayment->getAid();
			$params['txid'] = $currentPayment->getId();
			$params['card_token'] = $account['payment_gateway']['active']['card_token'];
			if (isset($account['payment_gateway']['active']['card_expiration'])) {
				$params['card_expiration'] = $account['payment_gateway']['active']['card_expiration'];
			}
			$line = $this->getDataLine($params);
			$this->data[] = $line;
		}
		$this->headers[0] = $this->getHeaderLine();
	}

	protected function isGatewayActive($account) {
		return $account['payment_gateway']['active']['name'] == $this->gatewayName;
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
	
		
	
	protected function initLogFile() {
		$this->cgLogFile = new Billrun_LogFile_CreditGuard($this->chargeOptions);
		$this->cgLogFile->setSequenceNumber();
		$this->setFilename();
		$this->cgLogFile->setFileName($this->filename);
		$this->cgLogFile->setStamp();
	}


	
}
