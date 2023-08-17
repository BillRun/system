<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment class
 *
 * @package  Billrun
 * @since    5.0
 */
abstract class Billrun_Bill_Payment extends Billrun_Bill {
	
	use Billrun_Traits_ForeignFields;
	/**
	 *
	 * @var string
	 */
	protected $type = 'rec';

	/**
	 * Payment method
	 * @var string
	 */
	protected $method;

	/**
	 * Payment direction (fc - from customer, tc - to customer)
	 * @var string
	 */
	protected $dir = 'fc';

	/**
	 * Optional fields to be saved to the payment. For some payment methods they are mandatory.
	 * @var array
	 */
	protected $optionalFields = array('payer_name', 'aaddress', 'azip', 'acity', 'IBAN', 'bank_name', 'BIC', 'cancel', 'RUM', 'correction', 'rejection', 'rejected', 'original_txid', 'rejection_code', 'source', 'pays', 'country', 'paid_by', 'vendor_response');

	protected $known_sources;
	
	protected static $aids;
        
        const txIdLength = 13;

	/**
	 * 
	 * @param type $options
	 */
	public function __construct($options) {
		$this->billsColl = Billrun_Factory::db()->billsCollection();
		$direction = 'fc';
		if (isset($options['_id'])) {
			$this->data = new Mongodloid_Entity($options, $this->billsColl);
		} elseif (isset($options['aid'], $options['amount'])) {
			if (!is_numeric($options['amount']) || $options['amount'] < 0 || ($options['amount'] == 0 && !isset($options['deposit'])) || !is_numeric($options['aid'])) {
				throw new Exception('Billrun_Bill_Payment: Wrong input. Was: Customer: ' . $options['aid'] . ', amount: ' . $options['amount'] . '.');
			}
			$this->data = new Mongodloid_Entity($this->billsColl);
			if (isset($options['dir'])) {
				$direction = $options['dir'];
			}
			$this->setDir($direction);
			$this->data['method'] = $this->method;
			$this->data['aid'] = intval($options['aid']);
			$this->data['type'] = $this->type;
			$this->data['amount'] = round(floatval($options['amount']), 2);
                        if(isset($options['is_denial'])){
                            $this->data['is_denial'] = $options['is_denial'];
                        }
			if (isset($options['due'])) {
				$this->data['due'] = round($options['due'], 2);
			} else {
				$this->data['due'] = $this->getDir() == 'fc' ? -$this->data['amount'] : $this->data['amount'];
			}
			if (isset($options['gateway_details'])){
				$this->data['gateway_details'] = $options['gateway_details'];
			}
			if (isset($options['transaction_status'])) {
				$this->data['transaction_status'] = $options['transaction_status'];
			}
			if (isset($options['due_date'])) {
				$this->data['due_date'] = $options['due_date'];
			} else {
				$this->data['due_date'] = new MongoDate();
			}
			if (isset($options['charge'])) {
				$this->data['charge'] = $options['charge'];
			} 
			if (isset($options['installments'])) {
				$this->data['installments'] = $options['installments'];
			}
			if (isset($options['charge'])) {
				$this->data['charge'] = $options['charge'];
			}

			if (isset($options['denial'])) {
				$this->data['denial'] = $options['denial'];
				if ($this->data['due'] >= 0) {
					$this->data['left_to_pay'] = 0; 
				} else {
					$this->data['left'] = 0;
				}
			}
			if (isset($options['generated_pg_file_log'])) {
				$this->data['generated_pg_file_log'] = $options['generated_pg_file_log'];
			}
			if (isset($options['pg_request'])) {
				$this->data['pg_request'] = $options['pg_request'];
			}
			if (isset($options['deposit']) && $options['deposit'] == true) {
				$this->data['deposit'] = $options['deposit'];
				if ($direction != 'fc') {
					throw new Exception('Deposit can only be received from customer');
				}
				$this->data['deposit_amount'] = $this->data['amount'];
				$this->data['amount'] = 0;
				$this->data['due'] = 0;
			}	
			if ($this->isDeposit()) {
				$this->data['left'] = 0;
			}
			if (isset($options['note'])) {
				$this->data['note'] = $options['note'];
			}
			if (isset($options['bills_merged'])) {
				$this->data['bills_merged'] = $options['bills_merged'];
			}

			$this->data['urt'] = new MongoDate();
			foreach ($this->optionalFields as $optionalField) {
				if (isset($options[$optionalField])) {
					$this->data[$optionalField] = $options[$optionalField];
				}
			}
		    if (isset($options['uf']) && is_array($options['uf'])) {
				$data = array_merge($this->getRawData(), ['uf' => $options['uf']]);
				$this->data->setRawData($data);
                               }
			$this->known_sources = Billrun_Factory::config()->getConfigValue('payments.offline.sources') !== null? array_merge(Billrun_Factory::config()->getConfigValue('payments.offline.sources'),array('POS','web')) : array('POS','web');
			$this->forced_uf = !empty($options['forced_uf']) ? $options['forced_uf'] : [];
			if(isset($options['source'])){
				if(!in_array($options['source'], $this->known_sources)){
					throw new Exception("Undefined payment source: " . $options['source'] . ", for account id: " . $this->data['aid'] . ", amount: " . $this->data['amount'] . ". This payment wasn't saved.");
				}
				$this->data['source'] = $options['source'];
			}
		} else {
			throw new Exception('Billrun_Bill_Payment: Insufficient options supplied.');
		}
		parent::__construct($options);
	}
	
	public static function getInstance($method, $params = []) {
		$paymentClass = self::getClassByPaymentMethod($method);
		if (!class_exists($paymentClass)) {
			return false;
		}
		
		return new $paymentClass($params);
	}
	
