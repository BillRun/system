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
		$this->updateLeftToPay();
		$this->recalculatePaymentFields();
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
				'waiting_for_confirmation_total' => array('$cond' => array('if' => array('$ne' => array('$waiting_for_confirmation', true)), 'then' => '$due' , 'else' => 0)),
				'pending_total' => array('$cond' => array('if' => array('$eq' => array('$pending', true)), 'then' => '$due' , 'else' => 0)),

			),
		);
		$group = array(
			'$group' => array(
				'_id' => '$aid',
				'total' => array(
					'$sum' => '$due',
				),
				'waiting_for_confirmation_total' => array(
					'$sum' => '$waiting_for_confirmation_total',
				),
				'pending_total' => array(
					'$sum' => '$pending_total',
				),
			),
		);
		
		$project2 = array(
			'$project' => array(
				'_id' => 0,
				'aid' => '$_id',
				'total' => 1,
				'waiting_for_confirmation_total' =>  1,
				'pending_total' => 1
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
				$ele['waiting_for_confirmation_total'] = Billrun_Util::getChargableAmount($ele['waiting_for_confirmation_total']);
				$ele['pending_total'] = Billrun_Util::getChargableAmount($ele['pending_total']);
				return $ele;
			}, $results);
		}
		return array_combine(array_map(function($ele) {
				return $ele['aid'];
			}, $results), $results);
	}

	/**
	 * Return total / total waiting due for account
	 * @param int $aid
	 * @param boolean $notFormatted
	 * @return array
	 */
	public static function getTotalDueForAccount($aid, $date = null, $notFormatted = false, $include_future_chargeable = false) {
		$query = Billrun_Bill::getNotRejectedOrCancelledQuery();
		$query['aid'] = $aid;
		if (!empty($date)) {
			$relative_date = new Mongodloid_Date(strtotime($date));
			if (!$include_future_chargeable) {
				$query['$or'] = array(
					array('charge.not_before' => array('$exists' => true, '$lte' => $relative_date)),
					array('charge.not_before' => array('$exists' => false), 'urt' => array('$exists' => true , '$lte' => $relative_date)),
					array('charge.not_before' => array('$exists' => false), 'urt' => array('$exists' => false))
				);
			} else {
				$query['urt'] = array('$lte' => $relative_date);
			}
		}
		$results = static::getTotalDue($query, $notFormatted);
		if (count($results)) {
			$total =  current($results)['total'];
			$totalWaiting = current($results)['waiting_for_confirmation_total'];
			$totalPending = abs(current($results)['pending_total']);
		} else if ($notFormatted) {
			$total = $totalWaiting = $totalPending = 0;
		} else {
			$total = $totalWaiting = $totalPending = Billrun_Util::getChargableAmount(0);
		}
		return array('total' => $total, 'without_waiting' => $totalWaiting, 'total_pending_amount' => $totalPending);
	}

	public static function payUnpaidBillsByOverPayingBills($aid, $sortByUrt = true) {
		$query = array(
			'aid' => $aid,
		);
		$sort = array(
			'urt' => 1,
		);
		$unpaidBills = Billrun_Bill::getUnpaidBills($query, $sort);
		$overPayingBills = Billrun_Bill::getOverPayingBills($query, $sort);
		foreach ($unpaidBills as $key1 => $unpaidBillRaw) {
			$unpaidBill = Billrun_Bill::getInstanceByData($unpaidBillRaw);
			$unpaidBillLeft = $unpaidBill->getLeftToPay();
			foreach ($overPayingBills as $key2 => $overPayingBill) {
				$payingBillAmountLeft = $overPayingBill->getLeft();
				if ($payingBillAmountLeft && (Billrun_Util::isEqual($unpaidBillLeft, $payingBillAmountLeft, static::precision))) {
					$overPayingBill->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $payingBillAmountLeft, $unpaidBill->getRawData())->save();
					$unpaidBill->attachPayingBill($overPayingBill, $payingBillAmountLeft)->save();
					unset($unpaidBills[$key1]);
					unset($overPayingBills[$key2]);
					break;
				}
			}
		}
		foreach ($unpaidBills as $unpaidBillRaw) {
			$unpaidBill = Billrun_Bill::getInstanceByData($unpaidBillRaw);
			$unpaidBillLeft = $unpaidBill->getLeftToPay();
			foreach ($overPayingBills as $overPayingBill) {
				$payingBillAmountLeft = $overPayingBill->getLeft();
				if ($payingBillAmountLeft) {
					$amountPaid = min(array($unpaidBillLeft, $payingBillAmountLeft));
					$overPayingBill->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $amountPaid, $unpaidBill->getRawData())->save();
					$unpaidBill->attachPayingBill($overPayingBill, $amountPaid)->save();
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
		if ($this->getDue() < 0 && ($this->getBillMethod() != 'denial')) {
			$this->data['left'] = $this->getAmount();
			foreach ($this->getPaidBills() as $paidBill) {
				$this->data['left'] -= $paidBill['amount'];
			}
			if (abs($this->data['left']) < Billrun_Bill::precision) {
				$this->data['left'] = 0;
			}
		}
	}
		
	protected function updateLeftToPay() {
		if ($this->getDue() > 0 && ($this->getBillMethod() != 'denial')) {
			$this->data['left_to_pay'] = $this->getAmount();
			foreach ($this->getPaidByBills() as $paidByBill) {
				$this->data['left_to_pay'] -= floatval($paidByBill['amount']);
			}
			if ($this->data['left_to_pay'] < Billrun_Bill::precision) {
				$this->data['left_to_pay'] = 0;
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
		return array_merge(array('due' => array('$gt' => 0,), 'left_to_pay' => array('$gt' => 0), 'paid' => array('$nin' => array(TRUE, '1', '2'),),), static::getNotRejectedOrCancelledQuery()
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
		foreach ($this->getPaidBills() as $bill) {
			$billObj = Billrun_Bill::getInstanceByTypeAndid($bill['type'], $bill['id']);
				$billObj->detachPayingBill($this->getType(), $this->getId())->save();
			}
		}
	
	public function detachPayingBills() {
		foreach ($this->getPaidByBills() as $bill) {
			$billObj = Billrun_Bill::getInstanceByTypeAndid($bill['type'], $bill['id']);
				$billObj->detachPaidBill($this->getType(), $this->getId())->save();
			}
		}

	public function getType() {
		return $this->type;
	}

	public function getPaidAmount() {
		return isset($this->data['total_paid']) ? $this->data['total_paid'] : 0;
	}

	protected function recalculatePaymentFields($billId = null, $status = null, $billType = null) {
		if ($this->getBillMethod() == 'denial') {
			return $this;
		}
		if ($this->getDue() > 0) {
			$amount = 0;
			foreach (Billrun_Util::getIn($this->data, 'paid_by', []) as $relatedBill) {
				$amount += floatval($relatedBill['amount']);
			}
			$this->data['total_paid'] = $amount;
			$this->data['left_to_pay'] = round($this->getLeftToPay(), 2);
			$this->data['vatable_left_to_pay'] = min($this->getLeftToPay(), $this->getDueBeforeVat());
			if (is_null($status)){
				$this->data['paid'] = $this->isPaid();
			} else {
				$this->data['paid'] = $this->calcPaidStatus($billId, $status, $billType);
			}
		} else if ($this->getDue() < 0){
			$amount = 0;
			foreach (Billrun_Util::getIn($this->data, 'pays', []) as $relatedBill) {
				$amount += floatval($relatedBill['amount']);
			}
			$this->data['left'] = round($this->data['amount'] - $amount, 2);				
		}
		$this->setPendingCoveringAmount();
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

	public function attachPayingBill($bill, $amount, $status = null) {
		$billId = $bill->getId();
		$billType = $bill->getType();
		if ($amount) {
			$paidBy = $this->getPaidByBills();
			$relatedBillId = Billrun_Bill::findRelatedBill($paidBy, $billType, $billId);
			if ($relatedBillId == -1) {
				Billrun_Bill::addRelatedBill($paidBy, $billType, $billId, $amount, $bill->getRawData());
			} else {
				$paidBy[$relatedBillId]['amount'] += floatval($amount);
			}
			if ($bill->isPendingPayment()) {
				$this->addToWaitingPayments($billId, $billType);
			}
			if ($status == 'Rejected') {
				$this->addToRejectedPayments($billId, $billType);
			}
			$this->updatePaidBy($paidBy, $billId, $status, $billType);
			if ($bill->isPendingPayment()) {
				$this->setPendingLinkedBills($billType, $billId);
                                $bill->setPendingLinkedBills($this->getType(), $this->getId());                               
			}
		}
		$this->setPendingCoveringAmount();
		return $this;
	}

	public function detachPayingBill($billType, $id) {
		$paidBy = $this->getPaidByBills();
		$index = Billrun_Bill::findRelatedBill($paidBy, $billType, $id);
		if ($index > -1) {
			unset($paidBy[$index]);
			$this->updatePaidBy(array_values($paidBy));
			if ($billType == 'rec') {
				$this->removeFromWaitingPayments($id, $billType);
			}
		}
		$this->setPendingCoveringAmount();
		return $this;
	}
	
	public function detachPaidBill($billType, $id) {
		$pays = $this->getPaidBills();
		$index = Billrun_Bill::findRelatedBill($pays, $billType, $id);
		if ($index > -1) {
			unset($pays[$index]);
			$this->updatePays(array_values($pays));
		}
		$this->setPendingCoveringAmount();
		return $this;
	}

	protected function updatePaidBy($paidBy, $billId = null, $status = null, $billType = null) {
		if ($this->getDue() > 0 || $this->isRejection() || $this->isCancellation()) {
			$this->data['paid_by'] = $paidBy;
			$this->recalculatePaymentFields($billId, $status, $billType);
		}
	}
	
	protected function updatePays($pays, $billId = null) {
		if ($this->getDue() < 0) {
			$this->data['pays'] = $pays;
			$this->recalculatePaymentFields();
		}
	}

	public function attachPaidBill($billType, $billId, $amount, $bill) {
		$paymentRawData = $this->data->getRawData();
		if(!isset($paymentRawData['pays'])){
			$paymentRawData['pays'] = [];
		}
		$relatedBillId = Billrun_Bill::findRelatedBill($paymentRawData['pays'], $billType, $billId);
		if ($relatedBillId == -1) {
			Billrun_Bill::addRelatedBill($paymentRawData['pays'], $billType, $billId, $amount, $bill);
		} else {
			$paymentRawData['pays'][$relatedBillId]['amount'] += floatval($amount);
		}
		$this->data->setRawData($paymentRawData);
		$this->updateLeft();
		$this->setPendingCoveringAmount();
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
			'is_denial' => array(
				'$ne' => TRUE,
			),
			'denied_by' => array(
				'$exists' => FALSE,
			),
		);
	}

	public static function getContractorsInCollection($aids = array()) {
		$account = Billrun_Factory::account();
		$exempted = $account->getExcludedFromCollection($aids);
		$subject_to = $account->getIncludedInCollection($aids);

		// white list exists but aids not included
		if (!is_null($subject_to) && empty($subject_to)) {
			return [];
		}
		// white list exists and aids included
		if (!is_null($subject_to) && !empty($subject_to)) {
			$aids = $subject_to;
		}
		
		
		if (!empty($aids)) {
			$aidsQuery = array('aid' => array('$in' => $aids));
		} else if (!empty($exempted)){
			$aidsQuery = array('aid' => array('$nin' => $aids));
		} else {
			$aidsQuery = array();
		}

		return static::getBalanceByAids($aidsQuery, true, true, true);
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
		if (in_array($method, array('automatic', 'cheque', 'wire_transfer', 'cash', 'credit', 'write_off', 'debit', 'installment_agreement', 'merge_installments'))) {
			$className = Billrun_Bill_Payment::getClassByPaymentMethod($method);
			foreach ($paymentsArr as $rawPayment) {
				$aid = intval($rawPayment['aid']);
				$dir = Billrun_Util::getFieldVal($rawPayment['dir'], null);			
				if (in_array($dir, array('fc', 'tc')) || is_null($dir)) { // attach invoices to payments and vice versa
					if (!empty($rawPayment['pays'])) {
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
						}
						if (!empty($rawPayment['pays']['rec'])) {
							$paidInvoices = $rawPayment['pays']['rec'];
							$invoices = Billrun_Bill_Payment::queryPayments(array('aid' => $aid, 'txid' => array('$in' => array_keys($paidInvoices))));
							if (count($invoices) != count($paidInvoices)) {
								throw new Exception('Unknown payments for account ' . $aid);
							}
							if (($rawPayment['amount'] - array_sum($paidInvoices)) <= -Billrun_Bill::precision) {
								throw new Exception($aid . ': Total to pay is less than the subtotals');
							}
							foreach ($invoices as $invoice) {
								$invoiceObj = Billrun_Bill_Payment::getInstanceByData($invoice);
								if ($invoiceObj->isPaid()) {
									throw new Exception('Payment ' . $invoiceObj->getId() . ' already paid');
								}
								if (!is_numeric($rawPayment['pays']['rec'][$invoiceObj->getId()])) {
									throw new Exception('Illegal amount ' . $rawPayment['pays']['rec'][$invoiceObj->getId()] . ' for payment ' . $invoiceObj->getId());
								} else {
									$invoiceAmountToPay = floatval($paidInvoices[$invoiceObj->getId()]);
								}
								if ((($leftToPay = $invoiceObj->getLeftToPay()) < $invoiceAmountToPay) && (number_format($leftToPay, 2) != number_format($invoiceAmountToPay, 2))) {
									throw new Exception('Payment ' . $invoiceObj->getId() . ' cannot be overpaid');
								}
								$updateBills['rec'][$invoiceObj->getId()] = $invoiceObj;
							}
						}
					} else if (!empty($rawPayment['paid_by'])) {
						if (!empty($rawPayment['paid_by']['inv'])) {
							$paidBy = $rawPayment['paid_by']['inv'];
							$invoices = Billrun_Bill_Invoice::getInvoices(array('aid' => $aid, 'invoice_id' => array('$in' => Billrun_Util::verify_array(array_keys($paidBy), 'int'))));
							if (count($invoices) != count($paidBy)) {
								throw new Exception('Unknown invoices for account ' . $aid);
							}
							if (($rawPayment['amount'] - array_sum($paidBy)) <= -Billrun_Bill::precision) {
								throw new Exception($aid . ': Total to pay is less than the subtotals');
							}
							foreach ($invoices as $invoice) {
								$invoiceObj = Billrun_Bill_Invoice::getInstanceByData($invoice);
								if (!is_numeric($rawPayment['paid_by']['inv'][$invoiceObj->getId()])) {
									throw new Exception('Illegal amount ' . $rawPayment['paid_by']['inv'][$invoiceObj->getId()] . ' for invoice ' . $invoiceObj->getId());
								} else {
									$invoiceAmountToPay = floatval($paidBy[$invoiceObj->getId()]);
								}
								if ((($left = $invoiceObj->getLeft()) < $invoiceAmountToPay) && (number_format($left, 2) != number_format($invoiceAmountToPay, 2))) {
									throw new Exception('Invoice ' . $invoiceObj->getId() . ' Credit was exhausted when paying bills');
								}
								$updateBills['inv'][$invoiceObj->getId()] = $invoiceObj;
							}
						}
						if (!empty($rawPayment['paid_by']['rec'])) {
							$paidBy = $rawPayment['paid_by']['rec'];
							$invoices =  Billrun_Bill_Payment::queryPayments(array('aid' => $aid, 'txid' => array('$in' => array_keys($paidBy))));
							if (count($invoices) != count($paidBy)) {
								throw new Exception('Unknown payments for account ' . $aid);
							}
							if (($rawPayment['amount'] - array_sum($paidBy)) <= -Billrun_Bill::precision) {
								throw new Exception($aid . ': Total to pay is less than the subtotals');
							}
							foreach ($invoices as $invoice) {
								$invoiceObj = Billrun_Bill_Payment::getInstanceByData($invoice);
								if (!is_numeric($rawPayment['paid_by']['rec'][$invoiceObj->getId()])) {
									throw new Exception('Illegal amount ' . $rawPayment['paid_by']['rec'][$invoiceObj->getId()] . ' for payment ' . $invoiceObj->getId());
								} else {
									$invoiceAmountToPay = floatval($paidBy[$invoiceObj->getId()]);
								}
								if ((($left = $invoiceObj->getLeft()) < $invoiceAmountToPay) && (number_format($left, 2) != number_format($invoiceAmountToPay, 2))) {
									throw new Exception('Payment ' . $invoiceObj->getId() . ' Credit was exhausted when paying bills');
								}
								$updateBills['rec'][$invoiceObj->getId()] = $invoiceObj;
							}	
						}
					} else if ($rawPayment['dir'] == 'fc') {
						$leftToSpare = floatval($rawPayment['amount']);
						$sort = array(
							'urt' => 1,
						);
						$unpaidBills = Billrun_Bill::getUnpaidBills(array('aid' => $aid), $sort);
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
					} else if ($rawPayment['dir'] == 'tc') {
						$leftToSpare = floatval($rawPayment['amount']);
						$sort = array(
							'urt' => 1,
						);
						$overPayingBills = Billrun_Bill::getOverPayingBills(array('aid' => $aid), $sort);
						foreach ($overPayingBills as $overPayingBill) {
							$credit = min($overPayingBill->getLeft(), $leftToSpare);
							if ($credit) {
								$billType = $overPayingBill->getType();
								$billId = $overPayingBill->getId();
								$leftToSpare -= $rawPayment['paid_by'][$billType][$billId] = $credit;
								$updateBills[$billType][$billId] = $overPayingBill;
							}
						}
					}
				}
				$involvedAccounts[] = $aid;
				if (!empty($options['file_based_charge']) && isset($options['generated_pg_file_log'])) {
					$rawPayment['generated_pg_file_log'] = $options['generated_pg_file_log'];
				}
				$payments[] = new $className($rawPayment);
			}
			$res = Billrun_Bill_Payment::savePayments($payments);
			if ($res && isset($res['ok']) && $res['ok']) {
				if (isset($options['payment_gateway']) && $options['payment_gateway']) {
					$responsesFromGateway = array();
					$paymentSuccess = array();
					foreach ($payments as $payment) {
						$gatewayDetails = $payment->getPaymentGatewayDetails();
						$gatewayName = $gatewayDetails['name'];
						$gatewayInstanceName = $gatewayDetails['instance_name'];
						$gateway = Billrun_PaymentGateway::getInstance($gatewayInstanceName);
						if (is_null($gateway)) {
							Billrun_Factory::log("Illegal payment gateway object", Zend_Log::ALERT);
						} else {
							Billrun_Factory::log("Paying bills through " . $gatewayName, Zend_Log::INFO);
							Billrun_Factory::log("Charging payment gateway details: " . "name=" . $gatewayName . ", amount=" . $gatewayDetails['amount'] . ', charging account=' . $aid, Zend_Log::DEBUG);
						}
						if (empty($options['single_payment_gateway'])) {
							try {
								$payment->setPending(true);
								$addonData = array('aid' => $payment->getAid(), 'txid' => $payment->getId());
								$paymentStatus = $gateway->makeOnlineTransaction($gatewayDetails, $addonData);
							} catch (Exception $e) {
								$payment->setGatewayChargeFailure($e->getMessage());
								$responseFromGateway = array('status' => $e->getCode(), 'stage' => "Rejected");
								Billrun_Factory::log('Failed to pay bill: ' . $e->getMessage(), Zend_Log::ALERT);
								continue;
							}
						} else {
							$paymentStatus = array(
								'status' => $payment->getSinglePaymentStatus(),
								'additional_params' => isset($options['additional_params']) ? $options['additional_params'] : array()
							);
							if (empty($paymentStatus['status'])) {
								throw new Exception("Missing status from gateway for single payment");
							}
						}
						$responseFromGateway = Billrun_PaymentGateway::checkPaymentStatus($paymentStatus['status'], $gateway, $paymentStatus['additional_params']);
						$txId = $gateway->getTransactionId();
						$payment->updateDetailsForPaymentGateway($gatewayName, $txId);
						$paymentSuccess[] = $payment;
						$responsesFromGateway[$txId] = $responseFromGateway;
					}
				} else {
					$paymentSuccess = $payments;
				}
				foreach ($paymentSuccess as $payment) {
					$paymantData = $payment->getRawData();
					$transactionId = isset($paymantData['payment_gateway']['transactionId']) ? $paymantData['payment_gateway']['transactionId'] : null;
					if (isset($paymantData['payment_gateway']) && empty($transactionId)) {
						throw new Exception('Illegal transaction id for aid ' . $paymantData['aid'] . ' in response from ' . $gatewayName);
					}
					if ($payment->getDir() == 'fc') {
						foreach ($payment->getPaidBills() as $bill) {
								if (isset($options['file_based_charge']) && $options['file_based_charge']) {
									$payment->setPending(true);
								}
								if (isset($responsesFromGateway[$transactionId]) && $responsesFromGateway[$transactionId]['stage'] != 'Pending') {
									$payment->setPending(false);
								}
							$updateBills[$bill['type']][$bill['id']]->attachPayingBill($payment, $bill['amount'], empty($responsesFromGateway[$transactionId]['stage']) ? 'Completed' : $responsesFromGateway[$transactionId]['stage'])->save();
						}
					} else if ($payment->getDir() == 'tc') {
						foreach ($payment->getPaidByBills() as $bill) {
								if (isset($options['file_based_charge']) && $options['file_based_charge']) {
									$payment->setPending(true);
								}
								if (isset($responsesFromGateway[$transactionId]) && $responsesFromGateway[$transactionId]['stage'] != 'Pending') {
									$payment->setPending(false);
								}
							$updateBills[$bill['type']][$bill['id']]->attachPaidBill($payment->getType(), $payment->getId(), $bill['amount'], $payment->getRawData())->save();
						}
					} else {
						Billrun_Bill::payUnpaidBillsByOverPayingBills($payment->getAccountNo());
					}

					$involvedAccounts = array_unique($involvedAccounts);
					if (!empty($gatewayDetails)) {
						$gatewayAmount = isset($gatewayDetails['amount']) ? $gatewayDetails['amount'] : $gatewayDetails['transferred_amount'];
					}
					if (!empty($responsesFromGateway[$transactionId]) && $responsesFromGateway[$transactionId]['stage'] == 'Completed' && ($gatewayAmount < (0 - Billrun_Bill::precision))) {
						Billrun_Factory::dispatcher()->trigger('afterRefundSuccess', array($payment->getRawData()));
					}
					if (!empty($responsesFromGateway[$transactionId]) && $responsesFromGateway[$transactionId]['stage'] == 'Completed' && ($gatewayAmount > (0 + Billrun_Bill::precision))) {
						Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($payment->getRawData()));
					}
					if (empty($responsesFromGateway[$transactionId]) && !isset($options['file_based_charge']) && $payment->getDue() > 0) { // offline payment
						Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($payment->getRawData()));
					}
				}
			} else {
				throw new Exception('Error encountered while saving the payments');
			}
		} else {
			throw new Exception('Unknown payment method');
		}
		if (isset($options['payment_gateway'])) {
			return array('payment' => $payments, 'response' => $responsesFromGateway);
		} else {
			return $payments;
		}
	}

	protected function calcPaidStatus($billId = null, $status = null, $billType = null) {
		if (is_null($billId) || is_null($status)){
			return;
		}
		switch ($status) {
			case 'Rejected':
				$result = '0';
				$this->removeFromWaitingPayments($billId, $billType);
				break;

			case 'Completed':
				$pending = $this->data['waiting_payments'];
				if (!empty($pending)) {
					$this->removeFromWaitingPayments($billId, $billType);
					$result = count($this->data['waiting_payments']) ? '2' : ($this->isPaid() ? '1' : '0');
				}
				else {
					$result = $this->isPaid() ? '1' : '0'; 
				}
				break;

			case 'Pending':
				$result = '2';
				break;
			default:
				$result = '0';
				break;
		}
		
		return $result;
	}
	
	protected function addToWaitingPayments($billId, $billType) {
		if ($billType == 'inv') {
			return;
		}
		$waiting_payments = isset($this->data['waiting_payments']) ? $this->data['waiting_payments'] : array();
		array_push($waiting_payments, $billId);
		$this->data['waiting_payments'] = array_unique($waiting_payments);
	}
	
	protected function setPendingLinkedBills($billType, $billId) {
		$paidBy = $this->getPaidByBills();
		$index = Billrun_Bill::findRelatedBill($paidBy, $billType, $billId);
		if ($index > -1) {
			$paidBy[$index]['pending'] = true;
			$this->data['paid_by'] = array_values($paidBy);
		} else {
			$pays = $this->getPaidBills();
			$index = Billrun_Bill::findRelatedBill($pays, $billType, $billId);
			if ($index > -1) {
				$pays[$index]['pending'] = true;
				$this->data['pays'] = array_values($pays);
			}
		}
	}

	protected function unsetPendingLinkedBills($billType, $billId) {
		$paidBy = $this->getPaidByBills();
		$index = Billrun_Bill::findRelatedBill($paidBy, $billType, $billId);
		if ($index > -1) {
			unset($paidBy[$index]['pending']);
			$this->data['paid_by'] = array_values($paidBy);
		} else {
			$pays = $this->getPaidBills();
			$index = Billrun_Bill::findRelatedBill($pays, $billType, $billId);
			if ($index > -1) {
				unset($pays[$index]['pending']);
				$this->data['pays'] = array_values($pays);
			}
		}
	}
	
	/**
	 * This method assumes paid_by / pays field are already updated with the correct pending status
	 */
	protected function setPendingCoveringAmount() {
		$this->data['pending_covering_amount'] = 0;
		foreach (array('pays', 'paid_by') as $key) {
			if (isset($this->data[$key])) {
				$this->data['pending_covering_amount'] += array_sum(array_column(array_filter($this->data[$key], array($this, 'isPendingLink')), 'amount'));
			}
		}
	}

	protected function isPendingLink($link) {
		return !empty($link['pending']);
	}

	protected function addToRejectedPayments($billId, $billType) {
		if ($billType == 'inv') {
			return;
		}
		$rejectedPayments = isset($this->data['past_rejections']) ? $this->data['past_rejections'] : array();
		array_push($rejectedPayments, $billId);
		$this->data['past_rejections'] = $rejectedPayments;
	}

	protected function removeFromWaitingPayments($billId, $billType) {
		if ($billType == 'rec') {
			$pending = isset($this->data['waiting_payments']) ? $this->data['waiting_payments'] : array();
			$key = array_search($billId, $pending);
			if ($key !== false) {
				unset($pending[$key]);
			}
			$this->data['waiting_payments'] = $pending;
			$this->unsetPendingLinkedBills($billType, $billId);
		}
	}

	public function updatePendingBillToConfirmed($billId, $status, $billType) {
		$paidBy = $this->getPaidByBills();
		$this->updatePaidBy($paidBy, $billId, $status, $billType);
		return $this;
	}
	
	public function isPendingPayment() {
		return (isset($this->data['pending']) && $this->data['pending']);
	}
	
	public static function getBillsAggregateValues($filters = array(), $payMode = 'one_payment') {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$nonRejectedOrCanceled = Billrun_Bill::getNotRejectedOrCancelledQuery();
		$filters = array_merge($filters, $nonRejectedOrCanceled);
		if (!empty($filters)) {
			$match = array(
				'$match' => $filters
			);
		}
		$match['$match']['$and'][] = array('$or' => array(
				array('charge.not_before' => array('$exists' => false)),
				array('charge.not_before' => array('$lt' => new Mongodloid_Date())),
		));
		$match['$match']['$and'][] = array('$or' => array(
				array('left_to_pay' => array('$gt' => 0)),
				array('left' => array('$gt' => 0)),
		));
		$pipelines[] = $match;
		$pipelines[] = array(
			'$sort' => array(
				'type' => 1,
				'charge.not_before' => -1,
			),
		);
		$pipelines[] = array(
			'$addFields' => array(
				'method' => 'automatic',
				'unique_id' => array('$ifNull' => array('$invoice_id', '$txid')),
			),
		);
		
		$pipelines[] = array(
			'$group' => self::getGroupByMode($payMode),
		);

		$pipelines[] = array(
			'$project' => array(
				'_id' => 1,
				'suspend_debit' => 1,
				'type' => 1,
				'payment_method' => 1,
				'aid' => 1,
				'billrun_key' => 1,
				'lastname' => 1,
				'firstname' => 1,
				'bill_unit' => 1,
				'bank_name' => 1,
				'due_date'=> 1,
				'charge.not_before'=> 1,
				'source' => 1,
				'currency' => 1,
				'invoices' => 1,
				'left' => 1,
				'left_to_pay' => 1,
				'due' => array('$subtract' => array('$left_to_pay', '$left')),
				'invoice_id' => 1,
				'txid' => 1,
				'unique_id' => 1,
			),
		);
		
		$pipelines[] = array(
			'$match' => array(
				'$or' => array(
					array('due' => array('$gt' => Billrun_Bill::precision)),
					array('due' => array('$lt' => -Billrun_Bill::precision)),
				),
				'suspend_debit' => NULL,
			),
		);
		
		$res = $billsColl->aggregateWithOptions($pipelines, ['allowDiskUse' => true]);
		return $res;
	}

	protected static function getGroupByMode($mode = false) {
		$group = array(
				'_id' => '$aid',
				'suspend_debit' => array(
					'$first' => '$suspend_debit',
				),
				'type' => array(
					'$first' => '$type',
				),
				'payment_method' => array(
					'$first' => '$method',
				),
				'aid' => array(
					'$first' => '$aid',
				),
				'billrun_key' => array(
					'$first' => '$billrun_key',
				),
				'lastname' => array(
					'$first' => '$lastname',
				),
				'firstname' => array(
					'$first' => '$firstname',
				),
				'bill_unit' => array(
					'$first' => '$bill_unit',
				),
				'bank_name' => array(
					'$first' => '$bank_name',
				),
				'due_date' => array(
					'$first' => '$due_date',
				),
				'source' => array(
					'$first' => '$source',
				),
				'currency' => array(
					'$first' => '$currency',
				),
				'left_to_pay' => array(
					'$sum' => '$left_to_pay',
				),
				'left' => array(
					'$sum' => '$left',
				),
				'invoices' => array(
					'$push' => array(
						'invoice_id' => '$invoice_id',
						'amount' => '$amount',
						'left' => '$left',
						'left_to_pay' => '$left_to_pay',
						'txid' => '$txid',
						'type' => '$type',
                                                'invoice_date' => '$invoice_date',
                                                'urt' => '$urt'
					)
				),
			);	
		if ($mode == 'multiple_payments') {
			$group['_id'] = '$unique_id';
			$group['unique_id'] = array('$first' => '$unique_id');
		}
			
		return $group;
	}
	protected function setDueDate($dueDate) {
		$this->data['due_date'] = $dueDate;
	}
	/**
	 * Function that return bills with method = "installment_agreement", by chosen conditions.
	 * @param type $aid - account id.
	 * @param type $dueDateStartBillrunKey - billrun key, to find bills that their due date is after it's beginning.
	 * @param type $dueDateEndBillrunKey - billrun key, to find bills that their due date is before it's beginning.
	 * @param type $type - bill type, 'rec' by default.
	 * @param type $urtStartBillrunKey - billrun key, to find bills that their urt is after it's beginning.
	 * @param type $urtEndBillrunKey - billrun key, to find bills that their urt is before it's beginning.
	 * @return type
	 */
	public static function getInstallmentsByKeysRangeAndMethod($aid, $dueDateStartBillrunKey, $dueDateEndBillrunKey, $type = 'rec', $urtStartBillrunKey = "197101", $urtEndBillrunKey = "999901") {
		$startBillrun = new Billrun_DataTypes_CycleTime($dueDateStartBillrunKey);
		$endBillrun = new Billrun_DataTypes_CycleTime($dueDateEndBillrunKey);
		$query['method'] = 'installment_agreement';
		$query['type'] = $type;
		$query['aid'] = $aid;
		$query['urt'] = array('$gte' => new Mongodloid_Date(Billrun_Billingcycle::getStartTime($urtStartBillrunKey)),
                              '$lte' => new Mongodloid_Date(Billrun_Billingcycle::getStartTime($urtEndBillrunKey)));
		$query['due_date'] = array('$gte' => new Mongodloid_Date($startBillrun->start()), '$lt' => new Mongodloid_Date($endBillrun->start()));
		return self::getBills($query);
	}
	
	public function updatePastRejectionsOnProcessingFiles() {
		foreach ($this->getPaidBills() as $bill) {
			$bill = Billrun_Bill::getInstanceByTypeAndid($bill['type'], $bill['id']);
				$bill->addToRejectedPayments($this->getId(), $this->getType());
				$bill->save();
		}
	}
	
	public function getBillMethod() {
		if (empty($this->method)) {
			return null;
		}
		return $this->method;
	}
	
	/**
	 * Function to get the debt or the credit balance, of all the accounts that are in collection, by aids (aids list or query).
	 * @param array $aids - array of aids, or query array on "aid" field in bill
	 * @param boolean $is_aids_query - true if "$aids" variable is query, true if it's a list of specific aids.
	 * @param boolean $only_debts - true if we want to get all the accounts that are in collection, with their debts
	 * (if they have credit balance they will not show) otherwise show also get all the accounts that are in collection that they have credit/debt
	 * @param boolean $include_pending - true if we want to get all the accounts that are in collection include debts that are in pending. false by default to not include pending debts. 
         * @return 
	 */
	public static function getBalanceByAids($aids = array(), $is_aids_query = false, $only_debts = false, $include_pending = false) {
		$billsColl = Billrun_Factory::db()->billsCollection();
		$account = Billrun_Factory::account();
		$rejection_required_conditions = Billrun_Factory::config()->getConfigValue("collection.settings.rejection_required.conditions.customers", []);
		$accountQuery = Billrun_Account::getBalanceAccountQuery($aids, $is_aids_query, $rejection_required_conditions);
		$currentAccounts = $account->loadAccountsForQuery($accountQuery);
		$rejection_required_aids = array_column(array_map(function($account) {
				return $account->getRawData();
			}, $currentAccounts), 'aid') ?? [];

		$nonRejectedOrCanceled = Billrun_Bill::getNotRejectedOrCancelledQuery();
		$match = array(
			'$match' => $nonRejectedOrCanceled,
		);

		if (!empty($aids)) {
			$match['$match']['aid'] = $is_aids_query ? $aids['aid'] : array('$in' => $aids);
		}
		$project = array(
			'$project' => array(
				'rejection_required' => array('$cond' => array(array('$in' => array('$aid', $rejection_required_aids)), true, false)),
				'past_rejections' => array('$cond' => array(array('$and' => array(array('$ifNull' => array('$past_rejections', false)), array('$ne' => array('$past_rejections', [])))), true, false)),
				'paid' => array('$cond' => array(array('$in' => array('$paid', array(false, '0', 0))), false, true)),
				'valid_due_date' => array('$cond' => array(array('$and' => array(array('$ne' => array('$due_date', null)), array('$lt' => array('$due_date', new Mongodloid_Date())))), true, false)),
				'aid' => 1,
				'left_to_pay' => 1,
				'left' => 1                              
			)
		);
                if ($include_pending) {
                    $project['$project']['pending'] = array('$cond' => array(array('$in' => array('$paid', array('2', 2))), true, false));
                    $project['$project']['pending_covering_amount'] = 1;
                }
		$addFields = array(
			'$addFields' => array(
				'total_debt_valid_cond' => array('$and' => array(array('$and' => array(
								array('$eq' => array('$rejection_required', true)),
								array('$ne' => array('$past_rejections', false)))), array('$and' => array(
								array('$eq' => array('$valid_due_date', true)),
								array('$eq' => array('$paid', false))))
					)
				),
				'total_debt_invalid_cond' => array('$and' => array(
						array('$and' => array(
								array('$eq' => array('$rejection_required', false)),
								array('$eq' => array('$valid_due_date', true)))),
						array('$eq' => array('$paid', false))
					)
				),
				'total_credit_cond' => array(
					'$cond' => array(array('$and' => array(array('$ne' => array('$left', null)), array('$eq' => array('$valid_due_date', true)))), true, false)
				),
			)
		);
                if ($include_pending) {
                    $addFields['$addFields']['total_pending_debt_valid_cond'] = array('$and' => array(array('$and' => array(
                                                        array('$eq' => array('$rejection_required', true)),
                                                        array('$ne' => array('$past_rejections', false)))), array('$and' => array(
                                                        array('$eq' => array('$valid_due_date', true)),
                                                        array('$eq' => array('$pending', true))))
                                )
                        );
                    $addFields['$addFields']['total_pending_debt_invalid_cond'] = array('$and' => array(
						array('$and' => array(
								array('$eq' => array('$rejection_required', false)),
								array('$eq' => array('$valid_due_date', true)))),
						array('$eq' => array('$pending', true))
					)
				);
                }
		$group = array(
			'$group' => array(
				'_id' => '$aid',
				'total_debt_valid' => array(
					'$sum' => array(
						'$cond' => array(array('$eq' => array('$total_debt_valid_cond', true)), '$left_to_pay', 0)
					),
				),
				'total_debt_invalid' => array(
					'$sum' => array(
						'$cond' => array(array('$eq' => array('$total_debt_invalid_cond', true)), '$left_to_pay', 0)
					),
				),
				'total_credit' => array(
					'$sum' => array(
						'$cond' => array(array('$eq' => array('$total_credit_cond', true)), array('$multiply' => array('$left', -1)), 0)
					),
				),
			),
		);
                 if ($include_pending) {
                    $group['$group']['total_pending_debt_valid'] = array(
                                '$sum' => array(
                                        '$cond' => array(array('$eq' => array('$total_pending_debt_valid_cond', true)), '$pending_covering_amount', 0)
                                ),
                        );
                    $group['$group']['total_pending_debt_invalid'] = array(
                                '$sum' => array(
                                        '$cond' => array(array('$eq' => array('$total_pending_debt_invalid_cond', true)), '$pending_covering_amount', 0)
                                ),
                        );
                }
		$project3 = array(
			'$project' => array(
				'_id' => 0,
				'aid' => '$_id',
			),
		);
		if ($only_debts) {			
                        if ($include_pending) {
                            $project3['$project']['total'] = array('$add'=> array(array('$add' => array(array('$add' => array('$total_debt_valid', '$total_debt_invalid')), '$total_pending_debt_valid')),'$total_pending_debt_invalid'));
                        } else {
                            $project3['$project']['total'] =  array('$add' => array('$total_debt_valid', '$total_debt_invalid'));
                        }
			$minBalance = floatval(Billrun_Factory::config()->getConfigValue('collection.settings.min_debt', '10'));
			$match2 = array(
				'$match' => array(
					'total' => array(
						'$gte' => $minBalance
					)
				)
			);
		} else {			
			if ($include_pending) {
                            $project3['$project']['total'] =  array('$add'=>array(array('$add' => array(array('$add' => array(array('$add' => array('$total_debt_valid', '$total_debt_invalid')), '$total_pending_debt_valid')), '$total_pending_debt_invalid')), '$total_credit'));
                        } else {
                            $project3['$project']['total'] =  array('$add' => array(array('$add' => array('$total_debt_valid', '$total_debt_invalid')), '$total_credit'));
                        }
                        $match2 = array(
				'$match' => array(
					'total' => array(
						'$ne' => 0
					)
				)
			);
		}
		$results = iterator_to_array($billsColl->aggregate($match, $project, $addFields, $group, $project3, $match2));
		return array_combine(array_map(function($ele) {
				return $ele['aid'];
			}, $results), $results);
	}
	
	
	protected function setChargeNotBefore($chargeNotBefore) {
		$rawData = $this->getRawData();
		$rawData['charge']['not_before'] = $chargeNotBefore;
		$this->setRawData($rawData);
	}
	
	public static function getDistinctBills($query, $distinctField) {
		if (empty($distinctField)) {
			Billrun_Factory::log("Billrun_Bill: no field to distinct by was passed", Zend_Log::ALERT);
			return false;
		}
		$billsColl = Billrun_Factory::db()->billsCollection();
		return $billsColl->distinct($distinctField, $query);
	}
	
	/**
	 * Function that returns bills that are payments, Within billrun keys range 
	 * @param type $aid
	 * @param type $urtStartBillrunKey - billrun key, to find bills that their urt is after it's beginning.
	 * @param type $urtEndBillrunKey - billrun key, to find bills that their urt is before it's beginning.
	 * @param type $method - array of methods - optional
	 * @return type
	 */
	public static function getPaymentsByKeysRange ($aid, $urtStartBillrunKey = "197001", $urtEndBillrunKey = "999901", $method = []) {
		$startUrt = new Billrun_DataTypes_CycleTime($urtStartBillrunKey);
		$endUrt = new Billrun_DataTypes_CycleTime($urtEndBillrunKey);
		$query['aid'] = $aid;
		$query['urt'] = array('$gte' => new Mongodloid_Date(Billrun_Billingcycle::getStartTime($urtStartBillrunKey)),
                              '$lte' => new Mongodloid_Date(Billrun_Billingcycle::getStartTime($urtEndBillrunKey)));
		$query['method'] = array('$ne' => 'installment_agreement');
		$query['type'] = 'rec';
		$query = array_merge($query, Billrun_Bill::getNotRejectedOrCancelledQuery(), Billrun_Bill_Payment::getNotWaitingPaymentsQuery());
		if (!empty($method)) {
			$query['method']['$in'] = $method;
		}
		return self::getBills($query);
	}

	/**
	 * Function to set custom fields in the paymet objects
	 * @param array $fields - array of "field path" => "field value" (field valut can be an array) to insert.
	 * @param boolean $mergeToExistingArray - array of fields names - for fields that their path leads to an array that needs
	 * to be merge with the given "field_value" - which have to be an array in this case as well. 
	 */
	public function setExtraFields($fields, $mergeToExistingArray = []) {
		if (empty($fields)) {
			return;
		}
		$paymentData = $this->getRawData();
		foreach ($fields as $path => $value) {
			if (!in_array($path, $mergeToExistingArray) || in_array($path, $mergeToExistingArray) && empty(Billrun_Util::getIn($paymentData, $path))) {
				Billrun_Util::setIn($paymentData, $path, $value);
			} else {
				$currentArray = Billrun_Util::getIn($paymentData, $path);
				Billrun_Util::setIn($paymentData, $path, array_unique(array_merge_recursive($currentArray, $value)));
			}
		}
		$this->setRawData($paymentData);
		$this->save();
	}
	
	/**
	 * Function that sets payment urt
	 * @param int $date - unix timestamp
	 */
	public function setUrt($date = null) {
		$this->data['urt'] = new Mongodloid_Date(!empty($date)? $date : time());
	}

	/**
	 * Function that sets payment process time
	 * @param int $date - unix timestamp to set as the process time.
	 */
	public function setProcessTime ($date = null) {
		$this->data['process_time'] = new Mongodloid_Date(!empty($date)? $date : time());
	}
	
	/**
	 * Function that sets deposit's freeze date
	 * @param int $date - unix timestamp
	 */
	public function setDepositFreezeDate ($date = null) {
		$this->data['freeze_date'] = new Mongodloid_Date(!empty($date)? $date : time());
	}
	
	/**
	 * will add a related/linked bill to an existing bill
	 * 
	 * @param array $relatedBills - the bills object to which we want to add a linked bill	
	 * @param srting $type - related bill's type. one of: "rec"/"inv"
	 * @param mixed $id - related bill's id
	 * @param float $amount - related bill's amount (calculated by left / left_to_pay fields)
         * @param array $relatedBill - the related bill
	 */
	public static function addRelatedBill(&$relatedBills, $type, $id, $amount, $relatedBill = []) {
		if (empty($relatedBills)) {
			$relatedBills = [];
		}
		$relatedBillDetails = [
			'type' => $type,
			'id' => $type === 'inv' ? intval($id) : $id,
			'amount' => floatval($amount)
		];
                if(isset($relatedBill['amount'])){
                    $relatedBillDetails['total_amount'] = $relatedBill['amount'];
                }
                if(($type === 'inv' && isset($relatedBill['invoice_date'])) || isset($relatedBill['urt'])){
                    $relatedBillDetails['date'] =  $type === 'inv' ?  $relatedBill['invoice_date'] : $relatedBill['urt'];
                }             
                $relatedBills[] = $relatedBillDetails;
	}
	
	/**
	 * get index of related bill, -1 if not found
	 * 
	 * @param array $relatedBills - array of related bills
	 * @param srting $type - related bill's type. one of: "rec"/"inv"
	 * @param mixed $id - related bill's id
	 */
	public static function findRelatedBill($relatedBills, $type, $id) {
		$id = $type === 'inv' ? intval($id) : $id;
		foreach ($relatedBills as $i => $bill) {
			if ($bill['type'] == $type && $bill['id'] == $id) {
				return $i;
			}
		}
		
		return -1;
	}
	
	/**
	 * get related bill
	 * 
	 * @param array $relatedBills - array of related bills
	 * @param srting $type - related bill's type. one of: "rec"/"inv"
	 * @param mixed $id - related bill's id
	 */
	public static function getRelatedBill($relatedBills, $type, $id) {
		$index = Billrun_Bill::findRelatedBill($relatedBills, $type, $id);
		return $index == -1 ? false : $relatedBills[$index];
	}
	
	/**
	 * get related bills 
	 * 
	 * @param array $relatedBills - array of related bills
	 * @param srting $type - related bill's type. one of: "rec"/"inv"
	 */
	public static function getRelatedBills($relatedBills, $type) {
		$ret = [];
		foreach ($relatedBills as $bill) {
			if ($bill['type'] == $type) {
				$ret[] = $bill;
			}
		}
		
		return $ret;
	}
	
	/**
	 * converts related bills (pays/paid_by) of given array
	 * 
	 * @param array $paymentParams
	 */
	public static function convertRelatedBills(&$paymentParams) {
		foreach (['pays', 'paid_by'] as $dir) {
			if (empty($paymentParams[$dir])) {
				continue;
			}
			if (!Billrun_Util::isAssoc($paymentParams[$dir])) { // already in the new format
				continue;
			}
			
			$newPaymentParam = [];
			foreach ($paymentParams[$dir] as $billType => $bills) {
				foreach ($bills as $billId => $amount) {                                       
					Billrun_Bill::addRelatedBill($newPaymentParam, $billType, $billId, $amount);
				}
			}
			
			$paymentParams[$dir] = $newPaymentParam;
		}
	}

}