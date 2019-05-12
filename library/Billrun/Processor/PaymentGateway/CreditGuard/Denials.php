<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Processor for Credit Guard denials files.
 * @package  Billing
 * @since    5.9
 */
class Billrun_Processor_PaymentGateway_CreditGuard_Denials extends Billrun_Processor_PaymentGateway_PaymentGateway {

	protected static $type = 'CreditGuardDenials';
	protected $gatewayName = 'CreditGuard';
	protected $actionType = 'denials';
	protected $vendorFieldNames = array('cg_clearing_by', 'terminal_number', 'inquiry_desc', 'supplier_num', 'rikuz', 'shovar_num', 'status', 'addon_data');

	public function __construct($options) {
		parent::__construct($options);
	}

	protected function addFields($line) {
		if (empty($line['customer_data'])) {
			throw new Exception('X parameter is missing');
		}
		return array('transaction_id' => $line['customer_data']);
	}

	protected function updatePayments($row, $payment) {
		$row['aid'] = $payment->getAid();
		if ($row['amount'] != $payment->getDue()) {
			throw new Exception("Amount isn't matching for payment with txid: " . $row['transaction_id']);
		}
		if ($payment->isPaymentDenied()) {
			Billrun_Factory::log()->log("Payment " . $row['transaction_id'] . " is already denied", Zend_Log::NOTICE);
		}
		$newRow = $this->adjustRowDetails($row);
		$res = Billrun_Bill_Payment::createDenial($newRow, $payment);
		if ($res) {
			Billrun_Factory::log()->log("Denial was created successfully for payment: " . $newRow['transaction_id'], Zend_Log::NOTICE);
			Billrun_Factory::dispatcher()->trigger('afterDenial', array($newRow));
			$payment->deny();
			$paymentSaved = $payment->save();
			if (!$paymentSaved) {
				Billrun_Factory::log()->log("Denied flagging failed for rec " . $newRow['transaction_id'], Zend_Log::ALERT);
			}
		} else {
			Billrun_Factory::log()->log("Denial process was failed for payment: " . $newRow['transaction_id'], Zend_Log::NOTICE);
		}
	}

	protected function adjustRowDetails($row) {
		$newRow = $row;
		$newRow['vendor_fields']['vendor_name'] = $this->gatewayName;
		foreach ($this->vendorFieldNames as $fieldName) {
			unset($newRow[$fieldName]);
			$newRow['vendor_fields'][$fieldName] = $row[$fieldName];
		}
		unset($newRow['vendor_fields']['inquiry_desc']);
		return $newRow;
	}

}