	public static function validatePaymentMethod($method, $params = []) {
		$paymentClass = self::getClassByPaymentMethod($method);
		return class_exists($paymentClass);
	}

	/**
	 * Save the payment to bills collection
	 * @param type $param
	 * @return type
	 */
	public function save() {
//		$this->coverInvoices();
		if (!isset($this->data['txid'])) {
			$this->setTxid();
		}
		Billrun_Factory::dispatcher()->trigger('beforeSavingPayment', array(&$this->data));
		return parent::save();
	}

	protected function setTxid($txid = NULL) {
		if ($txid) {
			$this->data['txid'] = $txid;
		} else {
			$this->data['_id'] = new MongoId();
			$this->data['txid'] = isset($this->data['gateway_details']['txid']) ? $this->data['gateway_details']['txid'] : self::createTxid();
		}
	}

	/**
	 * Insert new payments to bills collection
	 * @param array $payments
	 */
	public static function savePayments($payments) {
		if ($payments) {
			foreach ($payments as $payment) {
				if (!$payment->getId()) {
					$payment->setTxid();
				}
				$rawPayments[] = $payment->getRawData();
			}
			$options = array(
				'w' => 1,
				'ordered' => FALSE,
			);
			return Billrun_Factory::db()->billsCollection()->batchInsert($rawPayments, $options);
		}
		return NULL;
	}

	/**
	 * 
	 * @param string $id
	 * @return Billrun_Bill_Payment
	 */
	public static function getInstanceByid($id) {
                $id = self::padTxId($id);
		$data = Billrun_Factory::db()->billsCollection()->query('txid', $id)->cursor()->current();
		if ($data->isEmpty()) {
			return NULL;
		}
		return self::getInstanceByData($data);
	}

	/**
	 * 
	 * @param Mongodloid_Entity|array $data
	 * @return Billrun_Bill_Payment
	 */
	public static function getInstanceByData($data) {
		$className = self::getClassByPaymentMethod($data['method']);
		$rawData = is_array($data) ? $data : $data->getRawData();
		$instance = new $className($rawData);
		$instance->setRawData($rawData);
		return $instance;
	}

	/**
	 * 
	 * @return Billrun_Bill_Payment
	 */
	public function getCancellationPayment() {
		$className = Billrun_Bill_Payment::getClassByPaymentMethod($this->getBillMethod());
                $this->unsetAllPendingLinkedBills();
                $rawData = $this->getRawData();
		unset($rawData['_id'], $rawData['generated_pg_file_log']);
		$rawData['due'] = $rawData['due'] * -1;
		$rawData['cancel'] = $this->getId();
		return new $className($rawData);
	}

	public static function getClassByPaymentMethod($paymentMethod) {
		return 'Billrun_Bill_Payment_' . str_replace(' ', '', ucwords(str_replace('_', ' ', $paymentMethod)));
	}

	/**
	 * 
	 * @param array $rejection
	 * @return Billrun_Bill_Payment
	 */
	public function getRejectionPayment($response) {
		$className = Billrun_Bill_Payment::getClassByPaymentMethod($this->getBillMethod());
		$this->unsetAllPendingLinkedBills();
                $rawData = $this->getRawData();
		unset($rawData['_id']);
		$rawData['original_txid'] = $this->getId();
		$rawData['due'] = $rawData['due'] * -1;
		$rawData['rejection'] = TRUE;
		$rawData['rejection_code'] = $response['status'];
		if (isset($response['additional_params'])) {
			$rawData['vendor_response'] = $response['additional_params'];
		}
                               
		return new $className($rawData);
	}
        
	public function getId() {
		if (isset($this->data['txid'])) {
			return $this->data['txid'];
		}
		return NULL;
	}

	public function getRejectionCode() {
		if ($this->isRejection() && isset($this->data['rejection_code'])) {
			return $this->data['rejection_code'];
		}
		return NULL;
	}

	public function getCancellationId() {
		if (isset($this->data['cancel'])) {
			return $this->data['cancel'];
		}
		return NULL;
	}

	public function getDir() {
		return $this->data['dir'];
	}

	/**
	 * Set the direction of the payment (fc / tc)
	 * @param string $dir
	 */
	protected function setDir($direction = 'fc') {
		if (in_array($direction, array('fc', 'tc'))) {
			$this->data['dir'] = $direction;
		} else {
			throw new Exception('direction could be either \'fc\' or \'tc\'');
		}
	}

	/**
	 * Get non-rejected payments
	 * @param type $aid
	 * @param type $dir
	 * @param type $methods
	 * @param type $paymentDate
	 * @param type $amount
	 * @return type
	 */
	public static function getPayments($aid = null, $dir = array('fc'), $methods = array(), $to = null, $from = null, $amount = null, $includeRejected = false, $includeCancelled = false, $includeDenied = false) {
		if (!$includeRejected) {
			$query['rejected'] = array(// rejected payments
				'$ne' => TRUE,
			);
			$query['rejection'] = array(// rejecting payments
				'$ne' => TRUE,
			);
		}
		if (!$includeCancelled) {
			$query['cancelled'] = array(// cancelled payments
				'$ne' => TRUE,
			);
			$query['cancel'] = array(// cancelling payments
				'$exists' => FALSE,
			);
		}
		if (!$includeDenied) {
			$query['denied_by'] = array(// denied payments
				'$exists' => FALSE,
			);
			$query['is_denial'] = array(// denialing payments
				'$ne' => TRUE,
			);
		}
		if (!is_null($aid)) {
			$query['aid'] = $aid;
		}
		if ($dir) {
			$query['dir'] = array(
				'$in' => $dir
			);
		}

		if ($methods) {
			$query['method'] = array(
				'$in' => $methods,
			);
		}
		if ($to && $from) {
			$query['urt'] = array(
				'$gte' => new MongoDate(strtotime($from . ' 00:00:00')),
				'$lte' => new MongoDate(strtotime($to . ' 23:59:59')),
			);
		}
		if (!is_null($amount)) {
			$query['amount'] = $amount;
		}
		return static::queryPayments($query);
	}

