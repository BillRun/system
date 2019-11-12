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
	
	protected function mapProcessorFields($processorDefinition) {
		if (empty($processorDefinition['processor']['transaction_identifier_field']) || empty($processorDefinition['processor']['amount_field'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
			$this->informationArray['errors'][] = "Missing definitions for file type " . $processorDefinition['file_type'];
                        return false;
		}
		
		$this->tranIdentifierField = $processorDefinition['processor']['transaction_identifier_field'];
		$this->amountField = $processorDefinition['processor']['amount_field'];
		return true;
	}
	
	protected function updatePayments($row, $payment = null) {
		if (is_null($payment)) {
			Billrun_Factory::log('None matching payment for ' . $row['stamp'], Zend_Log::ALERT);
                        $this->informationArray['errors'][] = 'None matching payment for ' . $row['stamp'];
			return;
		}
		$row['aid'] = $payment->getAid();
		if (abs($row[$this->amountField]) > $payment->getAmount()) {
			Billrun_Factory::log("Amount sent is bigger than the amount of the payment with txid: " . $row[$this->tranIdentifierField], Zend_Log::ALERT);
			$this->informationArray['errors'][] = "Amount sent is bigger than the amount of the payment with txid: " . $row[$this->tranIdentifierField];
                        return;
		}
		if ($payment->isDenied(abs($row[$this->amountField]))) {
			Billrun_Factory::log()->log("Payment " . $row[$this->tranIdentifierField] . " is already denied", Zend_Log::NOTICE);
			$this->informationArray['errors'][] = "Payment " . $row[$this->tranIdentifierField] . " is already denied";
                        return;
		}
		if (!empty($this->amountField) && !empty($row[$this->amountField])) {
			$row['amount'] = $row[$this->amountField];
		}
		else {
			$row['amount'] = $payment->getAmount();
		}
		$denial = Billrun_Bill_Payment::createDenial($row, $payment);
		if (!empty($denial)) {
			if (!is_null($payment)) {
				Billrun_Factory::log()->log("Denial was created successfully for payment: " . $row[$this->tranIdentifierField], Zend_Log::NOTICE);
				$this->informationArray['info'][] = "Denial was created successfully for payment: " . $row[$this->tranIdentifierField];
                                $payment->deny($denial);
				$paymentSaved = $payment->save();
                                $this->informationArray['transactions']['denied']++;
				if (!$paymentSaved) {
					$this->informationArray['errors'][] = "Denied flagging failed for rec " . $row[$this->tranIdentifierField];
                                        Billrun_Factory::log()->log("Denied flagging failed for rec " . $row[$this->tranIdentifierField], Zend_Log::ALERT);
				}
			} else {
				Billrun_Factory::log()->log("Denial was created successfully without matching payment", Zend_Log::NOTICE);
			}
		} else {
                        $this->informationArray['warnings'][] = "Denial process was failed for payment: " . $row[$this->tranIdentifierField];
			Billrun_Factory::log()->log("Denial process was failed for payment: " . $row[$this->tranIdentifierField], Zend_Log::NOTICE);
		}
	}
	
	protected function filterData($data) {
		return array_filter($data['data'], function ($denial) {
			return $denial['status'] != 1;
		});
	}

}