<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Processor for payment gateways denials files.
 * @package  Billing
 * @since    5.10
 */
class Billrun_Processor_PaymentGateway_Custom_Denials extends Billrun_Processor_PaymentGateway_Custom {

	protected static $type = 'denials';
	protected $tranIdentifierField;
	protected $amountField;

	public function __construct($options) {
		parent::__construct($options);
	}

	protected function validateProcessorDefinitions($processorDefinition) {
		if (empty($processorDefinition['processor']['amount_field']) || empty($processorDefinition['processor']['transaction_identifier_field'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
			return false;
		}
		return true;
	}
	
	protected function mapProcessorFields($processorDefinition) {
		if (empty($processorDefinition['processor']['transaction_identifier_field']) || empty($processorDefinition['processor']['amount_field'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
			return false;
		}
		
		$this->tranIdentifierField = $processorDefinition['processor']['transaction_identifier_field'];
		$this->amountField = $processorDefinition['processor']['amount_field'];
	}
	
	protected function updatePayments($row, $payment = null) {
		if (is_null($payment)) {
			Billrun_Factory::log('None matching payment for ' . $row['stamp'], Zend_Log::ALERT);
			return;
		}
		$row['aid'] = $payment->getAid();
		if (!is_null($payment)) {
			if (abs($row[$this->amountField]) > $payment->getAmount()) {
				Billrun_Factory::log("Amount sent is bigger than the amount of the payment with txid: " . $row[$this->tranIdentifierField], Zend_Log::ALERT);
				return;
			}
			if ($payment->isDenied(abs($row[$this->amountField]))) {
				Billrun_Factory::log()->log("Payment " . $row[$this->tranIdentifierField] . " is already denied", Zend_Log::NOTICE);
				return;
			}
		}
		$denial = Billrun_Bill_Payment::createDenial($row, $payment);
		if (!empty($denial)) {
			if (!is_null($payment)) {
				Billrun_Factory::log()->log("Denial was created successfully for payment: " . $row[$this->tranIdentifierField], Zend_Log::NOTICE);
				$payment->deny($denial);
				$paymentSaved = $payment->save();
				if (!$paymentSaved) {
					Billrun_Factory::log()->log("Denied flagging failed for rec " . $row[$this->tranIdentifierField], Zend_Log::ALERT);
				}
			} else {
				Billrun_Factory::log()->log("Denial was created successfully without matching payment", Zend_Log::NOTICE);
			}
		} else {
			Billrun_Factory::log()->log("Denial process was failed for payment: " . $row[$this->tranIdentifierField], Zend_Log::NOTICE);
		}
	}
	
	protected function filterData($data) {
		return array_filter($data['data'], function ($denial) {
			return $denial['status'] != 1;
		});
	}

}