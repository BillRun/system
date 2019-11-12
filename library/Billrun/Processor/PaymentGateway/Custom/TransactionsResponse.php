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
	
	use Billrun_Traits_ConditionsCheck;

	protected static $type = 'transactions_response';
	protected $amountField = null;
	protected $tranIdentifierField = null;


	public function __construct($options) {
		parent::__construct($options);
	}
	
	protected function updatePayments($row, $payment, $currentProcessor) {
		$fileStatus = isset($currentProcessor['file_status']) ? $currentProcessor['file_status'] : null;
		$paymentResponse = (empty($fileStatus) || ($fileStatus == 'mixed')) ? $this->getPaymentResponse($row, $currentProcessor) : $this->getResponseByFileStatus($fileStatus);
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
	
	protected function getPaymentResponse($row, $currentProcessor) {
		if (!isset($currentProcessor['processor']['transaction_status'])) {
			Billrun_Factory::log("Missing transaction_status for file type " . $this->fileType, Zend_Log::DEBUG);
                        $this->informationArray['info'][] = "Missing transaction_status for file type " . $this->fileType;
		}
		$transactionStatusDef = $currentProcessor['processor']['transaction_status'];
		if (!isset($currentProcessor['processor']['transaction_status']['success'])) {
			Billrun_Factory::log("Missing transaction_status success definition for " . $this->fileType, Zend_Log::DEBUG);
                        $this->informationArray['info'][] = "Missing transaction_status success definition for " . $this->fileType;
		}
		$successConditions = $transactionStatusDef['success'];
		$rejectionConditions = isset($transactionStatusDef['rejection']) ? $transactionStatusDef['rejection'] : array();
		$stage = $this->getRowStageByConditions($row, $successConditions, $rejectionConditions);
		return array('status' => 'mixed', 'stage' => $stage);
	}
	
	protected function mapProcessorFields($processorDefinition) {
		if (empty($processorDefinition['processor']['amount_field']) ||
			empty($processorDefinition['processor']['transaction_identifier_field'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
                        $this->informationArray['errors'][] = "Missing definitions for file type " . $processorDefinition['file_type'];
			return false;
		}
		$this->amountField = $processorDefinition['processor']['amount_field'];
		$this->tranIdentifierField = $processorDefinition['processor']['transaction_identifier_field'];
		return true;
	}

	protected function getRowStageByConditions($row, $sucessConditions, $rejectionConditions) {
		$stage = false;
		if ($this->isConditionsMeet($row, $sucessConditions)) {
			$stage = 'Completed';
		} else if (empty($rejectionConditions) || $this->isConditionsMeet($row, $rejectionConditions)) {
			$stage = 'Rejected';
		}
		if (empty($stage)) {
                        $this->informationArray['errors'][] = "Can't define the transaction status for " . $this->fileType;
			throw new Exception("Can't define the transaction status for " . $this->fileType);
		}
		
		return $stage;
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
                        $this->informationArray['transactions']['confirmed']++;
		} else if ($response['stage'] == "Pending") { // handle pending
			$payment->setPaymentStatus($response, $this->gatewayName);
		} else { //handle rejections
			if (!$payment->isRejected()) {
				Billrun_Factory::log('Rejecting transaction  ' . $payment->getId(), Zend_Log::INFO);
                                $this->informationArray['info'][] = 'Rejecting transaction  ' . $payment->getId();
				$rejection = $payment->getRejectionPayment($response);
				$rejection->setConfirmationStatus(false);
				$rejection->save();
				$payment->markRejected();
                                $this->informationArray['transactions']['rejected']++;
				Billrun_Factory::dispatcher()->trigger('afterRejection', array($payment->getRawData()));
			} else {
				Billrun_Factory::log('Transaction ' . $payment->getId() . ' already rejected', Zend_Log::NOTICE);
                                $this->informationArray['info'][] = 'Transaction ' . $payment->getId() . ' already rejected';
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
                                $this->informationArray['errors'][] = 'Unknown file status';
				throw new Exception('Unknown file status');
				break;
		}
	}

}