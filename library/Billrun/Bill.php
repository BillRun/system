<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Bill class
 *
 * @package  Billrun
 * @since    5.0
 */
abstract class Billrun_Bill {

	/**
	 *
	 * @var string
	 */
	protected $type;

	/**
	 *
	 * @var Mongodloid_Entity
	 */
	protected $data;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billsColl;

	/**
	 * Optional fields to be saved to the payment. For some payment methods they are mandatory.
	 * @var array
	 */
	protected $optionalFields = array();

	const precision = 0.00001;

	/**
	 * 
	 * @param type $options
	 */
	public function __construct($options) {
		$this->updateLeft();
	}

	public function getRawData() {
		return $this->data->getRawData();
	}

	/**
	 * 
	 * @param array $rawData
	 */
	protected function setRawData($rawData) {
		$this->data = new Mongodloid_Entity($rawData, $this->billsColl);
	}

	public function getBIC() {
		if (isset($this->data['BIC'])) {
			return $this->data['BIC'];
		}
		return NULL;
	}

	public function getAmount() {
		if (isset($this->data['amount'])) {
			return $this->data['amount'];
		}
		return NULL;
	}

	public function getDue() {
		if (isset($this->data['due'])) {
			return $this->data['due'];
		}
		return 0;
	}

	public function getTime() {
		if (isset($this->data['urt'])) {
			return $this->data['urt'];
		}
		return NULL;
	}

	public function getAccountNo() {
		if (isset($this->data['aid'])) {
			return $this->data['aid'];
		}
		return NULL;
	}

	public function getIBAN() {
		if (isset($this->data['IBAN'])) {
			return $this->data['IBAN'];
		}
		return NULL;
	}

	public function getRUM() {
		if (isset($this->data['RUM'])) {
			return $this->data['RUM'];
		}
		return NULL;
	}

	public function getBankName() {
		if (isset($this->data['bank_name'])) {
			return $this->data['bank_name'];
		}
		return NULL;
	}

	public function getCustomerName() {
		if (isset($this->data['payer_name'])) {
			return $this->data['payer_name'];
		}
		return NULL;
	}

	public function getCustomerAddress() {
		if (isset($this->data['aaddress'])) {
			return $this->data['aaddress'];
		}
		return NULL;
	}

	public function getCustomerZipCode() {
		if (isset($this->data['azip'])) {
			return $this->data['azip'];
		}
		return NULL;
	}

	public function getCustomerCity() {
		if (isset($this->data['acity'])) {
			return $this->data['acity'];
		}
		return NULL;
	}

	public function getCustomerBankName() {
		if (isset($this->data['bank_name'])) {
			return $this->data['bank_name'];
		}
		return NULL;
	}

	public function save() {
		$this->data->save(NULL, 1);
		return true;
	}

	abstract public function getId();

	public static function getTotalDue($query = array(), $minBalance = false, $notFormatted = false) {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$query = array(
			'$match' => $query,
		);
		$project1 = array(
			'$project' => array(
				'aid' => 1,
				'waiting_for_confirmation' => 1,
				'due' => 1,
				'total2' => array('$cond' => array('if' => array('$ne' => array('$waiting_for_confirmation', true)), 'then' => '$due' , 'else' => 0)),

			),
		);
		$group = array(
			'$group' => array(
				'_id' => '$aid',
				'total' => array(
					'$sum' => '$due',
				),
				'total2' => array(
					'$sum' => '$total2',
				),
			),
		);
		
		$project2 = array(
			'$project' => array(
				'_id' => 0,
				'contractor_no' => '$_id',
				'total' => 1,
				'total2' =>  1,
			),
		);

		$having = array('$match' => array());
		if ($minBalance !== FALSE) {
			$having['$match']['total'] = array(
				'$gte' => $minBalance,
			);
		}
		if ($having['$match']) {
			$results = $billsColl->aggregate($query, $project1, $group, $project2, $having);
		} else {
			$results = $billsColl->aggregate($query,$project1, $group, $project2);
		}
		
		$results = iterator_to_array($results);
		if (!$notFormatted) {
			$results = array_map(function($ele) {
				$ele['total'] = Billrun_Util::getChargableAmount($ele['total']);
				$ele['total2'] = Billrun_Util::getChargableAmount($ele['total2']);
				return $ele;
			}, $results);
		}
		return array_combine(array_map(function($ele) {
				return $ele['contractor_no'];
			}, $results), $results);
	}

