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
	protected $attachDueDateToCycleEnd = false;
	protected $initialChargeNotBefore;
	protected $payment_uf = [];
	protected $forced_uf = [];

	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['payment_agreement'])) {
			return;
		}
		if (!isset($options['id'])) {
			$this->id = $this->data['payment_agreement.id'] = $this->generateAgreementId();
			$this->initialChargeNotBefore = isset($options['charge']['not_before']) ? $options['charge']['not_before'] : null;
		} else {
			$this->id = $this->data['payment_agreement.id'] = $options['id'];
		}
		if (!empty($options['installment_index'])) {
			$this->data['payment_agreement.installment_index'] = $options['installment_index'];
		}
		if (!empty($options['invoices'])) {
			$this->data['payment_agreement.invoices'] = $options['invoices'];
		}
		if ((!empty($options['installments_num']) || !empty($options['first_due_date'])) && !empty($options['amount'])) {
			if (!Billrun_Util::IsIntegerValue($options['installments_num'])) {
				throw new Exception('installments_num parameter must be numeric value');
			}
			$this->installmentsNum = $this->data['payment_agreement.installments_num'] = intval($options['installments_num']);
			$this->data['amount'] = floatval($options['amount']);
			$this->totalAmount = $this->data['payment_agreement.total_amount'] = !empty($options['total_amount']) ? $options['total_amount'] : floatval($options['amount']);
			$this->attachDueDateToCycleEnd = !empty($options['cycle_attached_date']) ? $options['cycle_attached_date'] : $this->attachDueDateToCycleEnd;
			$firstDueDate = strtotime($options['first_due_date']);
			if ($firstDueDate) {
				$this->firstDueDate = $this->data['payment_agreement.first_due_date'] = new MongoDate($firstDueDate);
			} else {
				if (!empty($options['first_due_date'])) {
					$this->firstDueDate = $this->data['payment_agreement.first_due_date'] = $options['first_due_date'];
				} else {
					$this->attachDueDateToCycleEnd = true;
					$this->firstDueDate = $this->data['payment_agreement.first_due_date'] = new MongoDate(Billrun_Billingcycle::getEndTime(Billrun_Billingcycle::getBillrunKeyByTimestamp()) - 1);
				}
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
		if (!empty($this->attachDueDateToCycleEnd)) {
			$paymentsArr[0]['cycle_attached_date'] = true;
		}
		if (!empty($this->forced_uf)) {
			$paymentsArr[0]['forced_uf'] = $this->forced_uf;
		}
		$account = Billrun_Factory::account();
		$params['account'] = $account->loadAccountForQuery(['aid' => $this->data['aid']]);
		$paymentResponse = Billrun_PaymentManager::getInstance()->pay($this->method, $paymentsArr, $params);
		$primaryInstallment = current($paymentResponse['payment']);
		$primaryInstallmentData = current($paymentResponse['payment_data']);
		$this->updatePaidInvoicesOnPrimaryInstallment($primaryInstallment);
		if (!empty($primaryInstallment) && !empty($primaryInstallment->getId())){
			$paymentAgreementData = array();
			$initialChargeNotBefore = !empty($this->initialChargeNotBefore) ? $this->initialChargeNotBefore : $this->getInitialChargeNotBefore($primaryInstallment);
			$success = $primaryInstallment->splitToInstallments($initialChargeNotBefore, $primaryInstallmentData);
			if ($success) {
				$paymentAgreementData = $primaryInstallment->getRawData()['payment_agreement'];
			}
			return array('status' => $success, 'payment_agreement' => $paymentAgreementData);
		}
		
		Billrun_Factory::log("Failed creating installment agreement for aid: " . $this->data['aid'], Zend_Log::ALERT);
		return false;
	}
	
	protected function splitToInstallments($initialChargeNotBefore, $primaryInstallmentData = null) {
		$this->normalizeInstallments($primaryInstallmentData);
		if (empty($this->installments)) {
			throw new Exception("Error: Installments are empty");
		}
		$this->sortInstallmentsByDueDate();
		$installments = $this->splitPrimaryBill($initialChargeNotBefore);
		$res = $this->savePayments($installments);
		if ($res && isset($res['ok']) && $res['ok']) {
			Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array(array('aid' => $this->data['aid'])));
			return true;
		} else {
			throw new Exception("Split to installments failed");
		}
	}
	
	protected function splitPrimaryBill($initialChargeNotBefore) {
		$installments = array();
		$amountsArray = array_column($this->installments, 'amount');
		$chargesArray = $this->calcInstallmentDates($initialChargeNotBefore, 'charge_not_before');
		foreach ($this->installments as $key => $installmentPayment) {
			$index = $key + 1;
			$installment = $this->buildInstallment($index, $chargesArray[$key]['charge_not_before']);
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
			$installment['uf'] = $installmentPayment['uf'];
			$installment['forced_uf'] = !empty($this->forced_uf) ? $this->forced_uf : [];
			$installmentObj = new self($installment);
			$installmentObj->setUserFields($installmentObj->getRawData(), true);
			$account = Billrun_Factory::account();
			$current_account = $account->loadAccountForQuery(['aid' => $installment['aid']]);
			$foreignData = $this->getForeignFields(array('account' => $current_account));
			if (!is_null($current_account)) {
				$installmentObj->setForeignFields($foreignData);
			}
			
			$installments[] = $installmentObj;
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
	
	protected function normalizeInstallments($primaryInstallmentData = null) {
		$installments = !is_null($primaryInstallmentData) && !empty($primaryInstallmentData['installments_agreement']) ? $primaryInstallmentData['installments_agreement'] : null;
		if (!empty($installments)) {
			$this->installments = $installments;
			return;
		}
		if (empty($this->installmentsNum) || empty($this->totalAmount)) {
			throw new Exception('Installments_num and total_amount must exist and be bigger than 0');
		}
		$this->installments = $this->calcInstallmentDates($this->firstDueDate, 'due_date');
		$amountsArray = array_column($this->installments, 'amount');
		if (count($amountsArray) != 0 && count($amountsArray) != $this->installmentsNum) {
			throw new Exception("All installments must all be with/without amount");
		}
		$dueDateArray = array_column($this->installments, 'due_date');
		if (count($dueDateArray) != $this->installmentsNum) {
			throw new Exception("All installments must have due_date");
		}
	}
	
	protected function buildInstallment($index, $chargeNotBefore) {
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
		$installment['invoices'] = $this->getInvoicesIdFromReceipt();
		$installment['charge']['not_before'] = new MongoDate(strtotime($chargeNotBefore));
		$installment['urt'] = new MongoDate(strtotime($chargeNotBefore));
		return $installment;
	}

	protected function updatePaidInvoicesOnPrimaryInstallment($primaryInstallment) {
		$installmentData = $primaryInstallment->getRawData();
		$installmentData['payment_agreement']['invoices'] = $primaryInstallment->getInvoicesIdFromReceipt();
		$primaryInstallment->setRawData($installmentData);
		$primaryInstallment->save();
	}

	protected function correctMonthMiscalculation($date, $prevMonth) {
		$year = date('Y', $date);
		$month = $prevMonth + 1;
		$monthDays = date('t', strtotime($year . '/' . $month . '/1'));
		return date(Billrun_Base::base_datetimeformat, strtotime($year . '/' . $month . '/' . $monthDays));
	}

	protected function calcInstallmentDates($initialDate, $dateType) {
		$res = array();
		$currentBillrun = Billrun_Billingcycle::getBillrunKeyByTimestamp();
		$previousMonth = 0;
		for ($index = 0; $index < $this->installmentsNum; $index++) {
			$dueDateTime = strtotime("$index  month", $initialDate->sec);
			$dueDate = date(Billrun_Base::base_datetimeformat, $dueDateTime);
			$currentMonth = intval(date('m', $dueDateTime));
			$correctMonth = ($previousMonth + 1) % 12;
			if (!empty($previousMonth) && $currentMonth != $correctMonth) {
				$dueDate = $this->correctMonthMiscalculation($dueDateTime, $previousMonth);
				$currentMonth = $correctMonth;
			}
			$previousMonth = $currentMonth;
			if ($this->attachDueDateToCycleEnd && ($dateType == 'due_date')) {
				$secondBeforeCycleEnd = Billrun_Billingcycle::getEndTime($currentBillrun) - 1;
				$dueDate = date(Billrun_Base::base_datetimeformat, $secondBeforeCycleEnd);
			}
			$res[$index] = array($dateType => $dueDate);
			$currentBillrun = Billrun_Billingcycle::getFollowingBillrunKey($currentBillrun);
		}
		
		return $res;
	}
	
	protected function getInitialChargeNotBefore($primaryInstallment) {
		$invoiceIds = $primaryInstallment->getInvoicesIdFromReceipt();
		$invoices = array();
		foreach ($invoiceIds as $invoiceId) {
			$invoices[] = Billrun_Bill_Invoice::getInstanceByid($invoiceId);
		}
		$chargeNotBefore = $this->getLatestChargeNotBefore($invoices);
		return !empty($chargeNotBefore) ? $chargeNotBefore : $this->firstDueDate;
	}
	
	protected function getLatestChargeNotBefore($invoices) {
		$chargeNotBefore = false;
		foreach ($invoices as $invoice) {
			$invoiceData = $invoice->getRawData();
			$chargeNotBefore = (empty($chargeNotBefore) && !empty($invoiceData['charge']['not_before'])) ? $invoiceData['charge']['not_before'] : $chargeNotBefore;
			if (!empty($invoiceData['charge']['not_before']) && $invoiceData['charge']['not_before']->sec > $chargeNotBefore->sec) {
				$chargeNotBefore = $invoiceData['charge']['not_before'];
			}
		}

		return $chargeNotBefore;
	}
}
