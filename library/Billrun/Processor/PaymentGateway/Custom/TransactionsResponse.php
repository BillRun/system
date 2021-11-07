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
	protected $dateField;


	public function __construct($options) {
		parent::__construct($options);
	}
	
	protected function updatePayments($row, $payment, $currentProcessor) {
		$customFields = $this->getCustomPaymentGatewayFields();
		$payment->setExtraFields(array_merge(['pg_response' => $this->billSavedFields], $customFields), array_keys($customFields));
		$fileStatus = isset($currentProcessor['file_status']) ? $currentProcessor['file_status'] : null;
		$paymentResponse = (empty($fileStatus) || ($fileStatus == 'mixed')) ? $this->getPaymentResponse($row, $currentProcessor) : $this->getResponseByFileStatus($fileStatus);
                $this->updatePaymentAccordingTheResponse($paymentResponse, $payment, $row);
				if ($paymentResponse['stage'] == 'Rejected') {
					$payment->updatePastRejectionsOnProcessingFiles();
				}
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
                        $message = "Missing transaction_status for file type " . $this->fileType;
			Billrun_Factory::log($message, Zend_Log::DEBUG);
                        $this->informationArray['info'][] = $message;
		}
		$transactionStatusDef = $currentProcessor['processor']['transaction_status'];
		if (!isset($currentProcessor['processor']['transaction_status']['success'])) {
                        $message = "Missing transaction_status success definition for " . $this->fileType;
			Billrun_Factory::log($message, Zend_Log::DEBUG);
                        $this->informationArray['info'][] = $message;
		}
		$successConditions = $transactionStatusDef['success'];
		$rejectionConditions = isset($transactionStatusDef['rejection']) ? $transactionStatusDef['rejection'] : array();
		$stage = $this->getRowStageByConditions($row, $successConditions, $rejectionConditions);
		return array('status' => 'mixed', 'stage' => $stage);
	}
	
	protected function mapProcessorFields($processorDefinition) {
		if (empty($processorDefinition['processor']['amount_field']) ||
			empty($processorDefinition['processor']['transaction_identifier_field'])) {
                        $message = "Missing definitions for file type " . $processorDefinition['file_type'];
			Billrun_Factory::log($message, Zend_Log::DEBUG);
                        $this->informationArray['errors'][] = $message;
			return false;
		}
		$this->amountField = $processorDefinition['processor']['amount_field'];
		$this->tranIdentifierField = $processorDefinition['processor']['transaction_identifier_field'];
		if (isset($processorDefinition['processor']['date_field'])) {
			$this->dateField = is_array($processorDefinition['processor']['date_field']) ? $processorDefinition['processor']['date_field'] : array(
				'source' => 'data',
				'field' => $processorDefinition['processor']['date_field']
			);
		} else {
			$this->dateField = null;
		}
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
                        $message = "Can't define the transaction status for " . $this->fileType;
                        $this->informationArray['errors'][] = $message;
			throw new Exception($message);
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
	protected function updatePaymentAccordingTheResponse($response, $payment, $row) {
		$urt = $this->getPaymentUrt($row);
		if ($response['stage'] == "Completed") { // payment succeeded 
			if ($payment->isPendingPayment()) {
				$payment->setUrt($urt);
				$payment->setPending(false);
				$payment->updateConfirmation();
				$payment->setPaymentStatus($response, $this->gatewayName);
				$this->informationArray['total_confirmed_amount'] += $payment->getAmount();
				Billrun_Factory::log('Confirming transaction ' . $payment->getId(), Zend_Log::INFO);
			} else {
				Billrun_Factory::log('Transaction ' . $payment->getId() . ' already confirmed', Zend_Log::NOTICE);
			}
		} else { //handle rejections
			if (!$payment->isRejected()) {
				$payment->setPending(false);
				Billrun_Factory::log('Rejecting transaction ' . $payment->getId(), Zend_Log::INFO);
				$this->informationArray['info'][] = 'Rejecting transaction  ' . $payment->getId();
				$rejection = $payment->getRejectionPayment($response);
				$rejection->setUrt($urt);
				$rejection->setConfirmationStatus(false);
				$rejection->save();
				$payment->markRejected();
				$payment->setUrt($urt);
				$this->informationArray['transactions']['rejected']++;
				$this->informationArray['total_rejected_amount']+=$payment->getAmount();
				Billrun_Factory::dispatcher()->trigger('afterRejection', array($payment->getRawData()));
			} else {
				$message = 'Transaction ' . $payment->getId() . ' already rejected';
				Billrun_Factory::log($message, Zend_Log::NOTICE);
				$this->informationArray['info'][] = $message;
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
	
	public function getType () {
		return static::$type;
	}
	
	public function getPaymentUrt($row){
		$date = in_array($this->dateField['source'], ['header', 'trailer']) ?  $this->{$this->dateField['source'].'Rows'}[$this->dateField['field']] : $row[$this->dateField['field']];
		if(!is_null($date)){
			return strtotime($date);
		} else {
			$message = "Couldn't find date field: " . $this->dateField['field'] . " in the relevant " . $this->dateField['source'] . " row. Current time was taken..";
			$this->informationArray['warnings'][] = $message;
			Billrun_Factory::log()->log($message, Zend_Log::WARN);
			return time();
		}
	}

}
