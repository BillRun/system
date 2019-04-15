<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Installment Agreement class
 *
 * @package  Billrun
 * @since    5.9
 */
class Billrun_Bill_Payment_InstallmentAgreement extends Billrun_Bill_Payment {

	protected $method = 'installment_agreement';
	protected $installments = array();

	public function __construct($options) {
		parent::__construct($options);
		if (!empty($options['split_bill'])) {
			return;
		}
		if (!empty($options['installments_num']) && !empty($options['first_due_date']) && !empty($options['amount'])) {
			$this->data['installments_num'] = intval($options['installments_num']);
			$this->data['total_amount'] = $this->data['amount'] = floatval($options['amount']);
			if (strtotime($options['first_due_date'])) {
				$this->data['first_due_date'] = new MongoDate(strtotime($options['first_due_date']));
			} else {
				$this->data['first_due_date'] = $options['first_due_date'];
			}
			$this->data['id'] = round(microtime(true) * 1000);
		} else if (!empty($options['installments_agreement']) && !empty($options['amount'])) {
			$this->data['total_amount'] = $this->data['amount'] = floatval($options['amount']);
			$this->installments = $options['installments_agreement'];
			$this->data['installments_num'] = count($options['installments_agreement']);
			$this->data['id'] = round(microtime(true) * 1000);
		} else {
			throw new Exception('Billrun_Bill_Payment_InstallmentAgreement: Insufficient options supplied.');
		}
	}
	
	public function splitBill() {
		$paymentsArr = array(array(
			'amount' => $this->data['total_amount'],
			'installments_num' => $this->data['installments_num'],
			'total_amount' => $this->data['total_amount'],
			'first_due_date' => !is_null($this->data['first_due_date']) ? $this->data['first_due_date'] : '',
			'dir' => 'fc',
			'aid' => $this->data['aid'],
			'installments_agreement' => $this->installments,
		));
		$primaryInstallment = current(Billrun_Bill::pay($this->method, $paymentsArr));
		if (!empty($primaryInstallment->getId())){
			$success = $primaryInstallment->splitToInstallments();
			return $success;
		}
		
		return false;
	}
	
	protected function splitToInstallments() {
		if (!empty($this->installments)) {
			$this->sortInstallmentsByDueDate();
			$installments = $this->splitPrimaryBillByInstallmentsArray();
		} else {
			$installments = $this->splitPrimaryBillByTotalAmount();
		}
		$res = $this->savePayments($installments);
		if ($res && isset($res['ok']) && $res['ok']) {
			return true;
		} else {
			throw new Exception("Split to installments failed");
		}
	}
	
	protected function splitPrimaryBillByInstallmentsArray() {
		$installments = array();
		$amountsArray = array_column($this->installments, 'amount');
		if (count($amountsArray) != 0 && count($amountsArray) != $this->data['installments_num']) {
			throw new Exception("All installments must all be with/without amount");
		}
		$dueDateArray = array_column($this->installments, 'due_date');
		if (count($dueDateArray) != $this->data['installments_num']) {
			throw new Exception("All installments must have due_date");
		}
		foreach ($this->installments as $key => $installmentPayment) {
			$index = $key + 1;
			if (empty($amountsArray)) {
				$installment = $this->buildInstallmentByTotalAmount($index);
			} else {
				$installment = $this->buildInstallmentByPassedAmount($index, $installmentPayment);
			}
			$installment['due_date'] = new MongoDate(strtotime($installmentPayment['due_date']));
			$installments[] = Billrun_Bill_Payment::getInstanceByData($installment);
		}

		return $installments;
	}
	
	protected function splitPrimaryBillByTotalAmount() {
		if (empty($this->data['installments_num']) || empty($this->data['total_amount'])) {
			throw new Exception('Installments_num and total_amount must exist and be bigger than 0');
		}
		$installments = array();
		for ($index = 1; $index <= $this->data['installments_num']; $index++) {
			$installment = $this->buildInstallmentByTotalAmount($index);
			$monthNumber = $index - 1;
			$installment['due_date'] = new MongoDate(strtotime("$monthNumber  month", $this->data['first_due_date']->sec));
			$installments[] = Billrun_Bill_Payment::getInstanceByData($installment);
		}

		return $installments;
	}
	
	protected function buildInstallmentByTotalAmount($index) {
		$installment['dir'] = 'tc';
		$installment['method'] = $this->method;
		$installment['aid'] = $this->data['aid'];
		$installment['type'] = 'rec';
		$totalAmount = $this->data['total_amount'];
		$periodicalPaymentAmount = floor($totalAmount/ $this->data['installments_num']);
		$firstPaymentAmount = $totalAmount - (($this->data['installments_num'] - 1) * $periodicalPaymentAmount);
		$installment['amount'] = ($index == 1) ? $firstPaymentAmount : $periodicalPaymentAmount;
		$installment['due'] = $installment['amount'];
		$installment['installments_num'] = $this->data['installments_num'];
		$installment['total_amount'] = $totalAmount;
		$installment['first_due_date'] = $this->data['first_due_date'];
		$installment['id'] = $this->data['id'];
		$installment['installment_index'] = $index;
		$installment['split_bill'] = true;
		$installment['linked_bills'] = isset($this->data['pays']) ? $this->data['pays'] : $this->data['paid_by'];
		return $installment;
	}
	
	public function getAgreementId() {
		return $this->data['id'];
	}
	
	protected function buildInstallmentByPassedAmount($index, $installmentPayment) {
		$installment['dir'] = 'tc';
		$installment['method'] = $this->method;
		$installment['aid'] = $this->data['aid'];
		$installment['type'] = 'rec';
		$installment['amount'] = $installmentPayment['amount'];
		$installment['due'] = $installmentPayment['amount'];
		$installment['installments_num'] = $this->data['installments_num'];
		$installment['total_amount'] = $this->data['total_amount'];
		$installment['first_due_date'] = $this->data['first_due_date'];
		$installment['id'] = $this->data['id'];
		$installment['installment_index'] = $index;
		$installment['split_bill'] = true;
		$installment['linked_bills'] = isset($this->data['pays']) ? $this->data['pays'] : $this->data['paid_by'];
		return $installment;
	}

	protected function sortInstallmentsByDueDate() {
		if (empty($this->installments)) {
			return;
		}
		$dueDates = array();
		foreach ($this->installments as $key => $row) {
			$dueDates[$key] = $row['due_date'];
		}
		array_multisort($dueDates, SORT_ASC, $this->installments);
		$this->data['first_due_date'] = new MongoDate(strtotime($this->installments[0]['due_date']));
	}
}