	/**
	 * Run a query  on thepayments in the  bills  collection.
	 * @param array $query
	 * @return type
	 */
	public static function queryPayments($query = array(), $sort = array('urt' => -1)) {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$query['type'] = 'rec';
		return iterator_to_array($billsColl->query($query)->cursor()->setRawReturn(true)->sort($sort), FALSE);
	}

	public static function getRejections() {
		$rejection_codesColl = Billrun_Factory::db()->rejection_codesCollection();
		$rejections = iterator_to_array($rejection_codesColl->find(array()), FALSE);
		return array_combine(array_map(function ($rejection) {
				return $rejection['code'];
			}, $rejections), $rejections);
	}

	/**
	 * Mark a payment as rejected to avoid rejecting it again
	 * @return boolean
	 * @todo Make rejections using transactions. This approach is not fail-safe.
	 */
	public function markRejected() {
		$this->data['rejected'] = true;
		$this->data['waiting_for_confirmation'] = false;
		$this->detachPaidBills();
		$this->detachPayingBills();
                $this->unsetAllPendingLinkedBills();
		$this->save();
		Billrun_Bill::payUnpaidBillsByOverPayingBills($this->getAid());
	}

	/**
	 * Find whether a payment has been rejected or not
	 * @return boolean
	 */
	public function isRejected() {
		return isset($this->data['rejected']) && $this->data['rejected'];
	}

	/**
	 * Find whether a payment is a rejection of an existing payment
	 * @return boolean
	 */
	public function isRejection() {
		return isset($this->data['rejection']) && $this->data['rejection'];
	}

	/**
	 * 
	 * @param type $query
	 * @return boolean
	 */
	public static function removePayments($query = array()) {
		if ($query) {
			$payments = static::queryPayments($query);
			if ($payments) {
				$res = Billrun_Factory::db()->billsCollection()->remove(array_merge($query, array('type' => 'rec')));
				if (isset($res['ok'], $res['n']) && $res['n']) {
					foreach ($payments as $payment) {
						$paymentObj = static::getInstanceByData($payment);
						$paymentObj->detachPaidBills();
					}
				} else {
					return FALSE;
				}
			}
		}
		return TRUE;
	}

	public function markCancelled() {
		$this->data['cancelled'] = true;
                $this->unsetAllPendingLinkedBills();
                $this->setPending(false);
                $this->setConfirmationStatus(false);
		return $this;
	}

	public function getCountry() {
		return isset($this->data['country']) ? $this->data['country'] : '';
	}

	/**
	 * Find whether a payment has been cancelled or not
	 * @return boolean
	 */
	public function isCancelled() {
		return isset($this->data['cancelled']) && $this->data['cancelled'];
	}

	/**
	 * Find whether a payment is a cancellation of an existing payment
	 * @return boolean
	 */
	public function isCancellation() {
		return isset($this->data['cancel']);
	}
	
	/**
	 * Find whether a payment has been denied or not
	 * @return boolean
	 */
	public function isDeniedPayment() {
		return isset($this->data['denied_by']);
	}

	/**
	 * Find whether a payment is a denial of an existing payment
	 * @return boolean
	 */
	public function isDenial() {
		return isset($this->data['is_denial']) && $this->data['is_denial'];
	}
	
	/**
	 * Update payment status
	 * @since 5.0
	 */
	public function updateConfirmation() {
		$this->data['waiting_for_confirmation'] = false;
		$this->data['confirmation_time'] = new MongoDate();
                $this->unsetAllPendingLinkedBills();
		$this->setBalanceEffectiveDate();
		$this->save();
	}

	/**
	 * Checks the status of the payment.
	 * 
	 * @return boolean - true if the payment is still not got through.
	 */
	public function isWaiting() {
		if (isset($this->data['waiting_for_confirmation'])){
			$status = $this->data['waiting_for_confirmation'];
		}
		return !empty($status);
	}

	/**
	 * Sets to true if the payment not yet approved.
	 * 
	 */
	public function setConfirmationStatus($status) {
		$this->data['waiting_for_confirmation'] = $status;
	}

	/**
	 * Saves the response from the gateway about the status of the payment.
	 * 
	 */
	public function setPaymentStatus($response, $gatewayName) {
		$vendorResponse = array('name' => $gatewayName, 'status' => $response['status']);
		$this->data['last_checked_pending'] = new MongoDate();
		$extraParams = isset($response['additional_params']) ? $response['additional_params'] : array();
		$vendorResponse = array_merge($vendorResponse, $extraParams);
		$this->data['vendor_response'] = $vendorResponse;
		$this->save();
	}

	/**
	 * Saves the current time that represents the check to see if the a payment is pending.
	 * 
	 */
	public function updateLastPendingCheck() {
		$this->data['last_checked_pending'] = new MongoDate();
		$this->save();
	}
	
