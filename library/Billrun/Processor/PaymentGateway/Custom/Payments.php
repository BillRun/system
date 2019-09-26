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
	protected $dbNumericValuesFields = array('invoice_id');

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
		if (count($bill) == 0) {
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
			Billrun_Bill::pay('cash', array($paymentParams));
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Payment process was failed for payment: " . $e->getMessage(), Zend_Log::NOTICE);
		}
		Billrun_Factory::log()->log("Payment was created successfully for " . $this->identifierField . ' ' . $row[$this->identifierField], Zend_Log::INFO);
	}

	protected function findBillByUniqueIdentifier($id) {
//		if (in_array($this->identifierField , $this->dbNumericValuesFields) && Billrun_Util::IsIntegerValue($id)) {
//			$id = intval($id);
//		}
		return $this->bills->query(array('type' => 'inv', 'invoice_id' => intval($id)))->cursor();
	}
}