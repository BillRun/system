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
	
	use Billrun_Traits_ConditionsCheck;
	
	protected static $type = 'transactions_request';
	protected $filterParams = array('aids', 'invoices', 'exclude_accounts', 'billrun_key', 'min_invoice_date', 'mode', 'pay_mode');
	protected $tokenField = null;
	protected $amountField = null;
	protected $generatedFileLog;
	protected $generatorFilters = array();
	protected $extraParamsDef = array();
	protected $options = array();
	protected $extraParamsNames = array();
	protected $fileNameStructure;
	protected $fileNameParams;

	public function __construct($options) {
		parent::__construct($options);
		$this->fileNameParams = isset($this->configByType['filename_params']) ? $this->configByType['filename_params'] : '';
		$this->fileNameStructure = isset($this->configByType['filename']) ? $this->configByType['filename'] : '';
		$this->initChargeOptions($options);
		$this->initLogFile();
		$this->localDir = $this->configByType['export']['export_directory'];
		if (isset($this->configByType['generator']['filtration'])) {
			$this->generatorFilters = $this->configByType['generator']['filtration'];
		}
		if (isset($this->configByType['parameters'])) {
			$this->extraParamsDef = $this->configByType['parameters'];
		}
		$this->options = $options;
	}

	public function load() {
		if (!$this->validateExtraParams()) {
			$message = "Parameters not validated for file type " .  $this->configByType['file_type'] . '. No file was generated.'; 
			throw new Exception($message);
			return;
		}
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
		$maxRecords = !empty($this->configByType['generator']['max_records']) ? $this->configByType['generator']['max_records'] : null;
		Billrun_Factory::dispatcher()->trigger('beforeGeneratingCustomPaymentGatewayFile', array(static::$type, $this->configByType['file_type'], $this->options, &$this->customers));
		foreach ($this->customers as $customer) {
			if (!is_null($maxRecords) && count($this->data) == $maxRecords) {
				break;
			}
			$paymentParams = array();
			$account = $subscribersInArray[$customer['aid']];
			$accountConditions = !empty($this->generatorFilters) && isset($this->generatorFilters['accounts']) ? $this->generatorFilters['accounts'] : array();
			if (!$this->isAccountUpholdConditions($account->getRawData(), $accountConditions)) {
				continue;
			}
			$options = array('collect' => false, 'file_based_charge' => true, 'generated_pg_file_log' => $this->generatedFileLog);
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
			$placeHoldersConditions = !empty($this->generatorFilters) && isset($this->generatorFilters['placeholders']) ? $this->generatorFilters['placeholders'] : array();
			if (!$this->isPaymentUpholdPlaceholders($paymentParams, $placeHoldersConditions)) {
				continue;
			}
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
		$this->trailers[0] = $this->getTrailerLine();
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

	protected function initLogFile() {
		$logOptions = $this->chargeOptions;
		$logOptions['source'] = $this->gatewayLogName . str_replace('_', '', ucwords(static::$type, '_'));
		$this->logFile = new Billrun_LogFile_CustomPaymentGateway($logOptions);
		$this->logFile->setSequenceNumber();
		$this->logFile->setFileName($this->getFilename());
		$this->logFile->setStamp();
		$this->generatedFileLog = $this->logFile->getStamp();
		$this->logFile->save();
	}
	
	protected function isPaymentUpholdPlaceholders($paymentDetails, $placeHoldersConditions) {
		$res = true;
		foreach ($placeHoldersConditions as $condition) {
			switch ($condition['field']) {
				case 'charge_amount':
					$newCondition = array(
						'field' => 'amount',
						'op' => $condition['op'],
						'value' => $condition['value']
					);
					if (!$this->isConditionsMeet($paymentDetails, array($newCondition))) {
						$res = false;
					}
					break;

				default:
					Billrun_Factory::log()->log("Unknown placeholder for file type " .  $this->configByType['file_type'] , Zend_Log::INFO);
					break;
			}
		}
		
		return $res;
	}
	
	protected function isAccountUpholdConditions($account, $conditions) {
		if (empty($conditions)) {
			return true;
		}
		if ($this->isConditionsMeet($account, $conditions)) {
			return true;
		}
		return false;
	}
	
	protected function validateExtraParams() {
		$validated = true;
		if (empty($this->extraParamsDef)) {
			return $validated;
		}
		foreach ($this->extraParamsDef as $paramObj) {
			if (!isset($paramObj['name'])) {
				$validated = false;
				break;
			}
			if ((!isset($paramObj['type']) || $paramObj['type'] == 'string') && isset($this->options[$paramObj['name']])) {
				if (!is_string($this->options[$paramObj['name']])) {
					$validated = false;
					break;
				}
			}
			if (!isset($paramObj['mandatory']) || !empty($paramObj['mandatory'])) {
				if (!isset($this->options[$paramObj['name']])) {
					$validated = false;
					break;
				}
			}
			if (isset($paramObj['regex']) && isset($this->options[$paramObj['name']])) {
				if (!preg_match($paramObj['regex'], $this->options[$paramObj['name']])) {
					$validated = false;
					break;
				}
			}
			$this->extraParamsNames[] = $paramObj['name'];
		}
		
		return $validated;
	}

}
