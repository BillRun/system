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
                        $message = "Missing definitions for file type " . $processorDefinition['file_type'];
			Billrun_Factory::log($message, Zend_Log::DEBUG);
			$this->informationArray['errors'][] = $message;
                        return false;
		}
		
		$this->tranIdentifierField = $processorDefinition['processor']['transaction_identifier_field'];
		$this->amountField = $processorDefinition['processor']['amount_field'];
		return true;
	}
	
	protected function updatePayments($row, $payment = null) {
		if (is_null($payment)) {
                        $message = 'None matching payment for ' . $row['stamp'];
			Billrun_Factory::log($message, Zend_Log::ALERT);
                        $this->informationArray['errors'][] = $message;
			return;
		}
		$row['aid'] = $payment->getAid();
		if (abs($row[$this->amountField]) !== $payment->getAmount()) {
                        $message = "Amount sent is different than the amount of the payment with txid: " . $row[$this->tranIdentifierField] . ". denial process has failed for this payment.";
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
                        return;
                }
		if ($payment->isDenied(abs($row[$this->amountField]))) {
                        $message = "Payment " . $row[$this->tranIdentifierField] . " is already denied";
			Billrun_Factory::log()->log($message, Zend_Log::NOTICE);
			$this->informationArray['errors'][] = $message;
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
                                $message = "Denial was created successfully for payment: " . $row[$this->tranIdentifierField];
				Billrun_Factory::log()->log($message, Zend_Log::NOTICE);
				$this->informationArray['info'][] = $message;
                                $payment->deny($denial);
				$paymentSaved = $payment->save();
                                $this->informationArray['transactions']['denied']++;
				if (!$paymentSaved) {
                                        $message = "Denied flagging failed for rec " . $row[$this->tranIdentifierField];
					$this->informationArray['errors'][] = $message;
                                        Billrun_Factory::log()->log($message, Zend_Log::ALERT);
				}
			} else {
				Billrun_Factory::log()->log("Denial was created successfully without matching payment", Zend_Log::NOTICE);
			}
		} else {
                        $message = "Denial process was failed for payment: " . $row[$this->tranIdentifierField];
                        $this->informationArray['warnings'][] = $message;
			Billrun_Factory::log()->log($message, Zend_Log::NOTICE);
		}
	}
	
	protected function filterData($data) {
		return array_filter($data['data'], function ($denial) {
			return $denial['status'] != 1;
		});
	}

}