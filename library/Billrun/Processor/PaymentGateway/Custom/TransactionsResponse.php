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
		}
		$transactionStatusDef = $currentProcessor['processor']['transaction_status'];
		if (!isset($currentProcessor['processor']['transaction_status']['success'])) {
			Billrun_Factory::log("Missing transaction_status success definition for " . $this->fileType, Zend_Log::DEBUG);
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
                        if ($payment->isPendingPayment()){
                            $payment->setPending(false);
                            $payment->updateConfirmation();
                            $payment->setPaymentStatus($response, $this->gatewayName);
                            Billrun_Factory::log('Confirming transaction ' . $payment->getId() , Zend_Log::INFO);
                        }else{
                            Billrun_Factory::log('Transaction ' . $payment->getId() . ' already confirmed', Zend_Log::NOTICE);
                        }
		} else if ($response['stage'] == "Pending") { // handle pending
                        $payment->setPending(false);
			$payment->setPaymentStatus($response, $this->gatewayName);
		} else { //handle rejections
			if (!$payment->isRejected()) {
                                $payment->setPending(false);
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