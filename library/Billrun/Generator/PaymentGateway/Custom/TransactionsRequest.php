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
	
	const INITIAL_FILE_STATE = "waiting_for_confirmation";
	const ASSUME_APPROVED_FILE_STATE = "assume_approved";
	use Billrun_Traits_ConditionsCheck;
	
	protected static $type = 'transactions_request';
	protected $filterParams = array('aids', 'invoices', 'exclude_accounts', 'billrun_key', 'min_invoice_date', 'mode', 'pay_mode');
	protected $tokenField = null;
	protected $amountField = null;
	protected $generatedLogFileStamp;
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
		if (isset($options['backup_path'])) {
			$this->localDir = Billrun_Util::getBillRunSharedFolderPath($options['backup_path']);
		} elseif (isset($this->configByType['export']['export_directory'])) {
		$this->localDir = $this->configByType['export']['export_directory'];
		} else {
			$this->localDir = Billrun_Util::getBillRunSharedFolderPath(Billrun_Factory::config()->getConfigValue($this->getType() . '.backup_path', './backups/' . $this->getType()));
		}
		if (isset($this->configByType['filtration'])) {
			$this->generatorFilters = $this->configByType['filtration'];
		}
		$this->extraParamsDef = !empty($this->configByType['parameters']) ? $this->configByType['parameters'] : [];
		$parametersString = "";
		foreach ($this->extraParamsDef as $index => $param) { 
			$field_name = !empty($param['field_name']) ? $param['field_name'] : $param['name'];
			if (!empty($options[$field_name])) {
				if ($param['type'] === "string") {
					$value = !empty($param['regex']) ? (preg_match($param['regex'], $options[$field_name]) ? $options[$field_name] : "") : $options[$field_name];
					$parametersString .= $field_name . "=" . $options[$field_name] . ",";
		}
			}
		}
		$parametersString = trim($parametersString, ",");
		$this->options = $options;
                $className = $this->getGeneratorClassName();
                $generatorOptions = $this->buildGeneratorOptions();
		$this->createLogFile();
		$extraFields = $this->getCustomPaymentGatewayFields();
		$this->logFile->updateLogFileField(null, null, $extraFields);
                try{
                $this->fileGenerator = new $className($generatorOptions);
                }catch(Exception $ex){
                    $this->logFile->updateLogFileField('errors', $ex->getMessage());
			$this->logFile->save();
                    throw new Exception($ex->getMessage());
                }
                $this->initLogFile();
				if (!empty($options['created_by'])) {
					$this->logFile->updateLogFileField('created_by', $options['created_by']);
				}
		$this->logFile->updateLogFileField('file_status',Billrun_Util::getFieldVal(	$options['file_status'],
		Billrun_Util::getFieldVal(	$this->configByType['file_status'], static::INITIAL_FILE_STATE)));
                $this->logFile->updateLogFileField('payment_gateway', $options['payment_gateway']);
                $this->logFile->updateLogFileField('type', 'custom_payment_gateway');
                $this->logFile->updateLogFileField('payments_file_type', $options['type']);
		$this->logFile->updateLogFileField('backed_to', [$this->localDir]);
                $this->logFile->updateLogFileField('parameters_string', $parametersString);
	}

	public function load() {
		if (!$this->validateExtraParams()) {
			$message = "Parameters not validated for file type " . $this->configByType['file_type'] . '. No file was generated.';
			$this->logFile->updateLogFileField('errors', $message);
			$this->logFile->saveLogFileFields();
			throw new Exception($message);
			return;
		}
		$this->logFile->setStartProcessTime();
		Billrun_Factory::log()->log('Parameters are valid for file type ' . $this->configByType['file_type'] . '. Starting to pull relevant bills..', Zend_Log::INFO);
		$filtersQuery = Billrun_Bill_Payment::buildFilterQuery($this->chargeOptions);
		$payMode = isset($this->chargeOptions['pay_mode']) ? $this->chargeOptions['pay_mode'] : 'one_payment';
		$this->customers = iterator_to_array(Billrun_Bill::getBillsAggregateValues($filtersQuery, $payMode));
		$message = 'generator entities loaded: ' . count($this->customers);
		Billrun_Factory::log()->log($message, Zend_Log::INFO);
		$this->logFile->updateLogFileField('info', $message);
		Billrun_Factory::dispatcher()->trigger('afterGeneratorLoadData', array('generator' => $this));
		$this->data = array();
		$customersAids = array_map(function($ele) {
			return $ele['aid'];
		}, $this->customers);

		Billrun_Factory::log()->log('Found ' . count($customersAids) . ' accounts releated to the pulled bills, trying to load their information..', Zend_Log::DEBUG);
		$account = Billrun_Factory::account();
		$accountQuery = array('aid' => array('$in' => $customersAids));
		$accounts = $account->loadAccountsForQuery($accountQuery);
		$accountsInArray = [];
		if (is_array($accounts)) {
			foreach ($accounts as $account) {
				$accountsInArray[$account['aid']] = $account;
			}
		}
		Billrun_Factory::log()->log('Successfully pulled ' . count($accountsInArray) . ' accounts..', Zend_Log::DEBUG);
		$maxRecords = !empty($this->configByType['generator']['max_records']) ? $this->configByType['generator']['max_records'] : null;
		Billrun_Factory::dispatcher()->trigger('beforeGeneratingCustomPaymentGatewayFile', array(static::$type, $this->configByType['file_type'], $this->options, &$this->customers));
		Billrun_Factory::log()->log('Processing the pulled bills, trying to match each bill it\'s account..', Zend_Log::DEBUG);
		$this->setFileMandatoryFields();
		foreach ($this->customers as $customer) {
			if (!is_null($maxRecords) && count($this->data) == $maxRecords) {
				break;
			}
			$paymentParams = array();
			if (isset($accountsInArray[$customer['aid']])) {
				$account = $accountsInArray[$customer['aid']];
				if(!$this->validateMandatoryFieldsExistence($account, 'account')){
					$message = "One or more of the file's mandatory fields is missing for account with aid: " . $customer['aid'] . ". No payment was created. Skipping this account..";
					Billrun_Factory::log($message, Zend_Log::ALERT);
					$this->logFile->updateLogFileField('errors', $message);
					continue;
				}
			} else {
				$message = "The aid in one of the payments is : " . $customer['aid'] . " - didn't find account with this aid. Skipping this payment process";
				Billrun_Factory::log($message, Zend_Log::ALERT);
				$this->logFile->updateLogFileField('errors', $message);
				continue;
			}
			$accountConditions = !empty($this->generatorFilters) && isset($this->generatorFilters['accounts']) ? $this->generatorFilters['accounts'] : array();
			if (!$this->isAccountUpholdConditions($account->getRawData(), $accountConditions)) {
				Billrun_Factory::log()->log('Account ' . $account->getRawData()['aid'] . " didn\'t meet the configured conditions..", Zend_Log::DEBUG);
				continue;
			}
			$options = array('collect' => false, 'file_based_charge' => true, 'generated_pg_file_log' => $this->generatedLogFileStamp);
			Billrun_Factory::log()->log('Checking if the bill needs to be charged..', Zend_Log::DEBUG);
			if (!Billrun_Util::isEqual($customer['left_to_pay'], 0, Billrun_Bill::precision) && !Billrun_Util::isEqual($customer['left'], 0, Billrun_Bill::precision)) {
				$message = "Wrong payment! left and left_to_pay fields are both set, Account id: " . $customer['aid'];
				Billrun_Factory::log($message, Zend_Log::ALERT);
				$this->logFile->updateLogFileField('errors', $message);
				continue;
			}
			if (Billrun_Util::isEqual($customer['left_to_pay'], 0, Billrun_Bill::precision) && Billrun_Util::isEqual($customer['left'], 0, Billrun_Bill::precision)) {
				$message = "Can't pay! left and left_to_pay fields are missing, Account id: " . $customer['aid'];
				Billrun_Factory::log($message, Zend_Log::ALERT);
				$this->logFile->updateLogFileField('errors', $message);
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
			Billrun_Factory::log()->log('Checking if the bill meets the configured conditions..', Zend_Log::DEBUG);
			if (!$this->isPaymentUpholdPlaceholders($paymentParams, $placeHoldersConditions)) {
				continue;
			}
			Billrun_Factory::log()->log('Trying to create debt payment to the pulled bill..', Zend_Log::DEBUG);
			try {
				$options['account'] = $account->getRawData();
				if ($this->isAssumeApproved()) {
					$options['waiting_for_confirmation'] = false;
				}
				$paymentReseponse = Billrun_PaymentManager::getInstance()->pay($customer['payment_method'], array($paymentParams), $options);
				$payment = $paymentReseponse['payment'];
				Billrun_Factory::log()->log('Updated debt payment details - aid: ' . $paymentParams['aid'] . ' ,amount: ' . $paymentParams['amount'] . '. This payment is wating for approval.', Zend_Log::INFO);
			} catch (Exception $e) {
				$message = 'Error paying debt for account ' . $paymentParams['aid'] . ' when generating Credit Guard file, ' . $e->getMessage();
				Billrun_Factory::log()->log($message, Zend_Log::ALERT);
				$this->logFile->updateLogFileField('errors', $message);
				continue;
			}
			$currentPayment = $payment[0];
			//If payment is pre-approved don't wait for confirmation and lfag it as such
			if ($this->isAssumeApproved()) {
				$currentPayment->setExtraFields([static::ASSUME_APPROVED_FILE_STATE => true]);
			}
			$params['amount'] = $paymentParams['amount'];
			$params['aid'] = $currentPayment->getAid();
			$params['txid'] = $currentPayment->getId();
			if (isset($account['payment_gateway']['active']['card_token'])) {
				$params['card_token'] = $account['payment_gateway']['active']['card_token'];
			}
			if (isset($account['payment_gateway']['active']['card_expiration'])) {
				$params['card_expiration'] = $account['payment_gateway']['active']['card_expiration'];
			}
			if (!$this->validateMandatoryFieldsExistence($currentPayment, 'payment_request')) {
				$message = "One or more of the file's mandatory fields is missing for the payment request that was created for aid: " . $customer['aid'] . ". The payment was creadted anyway..";
				Billrun_Factory::log($message, Zend_Log::WARN);
				$this->logFile->updateLogFileField('warnings', $message);
			}
			$extraFields = array_merge_recursive($this->getCustomPaymentGatewayFields(), ['pg_request' => $this->billSavedFields]);
			$currentPayment->setExtraFields($extraFields, ['cpg_name', 'cpg_type', 'cpg_file_type']);
			Billrun_Factory::dispatcher()->trigger('beforeSavingRequestFilePayment', array(static::$type, &$currentPayment, &$params, $this));
			Billrun_Factory::log()->log('Saving the debt payment after processing changes, and customization..', Zend_Log::DEBUG);
			$currentPayment->save();
			$line = $this->getDataLine($params);
			$this->data[] = $line;
		}
		$numberOfRecordsToTreat = count($this->data);
		$message = 'generator entities treated: ' . $numberOfRecordsToTreat;
		$this->file_transactions_counter = $numberOfRecordsToTreat;
		Billrun_Factory::log()->log($message, Zend_Log::INFO);
		$this->logFile->updateLogFileField('info', $message);
		$this->headers[0] = $this->getHeaderLine();
		$this->trailers[0] = $this->getTrailerLine();
	}

	/**
	 * Update the file status  this will afffect the state of the transactions generated to it (i.e. waiting_for_confirmation / assume_approved)
	 */
	public function setFileStatus($newStatus) {
		$this->logFile->updateLogFileField('file_status',$newStatus);
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
	
	protected function isAssumeApproved() {
		return $this->logFile && $this->logFile->getLogFileFieldValue('file_status') == static::ASSUME_APPROVED_FILE_STATE;
	}
	protected function isRefundMode() {
		return isset($this->chargeOptions['mode']) && $this->chargeOptions['mode'] == 'refund';
	}

	protected function isChargeMode() {
		return isset($this->chargeOptions['mode']) && $this->chargeOptions['mode'] == 'charge';
	}
	
	protected function isPaymentUpholdPlaceholders($paymentDetails, $placeHoldersConditions) {
		$res = true;
		foreach ($placeHoldersConditions as $condition) {
			switch ($condition['field']) {
				case 'amount':
					$newCondition = array(
						'field' => 'amount',
						'op' => $condition['op'],
						'value' => $condition['value']
					);
					if (!$this->isConditionsMeet($paymentDetails, array($newCondition))) {
						$res = false;
					}
					break;

				case 'payment_direction':
					$newCondition = array(
						'field' => 'dir',
						'op' => $condition['op'],
						'value' => $condition['value']
					);
					if (!$this->isConditionsMeet($paymentDetails, array($newCondition))) {
						$res = false;
					}
					break;
				default:
					Billrun_Factory::log()->log("Unknown placeholder for file type " . $this->configByType['file_type'], Zend_Log::INFO);
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
			$field_name = !empty($paramObj['field_name']) ? $paramObj['field_name'] : $paramObj['name'];
			Billrun_Factory::dispatcher()->trigger('beforeTransactionsRequestParamValidation', array($field_name, &$this->options[$field_name], &$validated));
			if (empty($field_name)) {
				$validated = false;
				break;
			}
			if ((!isset($paramObj['type']) || $paramObj['type'] == 'string') && isset($this->options[$field_name])) {
				if (!is_string($this->options[$field_name])) {
					$validated = false;
					break;
				}
			}
			if (!isset($paramObj['mandatory']) || !empty($paramObj['mandatory'])) {
				if (!isset($this->options[$field_name])) {
					$validated = false;
					break;
				}
			}
			if (isset($paramObj['regex']) && isset($this->options[$field_name])) {
				if (!preg_match($paramObj['regex'], $this->options[$field_name])) {
					$validated = false;
					break;
				}
			}         
			$this->extraParamsNames[] = $field_name;
			Billrun_Factory::dispatcher()->trigger('afterTransactionsRequestParamValidation', array($field_name, &$this->options[$field_name], &$validated));
			if($validated === false){
				break;
			}
		}
		return $validated;
	}

	public function getType() {
		return "payment_gateways";
	}
	
	public function getCustomPaymentGatewayFields () {
		return [
				'cpg_name' => [!empty($this->gatewayName) ? $this->gatewayName : ""],
				'cpg_type' => [!empty($this->options['type']) ? $this->options['type'] : ""], 
				'cpg_file_type' => [!empty($this->options['file_type']) ? $this->options['file_type'] : ""]
			];
        }
}
