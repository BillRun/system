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
	
	const PG_RESPONSE_SUCCESS = 'success';
	const PG_RESPONSE_FAILURE = 'failure';
	const PG_RESPONSE_PENDING = 'pending';
	
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
	
	public function __construct(Billrun_DataTypes_PrePayment $prePayment, $transactionId = '') {
		$this->prePayment = $prePayment;
		$this->setDue($prePayment->getDue());
		$this->setTransactionId($transactionId);
	}
	
	public function getDue() {
		return $this->due;
	}
	
	public function setDue($due) {
		$this->due = floatval($due);
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
}