		/**
	 * Load payments with status pending and that their status had not been checked for some time. 
	 * 
	 */
	public static function loadPending() {
		$lastTimeChecked = Billrun_Factory::config()->getConfigValue('PaymentGateways.orphan_check_time');
		$paymentsOrphan = new MongoDate(strtotime('-' . $lastTimeChecked, time()));
		$query = array(
			'waiting_for_confirmation' => true,
			'last_checked_pending' => array('$lte' => $paymentsOrphan)
		);
		if (!empty(self::$aids)) {
			$query['aid'] = array('$in' => self::$aids);
		}	
		$payments = Billrun_Bill_Payment::queryPayments($query);
		$res = array();
		foreach ($payments as $payment) {
			$res[] = Billrun_Bill_Payment::getInstanceByData($payment);
		}
		
		return $res;
	}
	
	/**
	 * Responsible for paying payments and classifying payments responses: completed, pending or rejected.
	 * 
	 * @param array $chargeOptions - Options regarding charge operation.
	 *
	 */
	public static function makePayment($chargeOptions) {
		$paymentResponses = [
			'completed' => 1,
			'responses' => [],
		];
		if (!empty($chargeOptions['aids'])) {
			self::$aids = Billrun_Util::verify_array($chargeOptions['aids'], 'int');
		}
		$size = !empty($chargeOptions['size']) ? (int) $chargeOptions['size'] : 100;
		$page = !empty($chargeOptions['page']) ? (int) $chargeOptions['page'] : 0;
		$filtersQuery = self::buildFilterQuery($chargeOptions);
		$payMode = isset($chargeOptions['pay_mode']) ? $chargeOptions['pay_mode'] : 'one_payment';
		$paymentData = Billrun_Util::getIn($chargeOptions, 'payment_data', []);
		if (!empty($chargeOptions['bills'])) {
			$customersAids = array_column($chargeOptions['bills'], 'aid');
		} else {
			$paginationQuery = self::getPaginationQuery($filtersQuery, $page, $size);
			$paginationAids = iterator_to_array(Billrun_Factory::db()->billsCollection()->aggregate($paginationQuery));
			$customersAids = array();
			foreach ($paginationAids as $paginationResult) {
				$customersAids[] = $paginationResult->getRawData()['_id'];
			}
		}
		$involvedAccounts = array();
		$options = array('collect' => true, 'payment_gateway' => TRUE, 'payment_data' => $paymentData);
		$options['pretend_bills'] = !empty($chargeOptions['bills']);

		$query['aid'] = array(
			'$in' => $customersAids
		);
		$accounts = Billrun_Factory::account()->loadAccountsForQuery($query);
		if(!empty($accounts)){
			foreach ($accounts as $account) {
				$accounts_in_array[$account['aid']] = $account;
			}
		}
		foreach ($customersAids as $customerAid) {
			$accountIdQuery = self::buildFilterQuery(array('aids' => array($customerAid)));
			$filtersQuery['$and'] = array($accountIdQuery);
			if (!empty($chargeOptions['bills'])) {
				$billsDetails = array_filter($chargeOptions['bills'], function($bill) use ($customerAid) {
					return $bill['aid'] == $customerAid;
				});
			} else {
				$billsDetails = iterator_to_array(Billrun_Bill::getBillsAggregateValues($filtersQuery, $payMode));
			}
			foreach ($billsDetails as $billDetails) {
				$paymentParams = array();
				$subscriber = $accounts_in_array[$billDetails['aid']];
				$gatewayDetails = Billrun_Util::getIn($paymentData, $billDetails['aid'], $subscriber['payment_gateway']['active']);
				if (!Billrun_PaymentGateway::isValidGatewayStructure($gatewayDetails)) {
					Billrun_Factory::log("Non valid payment gateway for aid = " . $billDetails['aid'], Zend_Log::ALERT);
					continue;
				}
				if (!Billrun_Util::isEqual($billDetails['left_to_pay'], 0, Billrun_Bill::precision) && !Billrun_Util::isEqual($billDetails['left'], 0, Billrun_Bill::precision)) {
					Billrun_Factory::log("Wrong payment! left and left_to_pay fields are both set, Account id: " . $billDetails['aid'], Zend_Log::ALERT);
					continue;
				}
				if (Billrun_Util::isEqual($billDetails['left_to_pay'], 0, Billrun_Bill::precision) && Billrun_Util::isEqual($billDetails['left'], 0, Billrun_Bill::precision)) {
					Billrun_Factory::log("Can't pay! left and left_to_pay fields are missing, Account id: " . $billDetails['aid'], Zend_Log::ALERT);
					continue;
				} else if (!empty($billDetails['left_to_pay'])) {
					$paymentParams['amount'] = $gatewayDetails['amount'] = $billDetails['left_to_pay'];
					if ($payMode == 'multiple_payments') {
						if (!isset($paymentParams['pays'])) {
							$paymentParams['pays'] = [];
						}
						Billrun_Bill::addRelatedBill($paymentParams['pays'], $billDetails['type'], $billDetails['unique_id'], $paymentParams['amount']);
					}
					$paymentParams['dir'] = 'fc';
				} else if (!empty($billDetails['left'])) {
					$paymentParams['amount'] = $billDetails['left'];
					$gatewayDetails['amount'] = -$billDetails['left'];
					if ($payMode == 'multiple_payments') {
						if (!isset($paymentParams['paid_by'])) {
							$paymentParams['paid_by'] = [];
						}
						Billrun_Bill::addRelatedBill($paymentParams['paid_by'], $billDetails['type'], $billDetails['unique_id'], $paymentParams['amount']);
					}
					$paymentParams['dir'] = 'tc';
				}
				if ($payMode == 'one_payment' && !empty($billDetails['invoices']) && is_array($billDetails['invoices'])) {
					foreach ($billDetails['invoices'] as $invoice) {
						$id = isset($invoice['invoice_id']) ? $invoice['invoice_id'] : $invoice['txid'];
						$amount = isset($invoice['left']) ? $invoice['left'] : $invoice['left_to_pay'];
						if (Billrun_Util::isEqual($amount, 0, Billrun_Bill::precision)) {
							continue;
						}
						$payDir = isset($invoice['left']) ? 'paid_by' : 'pays';
						if (!isset($paymentParams[$payDir])) {
							$paymentParams[$payDir] = [];
						}
						Billrun_Bill::addRelatedBill($paymentParams[$payDir], $invoice['type'], $id, $amount);
					}
				}
				if (Billrun_Util::isEqual($paymentParams['amount'], 0, Billrun_Bill::precision)) {
					continue;
				}
				$involvedAccounts[] = $paymentParams['aid'] = $billDetails['aid'];
				$paymentParams['billrun_key'] = $billDetails['billrun_key'];
				$gatewayDetails['currency'] = !empty($billDetails['currency']) ? $billDetails['currency'] : Billrun_Factory::config()->getConfigValue('pricing.currency');
				$gatewayName = $gatewayDetails['name'];
				$gatewayInstanceName = $gatewayDetails['instance_name'];
				$paymentParams['gateway_details'] = $gatewayDetails;
				if ((self::isChargeMode($chargeOptions) && $gatewayDetails['amount'] < 0) || (self::isRefundMode($chargeOptions) && $gatewayDetails['amount'] > 0)) {
					continue;
				}
				if ($gatewayDetails['amount'] > 0) {
					Billrun_Factory::log("Charging account " . $billDetails['aid'] . ". Amount: " . $paymentParams['amount'], Zend_Log::INFO);
				} else {
					Billrun_Factory::log("Refunding account " . $billDetails['aid'] . ". Amount: " . $paymentParams['amount'], Zend_Log::INFO);
				}
				Billrun_Factory::log("Starting to pay bills", Zend_Log::INFO);
				try {
					$options['account'] = $subscriber;
					$paymentResponse = Billrun_PaymentManager::getInstance()->pay($billDetails['payment_method'], array($paymentParams), $options);
					if (empty($paymentResponse['response'])) {
						$paymentResponses['completed'] = 0;
					} else {
						$paymentResponses['responses'] += $paymentResponse['response'];
					}
				} catch (Exception $e) {
					$paymentResponses['completed'] = 0;
					Billrun_Factory::log($e->getMessage(), Zend_Log::ALERT);
					continue;
				}
				foreach ($paymentResponse['payment'] as $payment) {
					$paymentData = $payment->getRawData();
					$transactionId = $paymentData['payment_gateway']['transactionId'];
					if (isset($paymentResponse['response'][$transactionId]['status']) && $paymentResponse['response'][$transactionId]['status'] === '000') {
						if ($paymentData['gateway_details']['amount'] > 0) {
							Billrun_Factory::log("Successful charging of account " . $paymentData['aid'] . ". Amount: " . $paymentData['amount'], Zend_Log::INFO);
						} else {
							Billrun_Factory::log("Successful refunding of account " . $paymentData['aid'] . ". Amount: " . $paymentData['amount'], Zend_Log::INFO);
						}
					}
					self::updateAccordingToStatus($paymentResponse['response'][$transactionId], $payment, $gatewayName);
					$completed = $paymentResponses['completed'];
					if ($paymentResponse['response'][$transactionId]['stage'] != 'Completed') {
						$completed = 0;
					}
					
					if ($paymentResponse['response'][$transactionId]['stage'] == 'Rejected') {
						$gateway = Billrun_PaymentGateway::getInstance($gatewayInstanceName);
						$newPaymentParams['amount'] = $paymentData['amount'];
						$newPaymentParams['aid'] = $paymentData['aid'];
						$newPaymentParams['gateway_details'] = $paymentData['gateway_details'];
						$newPaymentParams['dir'] = $paymentData['dir'];
						$updatedPaymentParams = $gateway->handleTransactionRejectionCases($paymentResponse['response'][$transactionId], $newPaymentParams);
						try {
							if ($updatedPaymentParams) {
								$paymentResponse = Billrun_PaymentManager::getInstance()->pay($paymentData['method'], array($updatedPaymentParams), $options);
								$paymentResponses['responses'] += $paymentResponse['response'];
								if ($paymentResponse['response'][$transactionId]['stage'] != 'Completed') {
									$completed = $paymentResponses['completed'];
								}
								$newPaymentData = $paymentResponse['payment'][0]->getRawData();
								$newTransactionId = $newPaymentData['payment_gateway']['transactionId'];
								self::updateAccordingToStatus($paymentResponse['response'][$newTransactionId], $paymentResponse['payment'][0], $gatewayName);
								if (isset($paymentResponse['response'][$newTransactionId]['status']) && $paymentResponse['response'][$newTransactionId]['status'] === '000') {
									if ($newPaymentData['gateway_details']['amount'] > 0) {
										Billrun_Factory::log("Successful charging of account " . $newPaymentData['aid'] . ". Amount: " . $newPaymentData['amount'], Zend_Log::INFO);
									} else {
										Billrun_Factory::log("Successful refunding of account " . $newPaymentData['aid'] . ". Amount: " . $newPaymentData['amount'], Zend_Log::INFO);
									}
								}
							}
						} catch (Exception $ex) {
							Billrun_Factory::log($ex->getMessage(), Zend_Log::ALERT);
						}
					}
					
					$paymentResponses['completed'] = $completed;
				}
			}
		}
		
		return $paymentResponses;
	}

