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
		$response = Billrun_Bill_Payment::makePayment($options);
		
		return $response;
	}

}
