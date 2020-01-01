<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Merge Installments class
 *
 * @package  Billrun
 * @since    5.11
 */
class Billrun_Bill_Payment_MergedInstallments extends Billrun_Bill_Payment {

	protected $method = 'merge_installments';
	protected $splitBills = array();
	protected $aid;
	protected $uniqueId;
	protected $installmentDueDate;

	public function __construct($options) {
		if (!isset($options['aid']) ) {
			throw new Exception('Missing aid when merging bills');
		}
		if (!empty($options['autoload'])) {
			if (!isset($options['split_bill_id'])) {
				throw new Exception('Missing split bill id when merging bills');
			}
			$this->aid = $options['aid'];
			$this->uniqueId = $options['split_bill_id'];
			$this->splitBills = $this->getMatchingSplitBills($options['aid'], $options['split_bill_id']);
			$this->installmentdueDate = isset($options['due_date']) ? $options['due_date'] : null;
			return;
		}

		parent::__construct($options);
	}

	protected function getMatchingSplitBills($aid, $splitBillId) {
		$unpaidQuery = Billrun_Bill::getUnpaidQuery();
		$basicQuery = array(
			'aid' => $aid,
			'payment_agreement.id' => $splitBillId
		);
		$query = array_merge($basicQuery, $unpaidQuery);
		return Billrun_Bill::getBills($query, array('due_date' => 1));
	}

	protected function merge() {
		$paymentsArr = array();
		$totalAmount = 0;
		foreach ($this->splitBills as $key => $splitBill) {
			$totalAmount += $splitBill['left_to_pay'];
			$paymentsArr['pays'][$splitBill['type']][$splitBill['txid']] = $splitBill['left_to_pay'];
			if ($key == 0 && empty($this->installmentDueDate)) { // sorted array by due_date to get the earliest due date
				$this->installmentDueDate = $splitBill['due_date'];
			}
		}
		$paymentsArr['amount'] = $totalAmount;
		$paymentsArr['fc'] = 'fc';
		$paymentsArr['aid'] = $this->aid;
		$paymentsArr['split_bill_id'] = $this->uniqueId;
		$mergedBill = current(Billrun_Bill::pay($this->method, array($paymentsArr)));
		if (!empty($mergedBill) && !empty($mergedBill->getId())){
			$mergedBill->setDueDate($this->installmentDueDate);
			$success = $mergedBill->insertMergeInstallment();
			return $success;
		}
		
		Billrun_Factory::log("Failed merging installments for aid: " . $this->data['aid'], Zend_Log::ALERT);
		return false;
	}
	
	protected function insertMergeInstallment() {
		$bill = $this->buildInstallment();
		$mergedInstallment = new self($bill);
		$res = $mergedInstallment->save();
		if ($res) {
			return true;
		} else {
			throw new Exception("Merge installments failed");
		}
	}
	
	protected function buildInstallment() {
		$installment['dir'] = 'tc';
		$installment['method'] = $this->method;
		$installment['aid'] = $this->data['aid'];
		$installment['type'] = 'rec';
		$installment['amount'] = $this->data['amount'];
		$installment['bills_merged'] = $this->data['pays'];
		$installment['due_date'] = $this->data['due_date'];
		return $installment;
	}
}