	public static function getTotalDueForAccount($aid, $notFormatted = false) {
		$query = array('aid' => $aid);
		$results = static::getTotalDue($query, $notFormatted);
		if (count($results)) {
			return array('total' => current($results)['total'], 'without_waiting' => current($results)['total2']);
		} else if ($notFormatted) {
			return 0;
		} else {
			return Billrun_Util::getChargableAmount(0);
		}
	}

	public static function payUnpaidBillsByOverPayingBills($aid) {
		$query = array(
			'aid' => $aid,
		);
		$sort = array(
			'urt' => 1,
		);
		$unpaidBills = Billrun_Bill::getUnpaidBills($query, $sort);
		$overPayingBills = Billrun_Bill::getOverPayingBills($query, $sort);
		foreach ($unpaidBills as $unpaidBillRaw) {
			$unpaidBill = Billrun_Bill::getInstanceByData($unpaidBillRaw);
			$unpaidBillLeft = $unpaidBill->getLeftToPay();
			foreach ($overPayingBills as $overPayingBill) {
				$payingBillAmountLeft = $overPayingBill->getLeft();
				if ($payingBillAmountLeft) {
					$amountPaid = min(array($unpaidBillLeft, $payingBillAmountLeft));
					$overPayingBill->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $amountPaid)->save();
					$unpaidBill->attachPayingBill($overPayingBill->getType(), $overPayingBill->getId(), $amountPaid)->save();
					$unpaidBillLeft -= $amountPaid;
				}
				if (abs($unpaidBillLeft) < static::precision) {
					break;
				}
			}
		}
	}

	public static function getUnpaidBills($query = array(), $sort = array()) {
		$mandatoryQuery = static::getUnpaidQuery();
		$query = array_merge($query, $mandatoryQuery);
		return static::getBills($query, $sort);
	}

	protected function updateLeft() {
		if ($this->getDue() < 0) {
			$this->data['left'] = $this->getAmount();
			foreach ($this->getPaidBills() as $paidBills) {
				$this->data['left'] -= array_sum($paidBills);
			}
			if (abs($this->data['left']) < Billrun_Bill::precision) {
				$this->data['left'] = 0;
			}
		}
	}

	public function getPaidByBills() {
		return isset($this->data['paid_by']) ? $this->data['paid_by'] : array();
	}

	/**
	 * Get bills awaiting to be paid
	 * @return array
	 */
	public static function getUnpaidQuery() {
		return array_merge(array('due' => array('$gt' => 0,), 'paid' => array('$ne' => TRUE,),), static::getNotRejectedOrCancelledQuery()
		);
	}

	public static function getBills($query = array(), $sort = array()) {
		$billsColl = Billrun_Factory::db()->billsCollection();
		return iterator_to_array($billsColl->find($query)->sort($sort), FALSE);
	}

	public static function getOverPayingBills($query = array(), $sort = array()) {
		$billObjs = array();
		$query = array_merge($query, array('left' => array('$gt' => 0,)), static::getNotRejectedOrCancelledQuery());
		$bills = static::getBills($query, $sort);
		if ($bills) {
			foreach ($bills as $bill) {
				$billObjs[] = static::getInstanceByData($bill);
			}
		}
		return $billObjs;
	}

	/**
	 * 
	 * @param Mongodloid_Entity|array $data
	 * @return Billrun_Bill
	 */
	public static function getInstanceByData($data) {
		if ($data['type'] == 'inv') {
			return Billrun_Bill_Invoice::getInstanceByData($data);
		} else if ($data['type'] == 'rec') {
			return Billrun_Bill_Payment::getInstanceByData($data);
		} else {
			throw new Exception('Unknown bill type');
		}
	}

	public function reduceLeft($byAmount = 0) {
		if ($byAmount) {
			$left = $this->getLeft();
			if ($left >= $byAmount) {
				$this->data['left'] = $left - $byAmount;
			}
		}
		return $this;
	}

	public function getLeft() {
		return isset($this->data['left']) ? $this->data['left'] : 0;
	}

	public function detachPaidBills() {
		foreach ($this->getPaidBills() as $billType => $bills) {
			foreach (array_keys($bills) as $billId) {
				$billObj = Billrun_Bill::getInstanceByTypeAndid($billType, $billId);
				$billObj->detachPayingBill($this->getType(), $this->getId())->save();
			}
		}
	}

	public function getType() {
		return $this->type;
	}

	public function getPaidAmount() {
		return isset($this->data['total_paid']) ? $this->data['total_paid'] : 0;
	}

	protected function recalculatePaymentFields() {
		if ($this->getDue() > 0) {
			$amount = 0;
			if (isset($this->data['paid_by']['inv'])) {
				$amount += array_sum($this->data['paid_by']['inv']);
			}
			if (isset($this->data['paid_by']['rec'])) {
				$amount += array_sum($this->data['paid_by']['rec']);
			}
			$this->data['total_paid'] = $amount;
			$this->data['vatable_left_to_pay'] = min($this->getLeftToPay(), $this->getDueBeforeVat());
			$this->data['paid'] = $this->isPaid();
		}
		return $this;
	}

	/**
	 * 
	 * @param int $id
	 * @return Billrun_Bill_Invoice
	 */
	public static function getInstanceByTypeAndid($type, $id) {
		if ($type == 'inv') {
			return Billrun_Bill_Invoice::getInstanceByid($id);
		} else if ($type == 'rec') {
			return Billrun_Bill_Payment::getInstanceByid($id);
		}
		throw new Exception('Unknown bill type');
	}

	public function attachPayingBill($billType, $billId, $amount) {
		if ($amount) {
			$paidBy = $this->getPaidByBills();
			$paidBy[$billType][$billId] = (isset($paidBy[$billType][$billId]) ? $paidBy[$billType][$billId] : 0) + $amount;
			$this->updatePaidBy($paidBy);
		}
		return $this;
	}

	public function detachPayingBill($billType, $id) {
		$paidBy = $this->getPaidByBills();
		unset($paidBy[$billType][$id]);
		$this->updatePaidBy($paidBy);
		return $this;
	}

	protected function updatePaidBy($paidBy) {
		if ($this->getDue() > 0) {
			$this->data['paid_by'] = $paidBy;
			$this->recalculatePaymentFields();
		}
	}

	public function attachPaidBill($billType, $billId, $amount) {
		$paymentRawData = $this->data->getRawData();
		$paymentRawData['pays'][$billType][$billId] = (isset($paymentRawData['pays'][$billType][$billId]) ? $paymentRawData['pays'][$billType][$billId] : 0) + $amount;
		$this->data->setRawData($paymentRawData);
		$this->updateLeft();
		return $this;
	}

	public function getPaidBills() {
		return isset($this->data['pays']) ? $this->data['pays'] : array();
	}

	public static function getNotRejectedOrCancelledQuery() {
		return array(
			'rejected' => array(
				'$ne' => TRUE,
			),
			'rejection' => array(
				'$ne' => TRUE,
			),
			'cancelled' => array(
				'$ne' => TRUE,
			),
			'cancel' => array(
				'$exists' => FALSE,
			),
		);
	}

	public static function getContractorsInCollection($aids = array()) {
		$subscriber = Billrun_Factory::subscriber();
		$exempted = $subscriber->getExcludedFromCollection($aids);
		$query = array(
			'$or' => array(
				array(
					'type' => 'inv',
					'due_date' => array(
						'$lt' => new MongoDate(),
					)
				),
				array(
					'type' => 'rec',
				),
			),
		);
		if ($aids) {
			$query['aid']['$in'] = $aids;
		}
		if ($exempted) {
			$query['aid']['$nin'] = array_keys($exempted);
		}
		$minBalance = floatval(Billrun_Factory::config()->getConfigValue('collection.min_debt', 60.005));
		return static::getTotalDue($query, $minBalance, TRUE);
	}

	public function getDueBeforeVat() {
		return isset($this->data['due_before_vat']) ? $this->data['due_before_vat'] : 0;
	}

	public static function getVatableLeftToPay($aid) {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$query = array(
			'$match' => array(
				'aid' => $aid,
				'vatable_left_to_pay' => array(
					'$exists' => TRUE,
				)
			),
		);
		$group = array(
			'$group' => array(
				'_id' => 1,
				'vatable_left_to_pay' => array(
					'$sum' => '$vatable_left_to_pay',
				),
			),
		);
		$results = $billsColl->aggregate($query, $group);
		if ($results) {
			return $results[0]['vatable_left_to_pay'];
		} else {
			return 0;
		}
	}

	public function getLeftToPay() {
		return floatval($this->getDue() - $this->getPaidAmount());
	}

	public function isPaid() {
		return $this->getDue() <= ($this->getPaidAmount() + static::precision);
	}
	
	public static function pay($method, $paymentsArr, $options = array()) {
		$involvedAccounts = $payments = array();
		if (in_array($method, array('cheque', 'wire_transfer', 'cash', 'credit', 'write_off', 'debit'))) {
			$className = Billrun_Bill_Payment::getClassByPaymentMethod($method);
			foreach ($paymentsArr as $rawPayment) {
				$aid = intval($rawPayment['aid']);
				$dir = Billrun_Util::getFieldVal($rawPayment['dir'], null);
				if ($dir == 'fc' || is_null($dir)) { // attach invoices to payments and vice versa
					if (!empty($rawPayment['pays']['inv'])) {
						$paidInvoices = $rawPayment['pays']['inv']; // currently it is only possible to specifically pay invoices only and not payments
						$invoices = Billrun_Bill_Invoice::getInvoices(array('aid' => $aid, 'invoice_id' => array('$in' => Billrun_Util::verify_array(array_keys($paidInvoices), 'int'))));
						if (count($invoices) != count($paidInvoices)) {
							throw new Exception('Unknown invoices for account ' . $aid);
						}
						if (($rawPayment['amount'] - array_sum($paidInvoices)) <= -Billrun_Bill::precision) {
							throw new Exception($aid . ': Total to pay is less than the subtotals');
						}
						foreach ($invoices as $invoice) {
							$invoiceObj = Billrun_Bill_Invoice::getInstanceByData($invoice);
							if ($invoiceObj->isPaid()) {
								throw new Exception('Invoice ' . $invoiceObj->getId() . ' already paid');
							}
							if (!is_numeric($rawPayment['pays']['inv'][$invoiceObj->getId()])) {
								throw new Exception('Illegal amount ' . $rawPayment['pays']['inv'][$invoiceObj->getId()] . ' for invoice ' . $invoiceObj->getId());
							} else {
								$invoiceAmountToPay = floatval($paidInvoices[$invoiceObj->getId()]);
							}
							if ((($leftToPay = $invoiceObj->getLeftToPay()) < $invoiceAmountToPay) && (number_format($leftToPay, 2) != number_format($invoiceAmountToPay, 2))) {
								throw new Exception('Invoice ' . $invoiceObj->getId() . ' cannot be overpaid');
							}
							$updateBills['inv'][$invoiceObj->getId()] = $invoiceObj;
						}
					} else {
						$leftToSpare = floatval($rawPayment['amount']);
						$unpaidBills = Billrun_Bill::getUnpaidBills(array('aid' => $aid));
						foreach ($unpaidBills as $rawUnpaidBill) {
							$unpaidBill = Billrun_Bill::getInstanceByData($rawUnpaidBill);
							$invoiceAmountToPay = min($unpaidBill->getLeftToPay(), $leftToSpare);
							if ($invoiceAmountToPay) {
								$billType = $unpaidBill->getType();
								$billId = $unpaidBill->getId();
								$leftToSpare -= $rawPayment['pays'][$billType][$billId] = $invoiceAmountToPay;
								$updateBills[$billType][$billId] = $unpaidBill;
							}
						}
					}
				}
				$involvedAccounts[] = $aid;
				$payments[] = new $className($rawPayment);
			}
			$res = Billrun_Bill_Payment::savePayments($payments);
			if ($res && isset($res['ok']) && $res['ok']) {
				foreach ($payments as $payment) {
					if ($payment->getDir() == 'fc') {
						foreach ($payment->getPaidBills() as $billType => $bills) {
							foreach ($bills as $billId => $amountPaid) {
								$updateBills[$billType][$billId]->attachPayingBill($payment->getType(), $payment->getId(), $amountPaid)->save();
						}
					}
					} else {
						Billrun_Bill::payUnpaidBillsByOverPayingBills($payment->getAccountNo());
					}
				}
				if (!isset($options['collect']) || $options['collect']) {
					$involvedAccounts = array_unique($involvedAccounts);
//					CollectAction::collect($involvedAccounts);
				}
				if (isset($options['payment_gateway'])) {
					$gatewayDetails = $options['payment_gateway'];
					$gatewayName = $gatewayDetails['name'];
					$gateway = Billrun_PaymentGateway::getInstance($gatewayName);
					$paymentStatus = $gateway->pay($gatewayDetails);
					$responseFromGateway = Billrun_PaymentGateway::checkPaymentStatus($paymentStatus, $gateway);
					$txId = $gateway->getTransactionId();	
					$currentPayment = $payments[0];
					$currentPayment->updateDetailsForPaymentGateway($gatewayName, $txId);
				}	
			} else {
				throw new Exception('Error encountered while saving the payments');
			}
		} else {
			throw new Exception('Unknown payment method');
		}
		if (isset($options['payment_gateway'])){
			return array('payment' => $payments, 'response' => $responseFromGateway);
		} else { 
			return $payments;
		}
			
	}
}
