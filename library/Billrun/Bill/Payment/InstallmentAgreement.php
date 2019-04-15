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
		if (!isset($options['id'])) {
			$this->data['id'] = $this->generateAgreementId();
		} else {
			$this->data['id'] = $options['id'];
		}
		if (!empty($options['installments_num']) && !empty($options['first_due_date']) && !empty($options['amount'])) {
			if (!Billrun_Util::IsIntegerValue($options['installments_num'])) {
				throw new Exception('installments_num parameter must be numeric value');
			}
			$this->data['installments_num'] = intval($options['installments_num']);
			$this->data['total_amount'] = $this->data['amount'] = floatval($options['amount']);
			$firstDueDate = strtotime($options['first_due_date']);
			if ($firstDueDate) {
				$this->data['first_due_date'] = new MongoDate($firstDueDate);
			} else {
				$this->data['first_due_date'] = $options['first_due_date'];
			}
		} else if (!empty($options['installments_agreement']) && !empty($options['amount'])) {
			$this->data['total_amount'] = $this->data['amount'] = floatval($options['amount']);
			$this->installments = $options['installments_agreement'];
			$this->data['installments_num'] = count($options['installments_agreement']);
		} else {
			throw new Exception('Billrun_Bill_Payment_InstallmentAgreement: Insufficient options supplied.');
		}
	}
	
	protected function splitBill() {
		$paymentsArr = array(array(
			'amount' => $this->data['total_amount'],
			'installments_num' => $this->data['installments_num'],
			'total_amount' => $this->data['total_amount'],
			'first_due_date' => !is_null($this->data['first_due_date']) ? $this->data['first_due_date'] : '',
			'dir' => 'fc',
			'aid' => $this->data['aid'],
			'installments_agreement' => $this->installments,
			'id' => $this->data['id'],
		));
		$primaryInstallment = current(Billrun_Bill::pay($this->method, $paymentsArr));
		if (!empty($primaryInstallment->getId())){
			$success = $primaryInstallment->splitToInstallments();
			return $success;
		}
		
		Billrun_Factory::log("Faild creating installment agreement for aid: " . $this->data['aid'], Zend_Log::NOTICE);
		return false;
	}
	
	protected function splitToInstallments() {
		$this->normalizeInstallments();
		if (empty($this->installments)) {
			throw new Exception("Error: Installments are empty");
		}
		$this->sortInstallmentsByDueDate();
		$installments = $this->splitPrimaryBill();
		$res = $this->savePayments($installments);
		if ($res && isset($res['ok']) && $res['ok']) {
			return true;
		} else {
			throw new Exception("Split to installments failed");
		}
	}
	
	protected function splitPrimaryBill() {
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
			$installment = $this->buildInstallment($index);
			if (empty($amountsArray)) {
				$totalAmount = $this->data['total_amount'];
				$periodicalPaymentAmount = floor($totalAmount/ $this->data['installments_num']);
				$firstPaymentAmount = $totalAmount - (($this->data['installments_num'] - 1) * $periodicalPaymentAmount);
				$installment['amount'] = ($index == 1) ? $firstPaymentAmount : $periodicalPaymentAmount;
				$installment['due'] = $installment['amount'];
			} else {
				$installment['amount'] = $installmentPayment['amount'];
				$installment['due'] = $installmentPayment['amount'];
			}
			$installment['due_date'] = new MongoDate(strtotime($installmentPayment['due_date']));
			$installments[] = Billrun_Bill_Payment::getInstanceByData($installment);
		}

		return $installments;
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
	
	protected function generateAgreementId() {
		return round(microtime(true) * 1000);
	}
	
	protected function normalizeInstallments() {
		if (!empty($this->installments)) {
			return;
		}
		if (empty($this->data['installments_num']) || empty($this->data['total_amount'])) {
			throw new Exception('Installments_num and total_amount must exist and be bigger than 0');
		}
		for ($index = 0; $index < $this->data['installments_num']; $index++) {
			$this->installments[$index] = array('due_date' => date(Billrun_Base::base_datetimeformat, strtotime("$index  month", $this->data['first_due_date']->sec)));
		}
	}
	
	protected function buildInstallment($index) {
		$installment['dir'] = 'tc';
		$installment['method'] = $this->method;
		$installment['aid'] = $this->data['aid'];
		$installment['type'] = 'rec';
		$installment['payment_agreement']['installments_num'] = $this->data['installments_num'];
		$installment['payment_agreement']['first_due_date'] = $this->data['first_due_date'];
		$installment['payment_agreement']['id'] = $this->data['id'];
		$installment['payment_agreement']['total_amount'] = $this->data['total_amount'];
		$installment['payment_agreement']['installment_index'] = $index;
		$installment['split_bill'] = true;
		$installment['linked_bills'] = isset($this->data['pays']) ? $this->data['pays'] : $this->data['paid_by'];
		return $installment;
	}
}