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
	protected $unmatchedRows = array();

	public function __construct($options) {
		parent::__construct($options);
	}

	protected function addFields($line) {
		return array('transaction_id' => $line['customer_data']);
	}

	protected function updatePayments($row, $payment = null) {
		$addonData = !empty($row['addon_data']) ? intval($row['addon_data']) : '';
		if (is_null($payment) && empty($addonData)) {
			Billrun_Factory::log('None matching payment and missing Z parameter for row number ' . $row['row_number'] . ' in file ' . $this->filename, Zend_Log::ALERT);
			$this->unmatchedRows[] = $row;
			return;
		}
		$row['aid'] = !is_null($payment) ? $payment->getAid() : $addonData;
		if (!is_null($payment)) {
			if ($payment->isRejection() || $payment->isRejected()) {
				Billrun_Factory::log("Payment " . $payment->getId() . " is already rejected and can't been denied", Zend_Log::ALERT);
				return;
			}
			if ($payment->isPendingPayment()) {
				Billrun_Factory::log("Payment " . $payment->getId() . " is already pending and can't been denied", Zend_Log::ALERT);
				return;
			}
			if (abs($row['amount']) != $payment->getAmount()) {
				Billrun_Factory::log("Amount isn't equal to payment for payment with txid: " . $row['transaction_id'], Zend_Log::ALERT);
				return;
			}
			if ($payment->isAmountDeniable(abs($row['amount']))) {
				Billrun_Factory::log()->log("Payment " . $row['transaction_id'] .  " has already been denied", Zend_Log::ALERT);
				return;
			}
		} else { // in this case there's aid identifier
			Billrun_Factory::log('None matching payment for aid ' . $row['aid'] . ' with stamp ' . $row['stamp'], Zend_Log::ALERT);
			$this->unmatchedRows[] = $row;
			return;
		}
		$newRow = $this->adjustRowDetails($row);
		$denial = Billrun_Bill_Payment::createDenial($newRow, $payment);
		if (!empty($denial)) {
			if (!is_null($payment)) {
				Billrun_Factory::log()->log("Denial was created successfully for payment: " . $newRow['transaction_id'], Zend_Log::NOTICE);
				$payment->deny($denial);
			} else {
				Billrun_Factory::log()->log("Denial was created successfully without matching payment", Zend_Log::NOTICE);
			}
			Billrun_Factory::dispatcher()->trigger('afterDenial', array($newRow));
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
		foreach ($data['data'] as &$row) {
			$row = array_map(function($fieldName) {
				return trim($fieldName);
			}, $row);
		}
		
		return array_filter($data['data'], function ($denial) {
			return $denial['status'] != 1; 			
		});
	}
	
	protected function afterUpdateData() {
		if (empty($this->unmatchedRows)) {
			return;
		}
		$fileName = 'Denials/Unmacthed_Denials_' . time();
		$folderName = 'Denials';
		$filePath = Billrun_Util::getBillRunSharedFolderPath($fileName);
		$folderPath = Billrun_Util::getBillRunSharedFolderPath($folderName);
		if (!file_exists($folderPath)) {
			 mkdir($folderPath, 0777, true);
		}	
		Billrun_Factory::log('Writing unmatched denials rows to file ' . $filePath, Zend_Log::DEBUG);
		foreach ($this->unmatchedRows as $key => $row) {
			if ($key == 0) { // write header
				$header = implode(',', array_keys($row)) . PHP_EOL;
				file_put_contents($filePath, $header);
			}
			$unmatchedRow = implode(',', $row) . PHP_EOL;
			if (!file_put_contents($filePath, $unmatchedRow, FILE_APPEND)) {
				Billrun_Factory::log('Failed writing row to file ' . print_r($row, true), Zend_Log::ALERT);
			}
		}
	}

}
