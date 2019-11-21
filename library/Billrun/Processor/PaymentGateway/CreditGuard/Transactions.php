<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Processor for Credit Guard transaction files.
 * @package  Billing
 * @since    5.9
 */
class Billrun_Processor_PaymentGateway_CreditGuard_Transactions extends Billrun_Processor_PaymentGateway_PaymentGateway {

	protected static $type = 'CreditGuardTransactions';
	protected $gatewayName = 'CreditGuard';
	protected $actionType = 'transactions';

	public function __construct($options) {
		parent::__construct($options);
	}

	protected function addFields($line) {
		return array(
			'transaction_id' => $line['deal_id'],
			'ret_code' => $line['deal_status']
		);
	}

	protected function isValidTransaction($row){
		if ($row['ret_code'] == '000') { // 000 - Good Deal
			return true;
		} else{
			return false;
		}
	}

	protected function getPaymentResponse($row) {
		$stage = 'Rejected';
		if ($this->isValidTransaction($row)) {
			$stage = 'Completed';
		}

		return array('status' => $row['ret_code'], 'stage' => $stage);
	}
	
	protected function updatePayments($row, $payment = null) {
		if (is_null($payment)) {
			Billrun_Factory::log('Missing transaction_id in transactions file for ' . $row['stamp'], Zend_Log::ALERT);
			return;
		}
		$paymentResponse = $this->getPaymentResponse($row);
		$payment->setPending(false);
		if ($paymentResponse['stage'] == 'Rejected') {
			Billrun_Bill::updatePastRejectionsOnProcessingFiles($payment);
		}
		Billrun_Bill_Payment::updateAccordingToStatus($paymentResponse, $payment, $this->gatewayName);
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
	
	protected function filterData($data) {
		return $data['data'];
	}
}
