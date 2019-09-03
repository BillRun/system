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
	
	protected function updatePayments($row, $payment) {
		$fileStatus = isset($this->configByType['file_status']) ? $this->configByType['file_status'] : null;
		$paymentResponse = (empty($fileStatus) || ($fileStatus == 'mixed')) ? $this->getPaymentResponse($row) : $this->getResponseByFileStatus($fileStatus);
		$payment->setPending(false);
		$this->updatePaymentAccordingTheResponse($paymentResponse, $payment);
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
	
	protected function mapProcessorFields($processorDefinition) {
		if (empty($processorDefinition['processor']['token_field']) || empty($processorDefinition['processor']['amount_field']) ||
			empty($processorDefinition['processor']['deal_status_field']) || empty($processorDefinition['processor']['transaction_identifier_field']) || 
			empty($processorDefinition['processor']['valid_transaction_regex'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
			return false;
		}
		$this->tokenField = $processorDefinition['processor']['token_field'];
		$this->amountField = $processorDefinition['processor']['amount_field'];
		$this->tranIdentifierField = $processorDefinition['processor']['transaction_identifier_field'];
		$this->dealStatusField = $processorDefinition['processor']['deal_status_field'];
		$this->validTransacionRegex = $processorDefinition['processor']['valid_transaction_regex'];
		return true;
	}

	protected function isValidTransaction($row) {
		if (preg_match($this->validTransacionRegex, $row[$this->dealStatusField])) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Updating the payment status.
	 * 
	 * @param $response - the returned payment gateway status and stage of the payment.
	 * @param Payment payment- the current payment.
	 * 
	 */
	protected function updatePaymentAccordingTheResponse($response, $payment) {
		if ($response['stage'] == "Completed") { // payment succeeded 
			$payment->updateConfirmation();
			$payment->setPaymentStatus($response, $this->gatewayName);
		} else if ($response['stage'] == "Pending") { // handle pending
			$payment->setPaymentStatus($response, $this->gatewayName);
		} else { //handle rejections
			if (!$payment->isRejected()) {
				Billrun_Factory::log('Rejecting transaction  ' . $payment->getId(), Zend_Log::INFO);
				$rejection = $payment->getRejectionPayment($response);
				$rejection->setConfirmationStatus(false);
				$rejection->save();
				$payment->markRejected();
				Billrun_Factory::dispatcher()->trigger('afterRejection', array($payment->getRawData()));
			} else {
				Billrun_Factory::log('Transaction ' . $payment->getId() . ' already rejected', Zend_Log::NOTICE);
			}
		}
	}

	protected function getResponseByFileStatus($fileStatus) {
		switch ($fileStatus) {
			case 'only_rejections':
				return array('status' => 'only_rejections', 'stage' => 'Rejected');
				break;
			case 'only_acceptance':
				return array('status' => 'only_acceptance', 'stage' => 'Completed');
				break;
			default:
				throw new Exception('Unknown file status');
				break;
		}
	}

}