	/**
	 * Updating the payment status.
	 * 
	 * @param $response - the returned payment gateway status and stage of the payment.
	 * @param Payment payment- the current payment.
	 * @param String $gatewayName - name of the payment gateway.
	 * 
	 */
	public static function updateAccordingToStatus($response, $payment, $gatewayName) {
		if ($response['stage'] == "Completed") { // payment succeeded 
			$payment->updateConfirmation();
			$payment->setPaymentStatus($response, $gatewayName);
		} else if ($response['stage'] == "Pending") { // handle pending
			$payment->setPaymentStatus($response, $gatewayName);
		} else { //handle rejections
			if (!$payment->isRejected()) {
				Billrun_Factory::log('Rejecting transaction  ' . $payment->getId(), Zend_Log::INFO);
				$rejection = $payment->getRejectionPayment($response);
				$rejection->setConfirmationStatus(false);
				$rejection->save();
				$payment->markRejected();
				Billrun_Factory::dispatcher()->trigger('afterRejection', array($payment->getRawData()));
			} else {
				Billrun_Factory::log('Transaction ' . $payment->getId() . ' already rejected', Zend_Log::NOTICE);
			}
		}
	}
	
	public static function checkPendingStatus($pendingOptions){
		if (!empty($pendingOptions['aids'])) {
			self::$aids = Billrun_Util::verify_array($pendingOptions['aids'], 'int');
		}
		$pendingPayments = self::loadPending();
		foreach ($pendingPayments as $payment) {
			$gatewayName = $payment->getPaymentGatewayName();
			$gatewayInstanceName = $payment->getPaymentGatewayInstanceName();
			$paymentGateway = Billrun_PaymentGateway::getInstance($gatewayInstanceName);
			if (is_null($paymentGateway) || !$paymentGateway->hasPendingStatus()) {
				continue;
			}
			$txId = $payment->getPaymentGatewayTransactionId();
			Billrun_Factory::log("Checking status of pending payments", Zend_Log::INFO);
			$status = $paymentGateway->verifyPending($txId);
			if ($paymentGateway->isPending($status)) { // Payment is still pending
				Billrun_Factory::log("Payment with transaction id=" . $txId . ' is still pending', Zend_Log::INFO);
				$payment->updateLastPendingCheck();
				continue;
			}
			$response = $paymentGateway->checkPaymentStatus($status, $paymentGateway);
			Billrun_Factory::log("Updating payment with transaction id=" . $txId . ' to status ' . $response['stage'], Zend_Log::INFO);
			self::updateAccordingToStatus($response, $payment, $gatewayName);
		}
	}
	
