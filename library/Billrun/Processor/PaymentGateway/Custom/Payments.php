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
		if (empty($processorDefinition['processor']['identifier_field'])) {
			Billrun_Factory::log("Missing definitions for file type " . $processorDefinition['file_type'], Zend_Log::DEBUG);
			return false;
		}
		parent::initProcessorFields(['identifier_field' => 'identifier_field' , 'amount_field' => 'amount_field', 'date_field' => 'date_field'], $processorDefinition);
		return true;
	}

	protected function updatePayments($row, $payment = null) {
		if (!empty($this->identifierField)) {
			//TODO : support multiple header/footer lines
			$file_identifier_value = in_array($this->identifierField['source'], ['header', 'trailer']) ?  $this->{$this->identifierField['source'].'Rows'}[0][$this->identifierField['field']] : $row[$this->identifierField]['field'];
		}
		$bill = $this->findBillByUniqueIdentifier($file_identifier_value);
		if (count($bill) == 0) {
			$message = "Didn't find bill with " . intval($file_identifier_value) . " value in " . $this->identifierField['field'] . " field";
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		if (count($bill) > 1) {
			$message = $this->identifierField['field'] . " field isn't unique";
			Billrun_Factory::log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		$billData = $bill->current()->getRawData();
		if (!empty($this->amountField)) {
			//TODO : support multiple header/footer lines
			$optional_amount = in_array($this->amountField['source'], ['header', 'trailer']) ?  $this->{$this->amountField['source'].'Rows'}[0][$this->amountField['field']] : $row[$this->amountField]['field'];
		}
		$billAmount = !is_null($optional_amount) ? $optional_amount : $billData['amount'];
		$paymentParams['amount'] = $billAmount;
		$paymentParams['dir'] = 'fc';
		$paymentParams['aid'] = $billData['aid'];
		if ($this->linkToInvoice) {
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
			$message = "Payment process was failed for payment: " . $e->getMessage();
			Billrun_Factory::log()->log($message, Zend_Log::ALERT);
			$this->informationArray['errors'][] = $message;
			return;
		}
		if (isset($ret['payment'])) {
			$customFields = $this->getCustomPaymentGatewayFields();
			foreach ($ret['payment'] as $index => $returned_payment) {
				$returned_payment->setExtraFields($customFields, array_keys($customFields));
			}
		}
        $this->informationArray['transactions']['confirmed']++;
        $this->informationArray['total_confirmed_amount']+=$paymentParams['amount'];
        $message = "Payment was created successfully for " . $this->identifierField['field'] . ' ' . intval($file_identifier_value);
		Billrun_Factory::log()->log($message, Zend_Log::INFO);
		$this->informationArray['info'][] = $message;
	}

	protected function findBillByUniqueIdentifier($id) {
//		if (in_array($this->identifierField , $this->dbNumericValuesFields) && Billrun_Util::IsIntegerValue($id)) {
//			$id = intval($id);
//		}
		return $this->bills->query(array('type' => 'inv', 'invoice_id' => intval($id)))->cursor();
	}
	
	public function getType () {
		return static::$type;
	}

}
