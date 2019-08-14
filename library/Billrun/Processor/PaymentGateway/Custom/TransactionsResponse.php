<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Processor for payment gateways transactions files.
 * @package  Billing
 * @since    5.10
 */
class Billrun_Processor_PaymentGateway_Custom_TransactionsResponse extends Billrun_Processor_PaymentGateway_Custom {

	protected static $type = 'transactions_response';
	
	protected $tokenField = null;
	protected $amountField = null;
	protected $tranIdentifierField = null;
	protected $dealStatusField = null;
	protected $validTransacionRegex;


	public function __construct($options) {
		parent::__construct($options);
	}

	protected function validateProcessorDefinitions($processorDefinition) {
		if (empty($processorDefinition['processor']['token_field']) || empty($processorDefinition['processor']['amount_field']) ||
			empty($processorDefinition['processor']['deal_status_field']) || empty($processorDefinition['processor']['transaction_identifier_field']) || 
			empty($processorDefinition['processor']['valid_transaction_regex'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
			return false;
		}
		return true;
	}
	
	protected function updatePayments($row, $payment) {
		$paymentResponse = $this->getPaymentResponse($row);
		$payment->setPending(false);
		Billrun_Bill_Payment::updateAccordingToStatus($paymentResponse, $payment, $this->gatewayName);
		if ($paymentResponse['stage'] == 'Completed') {
			$payment->markApproved($paymentResponse['stage']);
			$billData = $payment->getRawData();
			if (isset($billData['left_to_pay']) && $billData['due']  > (0 + Billrun_Bill::precision)) {
				Billrun_Factory::dispatcher()->trigger('afterRefundSuccess', array($billData));
			}
			if (isset($billData['left']) && $billData['due'] < (0 - Billrun_Bill::precision)) {
				Billrun_Factory::dispatcher()->trigger('afterChargeSuccess', array($billData));
			}
		}
	}
	
	protected function getPaymentResponse($row) {
		$stage = 'Rejected';
		if ($this->isValidTransaction($row)) {
			$stage = 'Completed';
		}

		return array('status' => $row[$this->dealStatusField], 'stage' => $stage);
	}
	
	protected function mapProcessorFields($processor) {
		$this->tokenField = $processor['token_field'];
		$this->amountField = $processor['amount_field'];
		$this->tranIdentifierField = $processor['transaction_identifier_field'];
		$this->dealStatusField = $processor['deal_status_field'];
		$this->validTransacionRegex = $processor['valid_transaction_regex'];
	}

	protected function isValidTransaction($row) {
		if (preg_match($this->validTransacionRegex, $row[$this->dealStatusField])) {
			return true;
		} else {
			return false;
		}
	}

}