	public function updateDetailsForPaymentGateway($gatewayName, $txId){
		if (is_null($txId)) {
			$this->data['payment_gateway'] = array('name' => $gatewayName);
		} else {
			$this->data['payment_gateway'] = array('name' => $gatewayName, 'transactionId' => $txId);
		}
		$this->save();
	}
	
	public function getPaymentGatewayDetails(){
		return $this->data['gateway_details'];
	}
	
	public function getAid(){
		return $this->data['aid'];
	}
	
	protected function getPaymentGatewayTransactionId(){
		return $this->data['payment_gateway']['transactionId'];
	}
			
	protected function getPaymentGatewayName(){
		return $this->data['payment_gateway']['name'];
	}
        
	protected function getPaymentGatewayInstanceName(){
		return $this->data['payment_gateway']['instance_name'];
	}
	
	public function setGatewayChargeFailure($message){
		return $this->data['failure_message'] = $message;
	}
	
	public function getInvoicesIdFromReceipt() {
		$ids = [];
		foreach (Billrun_Util::getIn($this->data, 'pays', []) as $bill) {
			if ($bill['type'] == 'inv') {
				$ids[] = $bill['id'];
			}
		}
		return $ids;
	}

	public function markApproved($status) {
		foreach ($this->getPaidBills() as $bill) {
			$billObj = Billrun_Bill::getInstanceByTypeAndid($bill['type'], $bill['id']);
			$billObj->updatePendingBillToConfirmed($this->getId(), $status, $this->getType())->save();
		}
	}

	public function setPending($pending = true) {
		$this->data['pending'] = $pending;
	}
	
	public function getRejectionPayments($aid) {
		$query = array(
			'aid' => $aid,
			'$or' => array(
				array('rejected' => array('$eq' => true)),
				array('rejection' => array('$eq' => true)),
			),
		);
		return static::getBills($query);
	}
	
	public function getCancellationPayments($aid) {
		$query = array(
			'aid' => $aid,
			'$or' => array(
				array('cancelled' => array('$eq' => true)),
				array('cancel' => array('$exists' => true)),
			),
		);
		return static::getBills($query);
	}
	
	public static function buildFilterQuery($chargeFilters) {
		$filtersQuery = array();
		$errorMessage = self::validateChargeFilters($chargeFilters);
		if ($errorMessage) {
			throw new Exception($errorMessage);
		}
		if (!empty($chargeFilters['aids'])) {
			$aids = Billrun_Util::verify_array($chargeFilters['aids'], 'int');
			$aidsQuery = array('aid' => array('$in' => $aids));
			$filtersQuery = array_merge($filtersQuery, $aidsQuery);
		}
		
		if (!empty($chargeFilters['invoices'])) {
			$invoices = Billrun_Util::verify_array($chargeFilters['invoices'], 'int');
			$invoicesQuery = array('invoice_id' => array('$in' => $invoices));
			$filtersQuery = array_merge($filtersQuery, $invoicesQuery);
		}
		
		if (isset($chargeFilters['exclude_accounts'])) {
			$excludeAids = Billrun_Util::verify_array($chargeFilters['exclude_accounts'], 'int');
			$excludeAidsQuery = array('aid' => array('$nin' => $excludeAids));
			$filtersQuery = array_merge($filtersQuery, $excludeAidsQuery);
		}

		if (isset($chargeFilters['billrun_key'])) {
			$stampQuery = array('billrun_key' => $chargeFilters['billrun_key']);
			$filtersQuery = array_merge($filtersQuery, $stampQuery);
		}

		if (isset($chargeFilters['min_invoice_date'])) {
			$minInvoiceDateQuery = array('invoice_date' => array('$gte' => new MongoDate(strtotime($chargeFilters['min_invoice_date']))));
			$filtersQuery = array_merge($filtersQuery, $minInvoiceDateQuery);
		}

		return $filtersQuery;
	}

