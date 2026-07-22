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
	protected $tranIdentifierFields = null;
	protected $take_first = true;
	protected $dateField;


	public function __construct($options) {
		parent::__construct($options);
	}
	
	protected function updatePayments($row, $payment, $currentProcessor) {
		$customFields = $this->getCustomPaymentGatewayFields($row);
		$this->setTransactionsFields($row,  $currentProcessor);
		$fileStatus = isset($currentProcessor['file_status']) ? $currentProcessor['file_status'] : null;
		$paymentResponse = (empty($fileStatus) || ($fileStatus == 'mixed')) ? $this->getPaymentResponse($row, $currentProcessor) : $this->getResponseByFileStatus($fileStatus);
                $this->updatePaymentAccordingTheResponse($paymentResponse, $payment, $row);
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
		$payment->setExtraFields(array_merge(['vendor_response' => $this->billSavedFields], $customFields), array_merge(array_keys($customFields), ['vendor_response']));
		Billrun_Factory::dispatcher()->trigger('afterUpdatePayments', array($row, $payment, $currentProcessor, $billData, $this));
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
			(!isset($processorDefinition['processor']['transaction_identifier_field']) && 
			!isset($processorDefinition['processor']['transaction_identifier_fields']))) {
            $message = "Missing definitions for file type " . $processorDefinition['file_type'];
			Billrun_Factory::log($message, Zend_Log::DEBUG);
            $this->informationArray['errors'][] = $message;
			return false;
		}
		if (isset($processorDefinition['processor']['transaction_identifier_field'])){
			$this->tranIdentifierField = $processorDefinition['processor']['transaction_identifier_field'];
		} else if (isset($processorDefinition['processor']['transaction_identifier_fields'])) {
			$this->tranIdentifierFields = Billrun_Util::getIn($processorDefinition['processor']['transaction_identifier_fields'], 'conditions', null);
			$this->take_first = Billrun_Util::getIn($processorDefinition['processor']['transaction_identifier_fields'], 'take_first', true);
		}

		if (empty($this->tranIdentifierField) && empty($this->tranIdentifierFields)) {
			$message = "No transaction identifier configuration was found for file type " . $processorDefinition['file_type'];
			Billrun_Factory::log($message, Zend_Log::DEBUG);
            $this->informationArray['errors'][] = $message;
			return false;
		}
		parent::initProcessorFields(['tran_identifier_field' => 'transaction_identifier_field' ,'amount_field' => 'amount_field', 'date_field' => 'date_field'], $processorDefinition);
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
		$urt = !is_null($this->dateField) ? strtotime($this->getPaymentUrt($row)) : time();
		if ($response['stage'] == "Completed") { // payment succeeded 
                        if ($payment->isPendingPayment()){
                            $payment->setPending(false);
                            $payment->updateConfirmation();
							$payment->setUrt($urt);
                            $payment->setPaymentStatus($response, $this->gatewayName);
							$payment->setExtraFields($this->transactionsFields['success'], ['vendor_response', 'payment_method']);
                            $this->informationArray['total_confirmed_amount']+=$payment->getAmount();
                            Billrun_Factory::log('Confirming transaction ' . $payment->getId() , Zend_Log::INFO);
                        }else{
                            Billrun_Factory::log('Transaction ' . $payment->getId() . ' already confirmed', Zend_Log::NOTICE);
                        }
		} else { //handle rejections
			if (!$payment->isRejected()) {
				$payment->setPending(false);
				Billrun_Factory::log('Rejecting transaction ' . $payment->getId(), Zend_Log::INFO);
				$this->informationArray['info'][] = 'Rejecting transaction  ' . $payment->getId();
				$rejection = $payment->getRejectionPayment($response);
				$rejection->setConfirmationStatus(false);
				$rejection->setExtraFields($this->transactionsFields['rejection'],['vendor_response', 'payment_method']);
				$rejection->setUrt($urt);
				$rejection->save();
				$payment->setUrt($urt);
				$payment->markRejected();
				$payment->updatePastRejectionsOnProcessingFiles();
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

	protected function setTransactionsFields($row, $currentProcessor){
		$this->transactionsFields = [];
		$transactionsFieldsStructure = [
			'response_code_field' => [
				'default_path' => 'response_code',
				'billPaths' => ['rejection_code', 'vendor_response.code'],
				'type' => ['rejection']
			],
			'four_digits_field' => [
				'default_path' => 'four_digits',
				'billPaths' => ['payment_method.last_four_digits'],
				"substring" => [
					"offset" => -4,
					"length" => 4,
				]

			],
			'card_expiration_field' => [
				'default_path' => 'card_expiration',
				'billPaths' => ['payment_method.expiration_date']
			],
			'voucher_number_field'=> [
				'default_path' => 'voucher_number',
				'billPaths' => ['vendor_response.payment_identifier']
			],
		];
		foreach ($transactionsFieldsStructure as $field => $structure) {
			$path = $currentProcessor['processor'][$field] ?? $structure['default_path'];
			$value = Billrun_Util::getIn($row, $path);
			if(isset($value)){
				$value = Billrun_Util::formattingValue($structure, $value);
				$types = $structure['type'] ?? ['rejection', 'success'];
				foreach ($types as $type) {
					foreach ($structure['billPaths'] as $billPath) {
						Billrun_Util::setIn($this->transactionsFields, [$type , $billPath], $value);
					}
				}
			}else{
				Billrun_Factory::log("Missing value for $path in the file response" , Zend_Log::DEBUG);
			}
		}
	}
}
