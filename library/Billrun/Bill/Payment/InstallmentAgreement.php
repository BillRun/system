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
	protected $installmentsNum;
	protected $id;
	protected $totalAmount;
	protected $firstDueDate;

	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['payment_agreement'])) {
			return;
		}
		if (!isset($options['id'])) {
			$this->id = $this->data['payment_agreement.id'] = $this->generateAgreementId();
		} else {
			$this->id = $this->data['payment_agreement.id'] = $options['id'];
		}
		if (!empty($options['installment_index'])) {
			$this->data['payment_agreement.installment_index'] = $options['installment_index'];
		}
		
		if (!empty($options['installments_num']) && !empty($options['first_due_date']) && !empty($options['amount'])) {
			if (!Billrun_Util::IsIntegerValue($options['installments_num'])) {
				throw new Exception('installments_num parameter must be numeric value');
			}
			$this->installmentsNum = $this->data['payment_agreement.installments_num'] = intval($options['installments_num']);
			$this->data['amount'] = floatval($options['amount']);
			$this->totalAmount = $this->data['payment_agreement.total_amount'] = !empty($options['total_amount']) ? $options['total_amount'] : floatval($options['amount']);
			$firstDueDate = strtotime($options['first_due_date']);
			if ($firstDueDate) {
				$this->firstDueDate = $this->data['payment_agreement.first_due_date'] = new MongoDate($firstDueDate);
			} else {
				$this->firstDueDate = $this->data['payment_agreement.first_due_date'] = $options['first_due_date'];
			}
		} else if (!empty($options['installments_agreement']) && !empty($options['amount'])) {
			$this->data['amount'] = floatval($options['amount']);
			$this->totalAmount = $this->data['payment_agreement.total_amount'] = !empty($options['total_amount']) ? $options['total_amount'] : floatval($options['amount']);
			$this->installments = $options['installments_agreement'];
			$this->installmentsNum = $this->data['payment_agreement.installments_num'] = count($options['installments_agreement']);
		} else {
			throw new Exception('Billrun_Bill_Payment_InstallmentAgreement: Insufficient options supplied.');
		}
	}
	
	protected function splitBill() {
		$paymentsArr = array(array(
			'amount' => $this->totalAmount,
			'installments_num' => $this->installmentsNum,
			'total_amount' => $this->totalAmount,
			'first_due_date' => !is_null($this->firstDueDate) ? $this->firstDueDate : '',
			'dir' => 'fc',
			'aid' => $this->data['aid'],
			'installments_agreement' => $this->installments,
			'id' => $this->id,
		));
		if (!empty($this->data['note'])) {
			$paymentsArr[0]['note'] = $this->data['note'];
		}
		$primaryInstallment = current(Billrun_Bill::pay($this->method, $paymentsArr));
		if (!empty($primaryInstallment) && !empty($primaryInstallment->getId())){
			$success = $primaryInstallment->splitToInstallments();
			return $success;
		}
		
		Billrun_Factory::log("Faild creating installment agreement for aid: " . $this->data['aid'], Zend_Log::ALERT);
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
			Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array(array('aid' => $this->data['aid'])));
			return true;
		} else {
			throw new Exception("Split to installments failed");
		}
	}
	
	protected function splitPrimaryBill() {
		$installments = array();
		$amountsArray = array_column($this->installments, 'amount');
		foreach ($this->installments as $key => $installmentPayment) {
			$index = $key + 1;
			$installment = $this->buildInstallment($index);
			if (empty($amountsArray)) {
				$totalAmount = $this->totalAmount;
				$periodicalPaymentAmount = floor($totalAmount/ $this->installmentsNum);
				$firstPaymentAmount = $totalAmount - (($this->installmentsNum - 1) * $periodicalPaymentAmount);
				$installment['amount'] = ($index == 1) ? $firstPaymentAmount : $periodicalPaymentAmount;
			} else {
				$installment['amount'] = $installmentPayment['amount'];
			}
			if (!empty($installmentPayment['note'])) {
				$installment['note'] = $installmentPayment['note'];
			}
			$installment['due_date'] = new MongoDate(strtotime($installmentPayment['due_date']));
			$installment['charge']['not_before'] = $installment['due_date'];
			$installments[] = new self($installment);
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
		$this->firstDueDate = new MongoDate(strtotime($this->installments[0]['due_date']));
	}
	
	protected function generateAgreementId() {
		return round(microtime(true) * 1000);
	}
	
	protected function normalizeInstallments() {
		if (!empty($this->installments)) {
			return;
		}
		if (empty($this->installmentsNum) || empty($this->totalAmount)) {
			throw new Exception('Installments_num and total_amount must exist and be bigger than 0');
		}
		for ($index = 0; $index < $this->installmentsNum; $index++) {
			$date = date(Billrun_Base::base_datetimeformat, strtotime("$index  month", $this->firstDueDate->sec));
			$this->installments[$index] = array('due_date' => $date, 'charge' => array('not_before' => $date));
		}
		$amountsArray = array_column($this->installments, 'amount');
		if (count($amountsArray) != 0 && count($amountsArray) != $this->installmentsNum) {
			throw new Exception("All installments must all be with/without amount");
		}
		$dueDateArray = array_column($this->installments, 'due_date');
		if (count($dueDateArray) != $this->installmentsNum) {
			throw new Exception("All installments must have due_date");
		}
	}
	
	protected function buildInstallment($index) {
		$installment['dir'] = 'tc';
		$installment['method'] = $this->method;
		$installment['aid'] = $this->data['aid'];
		$installment['type'] = 'rec';
		$installment['installments_num'] = $this->installmentsNum;
		$installment['first_due_date'] = $this->firstDueDate;
		$installment['id'] = $this->id;
		$installment['total_amount'] = $this->totalAmount;
		$installment['installment_index'] = $index;
		$installment['split_bill'] = true;
		$installment['linked_bills'] = isset($this->data['pays']) ? $this->data['pays'] : $this->data['paid_by'];
		return $installment;
	}
}