	protected static function isRefundMode($options) {
		return isset($options['mode']) && $options['mode'] == 'refund';
	}

	protected static function isChargeMode($options) {
		return isset($options['mode']) && $options['mode'] == 'charge';
	}
	
	protected static function validateChargeFilters($filters) {
		$errorMessage = false;
		if (isset($filters['aids']) && isset($filters['exclude_accounts'])) {
			$errorMessage = "Wrong input! please choose between aids filter to exclude_accounts filter";
		}
		if (isset($filters['min_invoice_date']) && strtotime($filters['min_invoice_date']) === false) {
			$errorMessage = "Wrong input! min_invoice_date filter is invalid";
		}
		if (isset($filters['pay_mode']) && !in_array($filters['pay_mode'], array('one_payment','multiple_payments'))) {
			$errorMessage = "Wrong input! pay_mode can be multiple_payments or one_payment";
		}
		if (isset($filters['mode']) && !in_array($filters['mode'], array('charge','refund'))) {
			$errorMessage = "Wrong input! mode can be charge or refund";
		}
		if (!$errorMessage) {
			return self::validateArrayNumericValues($filters);
		}

		return $errorMessage;
	}
	
	protected static function validateArrayNumericValues($filters) {
		$filtersPossibleArray = array();
		$numericFields = array('aids', 'exclude_accounts', 'invoices');
		foreach ($numericFields as $fieldName) {
			if (isset($filters[$fieldName])) {
				$filtersPossibleArray[$fieldName] = $filters[$fieldName];
			}
		}
		foreach ($filtersPossibleArray as $filterName => $inputArray) {
			if (!is_array($inputArray)) {
				$inputArray = array($inputArray);
			}
			foreach ($inputArray as $value) {
				if (!Billrun_Util::IsIntegerValue($value)) {
					return 'Wrong input! non numeric values in ' . $filterName . ' filter';
				}
			}
		}
		
		return false;
	}

	public function getSinglePaymentStatus() {
		return !empty($this->data['transaction_status']) ? $this->data['transaction_status'] : null;
	}
	
	public static function payAndUpdateStatus($paymentMethod, $paymentParams, $options = array()) {
		$paymentResponse = Billrun_PaymentManager::getInstance()->pay($paymentMethod, array($paymentParams), $options);
		$gatewayName = $paymentParams['gateway_details']['name'];
		$gatewayInstanceName = $paymentParams['gateway_details']['instance_name'];
		$gateway = Billrun_PaymentGateway::getInstance($gatewayInstanceName);
		foreach ($paymentResponse['payment'] as $payment) {
			$paymentData = $payment->getRawData();
			$transactionId = $paymentData['payment_gateway']['transactionId'];
			if (isset($paymentResponse['response'][$transactionId]['status']) && preg_match($gateway->getCompletionCodes(), $paymentResponse['response'][$transactionId]['status'])) {
				Billrun_Factory::log("Received payment for account " . $paymentData['aid'] . ". Amount: " . $paymentData['gateway_details']['transferred_amount'], Zend_Log::INFO);
			}
			self::updateAccordingToStatus($paymentResponse['response'][$transactionId], $payment, $gatewayName);
		}
	}
	
	protected static function getPaginationQuery($filtersQuery, $page, $size) {
		$nonRejectedOrCanceled = Billrun_Bill::getNotRejectedOrCancelledQuery();
		$notPaidBiils = array(
			'$or' => array(
				array('left' => array('$gt' => Billrun_Bill::precision)),
				array('left_to_pay' => array('$gt' => Billrun_Bill::precision)),
			),
		);
		$updatedQuery = array_merge($filtersQuery, $nonRejectedOrCanceled, $notPaidBiils);
		$pipelines[] = array(
			'$match' => $updatedQuery,
		);
		$pipelines[] = array(
			'$sort' => array(
				'type' => 1,
				'due_date' => -1,
			),
		);		
		$pipelines[] = array(
			'$group' => array(
				'_id' => '$aid',
			),
		);
		$pipelines[] = array(
			'$skip' => intval($page) * intval($size)
		);	
		$pipelines[] = array(
			'$limit' => intval($size),
		);
		
		return $pipelines;
	}
	
	public static function createTxid() {
		$txid = Billrun_Factory::db()->billsCollection()->createAutoInc();
		return self::padTxId($txid);
	}
        
        public static function padTxId($txId) {
            return str_pad($txId, self::txIdLength, '0', STR_PAD_LEFT);
        }


	public static function createInstallmentAgreement($params) {
		$installmentAgreement = new Billrun_Bill_Payment_InstallmentAgreement($params);
		return $installmentAgreement->splitBill();
	}
	
	
	/**
	 * Checks if payment is a deposit.
	 * 
	 * @return true if the payment is deposit.
	 */
	public function isDeposit() {
		 return (!empty($this->data['deposit']) && isset($this->data['deposit_amount']));
	}

