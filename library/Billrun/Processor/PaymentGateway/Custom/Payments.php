<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Processor for payment gateways payments files.
 * @package  Billing
 * @since    5.10
 */
class Billrun_Processor_PaymentGateway_Custom_Payments extends Billrun_Processor_PaymentGateway_Custom {

	protected static $type = 'payments';
	protected $identifierField;
	protected $amountField;
	protected $method = 'cash';
	protected $dbNumericValuesFields = array('invoice_id');
	protected $date_field;

	public function __construct($options) {
		parent::__construct($options);
	}
	
	protected function mapProcessorFields($processorDefinition) {
		$identifier = $processorDefinition['processor']['identifier_field'];
		if (empty($identifier) ||
			(is_array($identifier) && array_keys($identifier) !== ['source', 'field', 'file_field'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
			return false;
		}
		
		$this->identifierField = is_array($identifier) ? $identifier : [
			'source' => 'data',
			'field' => 'invoice_id',
			'file_field' => $identifier
		];
		parent::initProcessorFields(['amount_field' => 'amount_field', 'date_field' => 'date_field'], $processorDefinition);
		return true;
	}

	protected function updatePayments($row, $payment = null) {
		$identifier_val = $this->getIdentifierValue($row);
		$bill = $this->findBillsByIdentifier($identifier_val);
		if (count($bill) == 0) {
			$message = "Didn't find bill with " . $identifier_val . " value in " . $this->identifierField['file_field'] . " field in " . $this->identifierField['source'] . " segment.";
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		if (count($bill) > 1 && $this->identifierField['field'] == 'invoice_id') {
			$message = $this->identifierField['field'] . " field isn't unique";
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		$billData = $bill->current()->getRawData();
		if (!empty($this->amountField)) {
			//TODO : support multiple header/footer lines
			$optional_amount = in_array($this->amountField['source'], ['header', 'trailer']) ?  $this->{$this->amountField['source'].'Rows'}[0][$this->amountField['field']] : $row[$this->amountField['field']];
		}
		$billAmount = !is_null($optional_amount) ? $optional_amount : $billData['amount'];
		$paymentParams['amount'] = $billAmount;
		$paymentParams['dir'] = 'fc';
		$paymentParams['aid'] = $billData['aid'];
		if ($this->linkToInvoice && ($this->identifierField['field'] == 'invoice_id')) {
			$id = isset($billData['invoice_id']) ? $billData['invoice_id'] : $billData['txid'];	
			$amount = $billAmount;
			$payDir = isset($billData['left']) ? 'paid_by' : 'pays';
			$paymentParams[$payDir][$billData['type']][$id] = $amount;
		}
		if (!is_null($this->dateField)) {
			$paymentParams['urt'] = $this->getPaymentUrt($row);
		}
		try {
			$ret = Billrun_PaymentManager::getInstance()->pay('cash', array($paymentParams));
		} catch (Exception $e) {
			$message = "Payment process was failed for account : " . $paymentParams['aid'] . ". Error: " . $e->getMessage();
			Billrun_Factory::log()->log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		if (isset($ret['payment'])) {
			$customFields = $this->getCustomPaymentGatewayFields();
			foreach ($ret['payment'] as $index => $returned_payment) {
				$payment_data = $returned_payment->getRawData();
				foreach ($payment_data['pays'] as $value) {
					if (is_array($value)) {
						$message = "Payment " . $payment_data['txid'] . " paid " . $value['amount'] . " from invoice : " . $value['id'];
						Billrun_Factory::log()->log($message, Zend_Log::INFO);
						$this->informationArray['info'][] = $message;
					}
				}
				if (!Billrun_Util::isEqual($payment_data['left'], 0, Billrun_Bill::precision)) {
					$message = "Payment " . $payment_data['txid'] . " left amount is " . $payment_data['left'] . " after processing the received transaction.";
					Billrun_Factory::log()->log($message, Zend_Log::INFO);
					$this->informationArray['info'][] = $message;
				}
				$returned_payment->setExtraFields($customFields, array_keys($customFields));
			}
		}
        $this->informationArray['transactions']['confirmed']++;
        $this->informationArray['total_confirmed_amount']+=$paymentParams['amount'];
        $message = "Payment was created successfully for " . $this->identifierField['field'] . ': ' . $identifier_val;
		Billrun_Factory::log()->log($message, Zend_Log::INFO);
		$this->informationArray['info'][] = $message;
	}

	protected function findBillsByIdentifier($val) {
//		if (in_array($this->identifierField , $this->dbNumericValuesFields) && Billrun_Util::IsIntegerValue($id)) {
//			$id = intval($id);
//		}
		$query = array('type' => 'inv', $this->identifierField['field'] => intval($val));
		if ($this->identifierField['field'] == 'aid') {
			$query['left_to_pay'] = array('$gt' => 0);
		}
		return $this->bills->query($query)->cursor();
	}
	
	public function getIdentifierValue($row){
		return in_array($this->identifierField['source'], ['header', 'trailer']) ?  $this->{$this->identifierField['source'].'Rows'}[0][$this->identifierField['file_field']] : $row[$this->identifierField['file_field']];
	}
	
	public function getType () {
		return static::$type;
	}

}
