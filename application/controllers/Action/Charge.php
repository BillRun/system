<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Charge action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 */
class ChargeAction extends Action_Base {

	use Billrun_Traits_Api_OperationsLock;

	protected $aids = array();

	/**
	 * method to execute the pay process for payment gateways.
	 * it's called automatically by the cli main controller
	 */
	public function execute() {
		$possibleOptions = array(
			'stamp' => true,
			'page' => true,
			'size' => true,
		);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$extraParams = $this->getController()->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}
		$this->getController()->addOutput("Checking pending payments...");
		Billrun_Bill_Payment::checkPendingStatus($options);
		if (!isset($options['pending'])) {
			$this->getController()->addOutput("Starting to charge unpaid payments...");
			$this->charge($options);
		}
		$this->getController()->addOutput("Charging Done");
	}

	public function charge($options) {
		$this->aids = isset($options['aids']) ? Billrun_Util::verify_array($options['aids'], 'int') : array();
		if (!$this->lock()) {
			Billrun_Factory::log("Charging is already running", Zend_Log::NOTICE);
			return false;
		}
		$response = Billrun_Bill_Payment::makePayment($options);
		if (!$this->release()) {
			Billrun_Factory::log("Problem in releasing operation", Zend_Log::ALERT);
			return false;
		}

		return $response;
	}

	protected function getConflictingQuery() {
		if (!empty($this->aids)) {
			return array(
				'$or' => array(
					array('filtration' => 'all'),
					array('filtration' => array('$in' => $this->aids)),
				),
			);
		}

		return array();
	}

	protected function getInsertData() {
		return array(
			'action' => 'charge_account',
			'filtration' => (empty($this->aids) ? 'all' : $this->aids),
		);
	}

	protected function getReleaseQuery() {
		return array(
			'action' => 'charge_account',
			'filtration' => (empty($this->aids) ? 'all' : $this->aids),
			'end_time' => array('$exists' => false)
		);
	}

}