	/**
	 * Method to unfreeze deposit.
	 * 
	 * @return true if the deposit got unfreezed.
	 */
	public function unfreezeDeposit() {
		if (!$this->isDeposit()) {
			throw new Exception('Payment is not a deposit');
		}
		if (empty($this->data['deposit_amount'])) {
			return false;
		}
		$depositAmount = $this->data['deposit_amount'];
		$this->data['deposit_amount'] = 0;
		$this->data['amount'] = $depositAmount;
		$this->data['due'] = -$depositAmount;
		$this->data['left'] = $depositAmount;
		$this->setBalanceEffectiveDate();
		$this->save();
		Billrun_Bill::payUnpaidBillsByOverPayingBills($this->data['aid']);
		return true;
	}

	public static function createDenial($denialParams, $matchedPayment) {
		$paymentAmount = $matchedPayment->getDue();
		$denialParams['payment_amount'] = $paymentAmount;
		$denial = new Billrun_Bill_Payment_Denial($denialParams);
		if (!is_null($matchedPayment)) {
			$denial->copyLinks($matchedPayment);
		}
		$denial->setTxid();
		$res = $denial->save();
		if ($res) {
			return $denial;
		}
		return false;
	}
	
	/**
	 * Deny a payment
	 * @param $denial- the information about the denied transaction.
	 */
	public function deny($denial) {
		$txId = $denial->getId();
		$deniedBy = array();
		$amount = $denial->getAmount();
		$deniedBy[$txId] = $amount;
		$this->data['denied_by'] = isset($this->data['denied_by']) ? array_merge($this->data['denied_by'], $deniedBy) : $deniedBy;
		$this->data['denied_amount'] = isset($this->data['denied_amount']) ? $this->data['denied_amount'] + $amount : $amount;
		$this->detachPaidBills();
		$this->detachPayingBills();
		$paymentSaved = $this->save();
		if (!$paymentSaved) {
			$message = "Denied flagging failed for rec " . $txId;
			Billrun_Factory::log($message, Zend_Log::ALERT);
			return array('status'=> false, 'massage' => $message);
		} else {
			$this->updatePastRejectionsOnProcessingFiles();
			Billrun_Bill::payUnpaidBillsByOverPayingBills($this->getAid());
		}
		return array('status'=> true);
	}
	
	public function isDenied($denialAmount) {
		$alreadyDenied = 0;
		if (isset($this->data['denied_amount'])) {
			$alreadyDenied = $this->data['denied_amount'];
		}
		$totalAmountToDeny =  $denialAmount + $alreadyDenied;
		return $totalAmountToDeny > $this->data['amount'];
	}

	public function addUserFields($fields = array()) {
		$this->data['uf'] = !empty($fields) ? $fields : new stdClass();
	}
	
	/**
	 * Checks if possible to deny a requested amount according to the bill amount.
	 * @param $denialAmount- the amount to deny.
	 * 
	 * return true when the sum of denied amount is larger than the bill amount
	 */
	public function isAmountDeniable($denialAmount) {
		$alreadyDenied = 0;
		if (isset($this->data['denied_amount'])) {
			$alreadyDenied = $this->data['denied_amount'];
		}
		$totalAmountToDeny =  $denialAmount + $alreadyDenied;
		return $totalAmountToDeny > $this->data['amount'];
	}

	public static function mergeSpllitedInstallments($params) {
		$mergedInstallmentsObj = new Billrun_Bill_Payment_MergeInstallments($params);
		return $mergedInstallmentsObj->merge();
	}
    
    /**
     * get bills affected by payment
     * 
     * @return array of Bills on success, false on error
     */
    public function getPaymentBills() {
        switch ($this->getDir()) {
            case 'fc':
                return $this->getPaidBills();
            case 'tc':
                return $this->getPaidByBills();
            default:
                return false;
        }
    }
	
	public function setForeignFields ($foreignData = []) {
		$paymentData = $this->getRawData();
		$paymentData = array_merge_recursive($paymentData, $foreignData);
		$this->setRawData($paymentData);
	}
	
	public function getForeignFieldsEntity () {
		return 'bills';
	}
	
	public function setUserFields ($data, $unsetOriginalUfFromData = false) {
		$paymentUf = [];
		$config = Billrun_Factory::config();
		$confUserFields = $config->getConfigValue('payments.offline.uf', []);
		$paymentData = ($this instanceof Billrun_Bill) ? $this->getRawData() : $this->getData();
		if (!empty($confUserFields)) {
			foreach ($confUserFields as $key => $field_name) {
				if (!empty($this->forced_uf[$field_name])) {
					$paymentUf['uf'][$field_name] = $this->forced_uf[$field_name];
				}
				if (!empty($data['uf'][$field_name])) {
					$paymentUf['uf'][$field_name] = $data['uf'][$field_name];
					if ($unsetOriginalUfFromData) {
						unset($paymentData['uf'][$field_name]);
					}			
				}
			}
		} else if (!empty($data['uf'])) {
			unset($data['uf']);
		}
		if ($unsetOriginalUfFromData) {
			unset($paymentData['uf']);
		}
		$paymentData = array_merge_recursive($paymentData, $paymentUf);
		$this->setRawData($paymentData);
	}
        
        
        protected function unsetAllPendingLinkedBills() {
            $pays = $this->getPaidBills();
            foreach ($pays as $pay){
                if(isset($pay['pending'])){
                    $this->unsetPendingLinkedBills($pay['type'], $pay['id']);
                }
            }
        }
}
