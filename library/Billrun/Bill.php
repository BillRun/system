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
				'aid' => '$_id',
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
				return $ele['aid'];
			}, $results), $results);
	}

	/**
	 * Return total / total waiting due for account
	 * @param int $aid
	 * @param boolean $notFormatted
	 * @return array
	 */
	public static function getTotalDueForAccount($aid, $date = null, $notFormatted = false) {
		$query = array('aid' => $aid);
		if (!empty($date)) {
			$query['$or'] = array(
				array('due_date' => array('$lte' => new MongoDate(strtotime($date)))),
				array('due_date' => array('$exists' => false)),
			);
		}
		$results = static::getTotalDue($query, $notFormatted);
		if (count($results)) {
			$total =  current($results)['total'];
			$totalWaiting = current($results)['total2'];
		} else if ($notFormatted) {
			$total = $totalWaiting = 0;
		} else {
			$total = $totalWaiting = Billrun_Util::getChargableAmount(0);
		}
		return array('total' => $total, 'without_waiting' => $totalWaiting);
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
					$overPayingBill->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $payingBillAmountLeft)->save();
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
					$overPayingBill->attachPaidBill($unpaidBill->getType(), $unpaidBill->getId(), $amountPaid)->save();
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
		
	protected function updateLeftToPay() {
		if ($this->getDue() > 0) {
			$this->data['left_to_pay'] = $this->getAmount();
			foreach ($this->getPaidByBills() as $paidByBills) {
				$this->data['left_to_pay'] -= array_sum($paidByBills);
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
		foreach ($this->getPaidBills() as $billType => $bills) {
			foreach (array_keys($bills) as $billId) {
				$billObj = Billrun_Bill::getInstanceByTypeAndid($billType, $billId);
				$billObj->detachPayingBill($this->getType(), $this->getId())->save();
			}
		}
	}
	
	public function detachPayingBills() {
		foreach ($this->getPaidByBills() as $billType => $bills) {
			foreach (array_keys($bills) as $billId) {
				$billObj = Billrun_Bill::getInstanceByTypeAndid($billType, $billId);
				$billObj->detachPaidBill($this->getType(), $this->getId())->save();
			}
		}
	}

	public function getType() {
		return $this->type;
	}

	public function getPaidAmount() {
		return isset($this->data['total_paid']) ? $this->data['total_paid'] : 0;
	}

	protected function recalculatePaymentFields($billId = null, $status = null) {
		if ($this->getDue() > 0) {
			$amount = 0;
			if (isset($this->data['paid_by']['inv'])) {
				$amount += array_sum($this->data['paid_by']['inv']);
			}
			if (isset($this->data['paid_by']['rec'])) {
				$amount += array_sum($this->data['paid_by']['rec']);
			}
			$this->data['total_paid'] = $amount;
			$this->data['left_to_pay'] = $this->getLeftToPay();
			$this->data['vatable_left_to_pay'] = min($this->getLeftToPay(), $this->getDueBeforeVat());
			if (is_null($status)){
				$this->data['paid'] = $this->isPaid();
			} else {
				$this->data['paid'] = $this->calcPaidStatus($billId, $status);
			}
				
		} else if ($this->getDue() < 0){
			$amount = 0;
			if (isset($this->data['pays']['inv'])) {
				$amount += array_sum($this->data['pays']['inv']);
			}
			if (isset($this->data['pays']['rec'])) {
				$amount += array_sum($this->data['pays']['rec']);
			}
			$this->data['left'] = $this->data['amount'] - $amount;				
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

	public function attachPayingBill($bill, $amount, $status = null) {
		$billId = $bill->getId();
		$billType = $bill->getType();
		if ($amount) {
			$paidBy = $this->getPaidByBills();
			$paidBy[$billType][$billId] = (isset($paidBy[$billType][$billId]) ? $paidBy[$billType][$billId] : 0) + $amount;
			if ($bill->isPendingPayment()) {
				$this->addToWaitingPayments($billId, $billType);
			}
			if ($status == 'Rejected') {
				$this->addToRejectedPayments($billId, $billType);
			}
			$this->updatePaidBy($paidBy, $billId, $status);
		}
		return $this;
	}

	public function detachPayingBill($billType, $id) {
		$paidBy = $this->getPaidByBills();
		unset($paidBy[$billType][$id]);
		$this->updatePaidBy($paidBy);
		return $this;
	}
	
	public function detachPaidBill($billType, $id) {
		$pays = $this->getPaidBills();
		unset($pays[$billType][$id]);
		$this->updatePays($pays);
		return $this;
	}

	protected function updatePaidBy($paidBy, $billId = null, $status = null) {
		if ($this->getDue() > 0 || $this->isRejection() || $this->isCancellation()) {
			$this->data['paid_by'] = $paidBy;
			$this->recalculatePaymentFields($billId, $status);
		}
	}
	
	protected function updatePays($pays, $billId = null) {
		if ($this->getDue() < 0) {
			$this->data['pays'] = $pays;
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
		$billsColl = Billrun_Factory::db()->billsCollection();
		$account = Billrun_Factory::account();
		$exempted = $account->getExcludedFromCollection($aids);
		$subject_to = $account->getIncludeFromCollection($aids);
		$accountCurrentRevisionQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$accountCurrentRevisionQuery['type'] = 'account';
		$minBalance = floatval(Billrun_Factory::config()->getConfigValue('collection.settings.min_debt', '10'));

		// white list exists but aids not included
		if (!is_null($subject_to) && empty($subject_to)) {
			return [];
		}
		// white list exists and aids included
		if (!is_null($subject_to) && !empty($subject_to)) {
			$aids = $subject_to;
		}
		
		$matchQuery = array(
			'type' => 'inv',
			'due_date' => array(
				'$lt' => new MongoDate(),
			),
			'paid' => array('$in' => array(false, '0', 0)),
		);
		
		if (!empty($aids)) {
			$aidsQuery = array('aid' => array('$in' => $aids));			
			$relevantAids = $billsColl->distinct('aid', array_merge($matchQuery, $aidsQuery));
		} else if (!empty($exempted)){
			$aidsQuery = array('aid' => array('$nin' => $aids));
			$relevantAids = $billsColl->distinct('aid', array_merge($matchQuery, $aidsQuery));
		} else {
			$relevantAids = $billsColl->distinct('aid', $matchQuery);
		}
		$accountQuery = array_merge($accountCurrentRevisionQuery, $aidsQuery);
		$currentAccounts = $account->getAccountsByQuery($accountQuery);
		$validGatewaysAids = array();
		foreach ($currentAccounts as $activeAccount) {
			if (!empty($activeAccount['payment_gateway']['active'])) {
				$validGatewaysAids[] = $activeAccount['aid'];
			}
		}

		$match = array(
			'$match' => $matchQuery,
		);

		if ($aids) {
			$match['$match']['aid']['$in'] = $aids;
		}
		if ($exempted) {
			$match['$match']['aid']['$nin'] = $exempted;
		}

		$project = array(
			'$project' => array(
				'valid_gateway' => array('$cond' => array(array('$in' => array('$aid', $validGatewaysAids)), true, false)),
				'past_rejections' => array('$cond' => array(array('$and' => array(array('$ifNull' => array('$past_rejections', false)) , array('$ne' => array('$past_rejections', [])))), true, false)),
				'aid' => 1,
				'left_to_pay' => 1
			)
		);
		
		$group = array(
			'$group' => array(
				'_id' => '$aid',
				'total_valid' => array(
					'$sum' => array(
						'$cond' => array(array('$and' => array(array('$eq' => array('$valid_gateway', true)) , array('$ne' => array('$past_rejections', false)))), '$left_to_pay', 0)
					),
				),
				'total_invalid' => array(
					'$sum' => array(
						'$cond' => array(array('$eq' => array('$valid_gateway', false)), '$left_to_pay', 0),
					),
				),
			),
		);

		$project3 = array(
			'$project' => array(
				'_id' => 0,
				'aid' => '$_id',
				'total' => array('$add' => array('$total_valid', '$total_invalid')),
			),
		);

		$match2 = array(
			'$match' => array(
				'total' => array(
					'$gte' => $minBalance
				)
			)
		);
		$results = iterator_to_array($billsColl->aggregate($match, $project, $group, $project3, $match2));
		return array_combine(array_map(function($ele) {
				return $ele['aid'];
			}, $results), $results);
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
		if (in_array($method, array('automatic', 'cheque', 'wire_transfer', 'cash', 'credit', 'write_off', 'debit'))) {
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
					} else if ($rawPayment['dir'] == 'tc') {
						$leftToSpare = floatval($rawPayment['amount']);
						$overPayingBills = Billrun_Bill::getOverPayingBills(array('aid' => $aid));
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
						$gateway = Billrun_PaymentGateway::getInstance($gatewayName);
						if (is_null($gateway)) {
							Billrun_Factory::log("Illegal payment gateway object", Zend_Log::ALERT);
						} else {
							Billrun_Factory::log("Paying bills through " . $gatewayName, Zend_Log::INFO);
							Billrun_Factory::log("Charging payment gateway details: " . "name=" . $gatewayName . ", amount=" . $gatewayDetails['amount'] . ', charging account=' . $aid, Zend_Log::DEBUG);
						}
						if (empty($options['single_payment_gateway'])) {
							try {
								$payment->setPending(true);
								$paymentStatus = $gateway->makeOnlineTransaction($gatewayDetails);
							} catch (Exception $e) {
								$payment->setGatewayChargeFailure($e->getMessage());
								$responseFromGateway = array('status' => $e->getCode(), 'stage' => "Rejected");
								Billrun_Factory::log('Failed to pay bill: ' . $e->getMessage(), Zend_Log::ALERT);
								continue;
							}
						} else {
							$paymentStatus = array(
								'status' => $payment->getSinglePaymentStatus(),
								'additional_params' => array()
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
						foreach ($payment->getPaidBills() as $billType => $bills) {
							foreach ($bills as $billId => $amountPaid) {
								if (isset($options['file_based_charge']) && $options['file_based_charge']) {
									$responsesFromGateway[$transactionId]['stage'] = 'Pending';
								}
								if ($responsesFromGateway[$transactionId]['stage'] != 'Pending') {
									$payment->setPending(false);
								}
								$updateBills[$billType][$billId]->attachPayingBill($payment, $amountPaid, empty($responsesFromGateway[$transactionId]['stage']) ? 'Completed' : $responsesFromGateway[$transactionId]['stage'])->save();
							}
						}
					} else if ($payment->getDir() == 'tc') {
						foreach ($payment->getPaidByBills() as $billType => $bills) {
							foreach ($bills as $billId => $amountPaid) {
								if (isset($options['file_based_charge']) && $options['file_based_charge']) {
									$responsesFromGateway[$transactionId]['stage'] = 'Pending';
								}
								if ($responsesFromGateway[$transactionId]['stage'] != 'Pending') {
									$payment->setPending(false);
								}
								$updateBills[$billType][$billId]->attachPaidBill($payment->getType(), $payment->getId(), $amountPaid)->save();
							}
						}
					} else {
						Billrun_Bill::payUnpaidBillsByOverPayingBills($payment->getAccountNo());
					}

					$involvedAccounts = array_unique($involvedAccounts);
					if ($responsesFromGateway[$transactionId]['stage'] == 'Completed' && ($gatewayDetails['amount'] < (0 - Billrun_Bill::precision))) {
						Billrun_Factory::dispatcher()->trigger('afterRefundSuccess', array($payment->getRawData()));
					}
					if ($responsesFromGateway[$transactionId]['stage'] == 'Completed' && ($gatewayDetails['amount'] > (0 + Billrun_Bill::precision))) {
						Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($payment->getRawData()));
					}
					if (is_null($responsesFromGateway[$transactionId]) && $payment->getDue() > 0) { // offline payment
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

	protected function calcPaidStatus($billId = null, $status = null) {
		if (is_null($billId) || is_null($status)){
			return;
		}
		switch ($status) {
			case 'Rejected':
				$result = '0';
				$this->removeFromWaitingPayments($billId);
				break;

			case 'Completed':
				$pending = $this->data['waiting_payments'];
				if (count($pending)) {
					$this->removeFromWaitingPayments($billId);
					$result = '2';
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
		$this->data['waiting_payments'] = $waiting_payments;
	}
	
	protected function addToRejectedPayments($billId, $billType) {
		if ($billType == 'inv') {
			return;
		}
		$rejectedPayments = isset($this->data['past_rejections']) ? $this->data['past_rejections'] : array();
		array_push($rejectedPayments, $billId);
		$this->data['past_rejections'] = $rejectedPayments;
	}

	protected function removeFromWaitingPayments($billId) {
		$pending = isset($this->data['waiting_payments']) ? $this->data['waiting_payments'] : array();
		$key = array_search($billId, $pending);
		if($key !== false) {
			unset($pending[$key]);
		}
		$this->data['waiting_payments'] = $pending;
	}

	public function updatePendingBillToConfirmed($billId, $status) {
		$paidBy = $this->getPaidByBills();
		$this->updatePaidBy($paidBy, $billId, $status);
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
		$match['$match']['$or'] = array(
				array('due_date' => array('$exists' => false)),
				array('due_date' => array('$lt' => new MongoDate())),
		);
		$pipelines[] = $match;
		$pipelines[] = array(
			'$sort' => array(
				'type' => 1,
				'due_date' => -1,
			),
		);
		$pipelines[] = array(
			'$addFields' => array(
				'method' => array('$ifNull' => array('$method', '$payment_method')),
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
		
		$res = $billsColl->aggregate($pipelines);
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
					)
				),
			);	
		if ($mode == 'multiple_payments') {
			$group['_id'] = '$unique_id';
			$group['unique_id'] = array('$first' => '$unique_id');
		}
			
		return $group;
	}
}
