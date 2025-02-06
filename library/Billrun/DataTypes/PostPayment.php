<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class holds data used after payment
 * 
 * @package  DataTypes
 */
class Billrun_DataTypes_PostPayment {

	/**
	 * pre-payment data 
	 * @var Billrun_DataTypes_PrePayment
	 */
	private $prePayment = null;

	/**
	 * status from payment gateway
	 * @var string
	 */
	private $status;

	/**
	 * status from payment gateway
	 * @var string
	 */
	private $subStatus;

	/**
	 * raw response from payment gateway
	 * @var array
	 */
	private $pgResponse = [];

	/**
	 * transaction ID 
	 * @var mixed
	 */
	private $transactionId;

	/**
	 * Payment data
	 * @var array
	 */
	protected $data;

	public function __construct(Billrun_DataTypes_PrePayment $prePayment) {
		$this->prePayment = $prePayment;
		$this->data = $prePayment->getData();
	}

	public function getAmount() {
		return $this->prePayment ? $this->prePayment->getAmount() : false;
	}

	public function getStatus() {
		return $this->status;
	}

	public function setStatus($status) {
		$this->status = $status;
	}

	public function getSubStatus() {
		return $this->subStatus;
	}

	public function setSubStatus($subStatus) {
		$this->subStatus = $subStatus;
	}

	public function getTransactionId() {
		return $this->transactionId;
	}

	public function setTransactionId($transactionId) {
		$this->transactionId = $transactionId;
	}

	public function getPgResponse() {
		return $this->pgResponse;
	}

	public function setPgResponse($response) {
		$this->pgResponse = $response;
	}

	public function getPayment() {
		return $this->prePayment ? $this->prePayment->getPayment() : false;
	}

	public function getCustomerDirection() {
		return $this->prePayment ? $this->prePayment->getCustomerDirection() : false;
	}

	public function getUpdatedBill($billType, $billId) {
		return $this->prePayment ? $this->prePayment->getUpdatedBill($billType, $billId) : false;
	}

	/**
	 * get bills related to the payment
	 * 
	 * @return array
	 */
	public function getRelatedBills() {
		$payment = $this->getPayment();
		if (!$payment) {
			return false;
		}

		switch ($this->getCustomerDirection()) {
			case Billrun_DataTypes_PrePayment::DIR_FROM_CUSTOMER:
				return $payment->getPaidBills();
			case Billrun_DataTypes_PrePayment::DIR_TO_CUSTOMER:
				return $payment->getPaidByBills();
			default:
				return false;
		}
	}

	public function getData() {
		return $this->data;
	}

}
