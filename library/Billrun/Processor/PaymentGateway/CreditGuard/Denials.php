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
		return array('transaction_id' => $line['customer_data']);
	}

	protected function updatePayments($row, $payment = null) {
		if (is_null($payment) && empty($row['addon_data'])) {
			Billrun_Factory::log('None matching payment and missing Z parameter for ' . $row['stamp'], Zend_Log::ALERT);
			return;
		}
		$row['aid'] = !is_null($payment) ? $payment->getAid() : $row['addon_data'];
		if (!is_null($payment)) {
			if (abs($row['amount']) > $payment->getAmount()) {
				Billrun_Factory::log("Amount sent is bigger than the amount of the payment with txid: " . $row['transaction_id'], Zend_Log::ALERT);
				return;
			}
			if ($payment->isPaymentDenied(abs($row['amount']))) {
				Billrun_Factory::log()->log("Payment " . $row['transaction_id'] . " is already denied", Zend_Log::NOTICE);
				return;
			}
		}
		$newRow = $this->adjustRowDetails($row);
		$denial = Billrun_Bill_Payment::createDenial($newRow, $payment);
		if (!empty($denial)) {
			Billrun_Factory::dispatcher()->trigger('afterDenial', array($newRow));
			if (!is_null($payment)) {
				Billrun_Factory::log()->log("Denial was created successfully for payment: " . $newRow['transaction_id'], Zend_Log::NOTICE);
				$payment->deny($denial);
				$paymentSaved = $payment->save();
				if (!$paymentSaved) {
					Billrun_Factory::log()->log("Denied flagging failed for rec " . $newRow['transaction_id'], Zend_Log::ALERT);
				}
			} else {
				Billrun_Factory::log()->log("Denial was created successfully without matching payment", Zend_Log::NOTICE);
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
	
	protected function filterData($data) {
		return array_filter($data['data'], function ($denial) {
			return $denial['status'] != 1; 			
		});
	}

}
