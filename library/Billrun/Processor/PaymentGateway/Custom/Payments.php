<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Processor for payment gateways payments files.
 * @package  Billing
 * @since    5.10
 */
class Billrun_Processor_PaymentGateway_Custom_Payments extends Billrun_Processor_PaymentGateway_Custom {

	protected static $type = 'payments';
	protected $identifierField;
	protected $amountField;
	protected $method = 'cash';

	public function __construct($options) {
		parent::__construct($options);
	}
	
	protected function mapProcessorFields($processorDefinition) {
		if (empty($processorDefinition['processor']['identifier_field'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
			return false;
		}
		
		$this->identifierField = $processorDefinition['processor']['identifier_field'];
		$this->amountField = isset($processorDefinition['processor']['amount_field']) ? $processorDefinition['processor']['amount_field'] : null;
		return true;
	}

	protected function updatePayments($row, $payment = null) {
		$bill = $this->findBillByUniqueIdentifier($row[$this->identifierField]);
		if (empty($bill)) {
			Billrun_Factory::log("Didn't find bill with " . $row[$this->identifierField] . " value in " . $this->identifierField . " field", Zend_Log::ALERT);
			return;
		}
		if (count($bill) > 1) {
			Billrun_Factory::log($this->identifierField . " field isn't unique", Zend_Log::ALERT);
			return;
		}
		$billData = $bill->current()->getRawData();
		$billAmount = !empty($this->amountField) ? $row[$this->amountField] : $billData['amount'];
		$paymentParams['amount'] = $billAmount;
		$paymentParams['dir'] = 'fc';
		$paymentParams['aid'] = $billData['aid'];
		$id = isset($billData['invoice_id']) ? $billData['invoice_id'] : $billData['txid'];	
		$amount = $billAmount;
		$payDir = isset($billData['left']) ? 'paid_by' : 'pays';
		$paymentParams[$payDir][$billData['type']][$id] = $amount;
		try {
			$paidRes = Billrun_Bill::pay('cash', array($paymentParams));
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Payment process was failed for payment: " . $e->getMessage(), Zend_Log::NOTICE);
		}
		
		
		
		
		
//		if (!empty($paidRes)) {
//			if (!is_null($payment)) {
//				Billrun_Factory::log()->log("Denial was created successfully for payment: " . $row[$this->tranIdentifierField], Zend_Log::NOTICE);
//				$payment->deny($denial);
//				$paymentSaved = $payment->save();
//				if (!$paymentSaved) {
//					Billrun_Factory::log()->log("Denied flagging failed for rec " . $row[$this->tranIdentifierField], Zend_Log::ALERT);
//				}
//			} else {
//				Billrun_Factory::log()->log("Denial was created successfully without matching payment", Zend_Log::NOTICE);
//			}
//		} else {
//			Billrun_Factory::log()->log("Denial process was failed for payment: " . $row[$this->tranIdentifierField], Zend_Log::NOTICE);
//		}
	}
	

	protected function findBillByUniqueIdentifier($id) {
		return $this->bills->query(array($this->identifierField => $id))->cursor();
	}
}