<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * .
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.8
 */
class debtCollectionPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'debtCollection';
	protected $immediateEnter = false;
	protected $immediateExit = true;
	protected $cronFrequency = 'daily';
	protected $stepsPeriodicity = 'daily';
	
	public function afterChargeSuccess($bill) {
		$id = '';
		if (isset($bill['invoice_id'])) {
			$id = $bill['invoice_id'];
		} else if (isset($bill['txid'])) {
			$id = $bill['txid'];
		}
		Billrun_Factory::log("Invoice " . $id . " charged successfully with due " . $bill['due'], Zend_Log::INFO);
		if ($this->immediateExit) {
			CollectAction::collect(array($bill['aid']));
		}
	}
	
	public function afterOfflineCharge($bill) {
		$id = '';
		if (isset($bill['invoice_id'])) {
			$id = $bill['invoice_id'];
		} else if (isset($bill['txid'])) {
			$id = $bill['txid'];
		}
		Billrun_Factory::log("Invoice " . $id . " charged successfully with due " . $bill['due'], Zend_Log::INFO);
		if ($this->immediateExit) {
			CollectAction::collect(array($bill['aid']));
		}
	}

	public function afterRefundSuccess($bill) {
		$id = '';
		if (isset($bill['invoice_id'])) {
			$id = $bill['invoice_id'];
		} else if (isset($bill['txid'])) {
			$id = $bill['txid'];
		}
		Billrun_Factory::log("Invoice " . $id . " refunded successfully with due " . $bill['due'], Zend_Log::INFO);
		if ($this->immediateEnter) {
			CollectAction::collect(array($bill['aid']));
		}
	}
	
	public function afterRejection($bill) {
		$id = '';
		if (isset($bill['invoice_id'])) {
			$id = $bill['invoice_id'];
		} else if (isset($bill['txid'])) {
			$id = $bill['txid'];
		}
		Billrun_Factory::log("Invoice" . $bill['invoice_id'] . "charged successfully with due " . $bill['due'], Zend_Log::INFO);
		if ($this->immediateEnter) {
			CollectAction::collect(array($bill['aid']));
		}
	}
	
	public function cronHour() {
		if ($this->cronFrequency == 'hourly') {
			CollectAction::collect();
		}
		if ($this->stepsPeriodicity == 'daily') {
			Run_collect_stepAction::runCollectStep();
		}
	}

	public function cronDay() {
		if ($this->cronFrequency == 'daily') {
			CollectAction::collect();
		}
		if ($this->stepsPeriodicity == 'daily') {
			Run_collect_stepAction::runCollectStep();
		}
	}
	
}
