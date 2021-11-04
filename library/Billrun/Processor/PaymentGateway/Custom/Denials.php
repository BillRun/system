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
		$this->tranIdentifierField = is_array($processorDefinition['processor']['transaction_identifier_field']) ? $processorDefinition['processor']['transaction_identifier_field'] : array (
			'source' => 'data',
			'field' => $processorDefinition['processor']['transaction_identifier_field']
		);
		$this->amountField = is_array($processorDefinition['processor']['amount_field']) ? $processorDefinition['processor']['amount_field'] : array (
			'source' => 'data',
			'field' => $processorDefinition['processor']['amount_field']
		);
		return true;
	}
	
	protected function updatePayments($row, $payment = null) {
		$customFields = $this->getCustomPaymentGatewayFields();
		$payment->setExtraFields($customFields, array_keys($customFields));
		if (is_null($payment)) {
			$message = 'None matching payment for ' . $row['stamp'];
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		$row['aid'] = $payment->getAid();
		if ($payment->isRejection() || $payment->isRejected()) {
			$message = "Payment " . $payment->getId() . " is already rejected and can't been denied";
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		if ($payment->isPendingPayment()) {
			$message = "Payment " . $payment->getId() . " is already pending and can't been denied";
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		if (!empty($this->amountField)) {
			$amount_from_file = in_array($this->amountField['source'], ['header', 'trailer']) ?  $this->{$this->amountField['source'].'Rows'}[$this->amountField['field']] : $row[$this->amountField['field']];
		}
		$txid_from_file = in_array($this->tranIdentifierField['source'], ['header', 'trailer']) ?  $this->{$this->tranIdentifierField['source'].'Rows'}[$this->tranIdentifierField['field']] : $row[$this->tranIdentifierField['field']];
		if (!is_null($amount_from_file) && !Billrun_Util::isEqual(abs($amount_from_file), $payment->getAmount(), Billrun_Bill::precision)) {
			$message = "Amount sent is different than the amount of the payment with txid: " . $txid_from_file . ". denial process has failed for this payment.";
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		if (!is_null($amount_from_file) && $payment->isDenied(abs($amount_from_file))) {
			$message = "Payment " . $txid_from_file . " is already denied";
			Billrun_Factory::log()->log($message, Zend_Log::NOTICE);
			$this->informationArray['errors'][] = $message;
			return;
		}
		
		$row['amount'] = !is_null($amount_from_file) ? $amount_from_file : $payment->getAmount();
		$denial = Billrun_Bill_Payment::createDenial($row, $payment);
		if (!empty($denial)) {
			if (!is_null($payment)) {
				$message = "Denial was created successfully for payment: " . $txid_from_file;
				Billrun_Factory::log()->log($message, Zend_Log::INFO);
				$this->informationArray['info'][] = $message;
				$res = $payment->deny($denial);
				$this->informationArray['transactions']['denied'] ++;
				if (isset($res['status']) && !$res['status']) {
					$this->informationArray['errors'][] = $res['massage'];
				}
			} else {
				Billrun_Factory::log()->log("Denial was created successfully without matching payment", Zend_Log::INFO);
			}
			$this->informationArray['total_denied_amount'] += $payment->getAmount();
		} else {
			$message = "Denial process was failed for payment: " . $txid_from_file;
			$this->informationArray['warnings'][] = $message;
			Billrun_Factory::log()->log($message, Zend_Log::NOTICE);
		}
	}
	
	protected function filterData($data) {
		return array_filter($data['data'], function ($denial) {
			return $denial['status'] != 1;
		});
	}

	public function getType () {
		return static::$type;
	}
}