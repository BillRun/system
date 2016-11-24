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
			'stamp' => false,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		if (is_null($options['stamp']) || !Billrun_Util::isBillrunKey($options['stamp'])) {
			return $this->setError("Illegal stamp ", $options['stamp']);
		}
		Billrun_Bill_Payment::checkPendingStatus();
		$this->getController()->addOutput("Starting to charge unpaid payments");
		Billrun_Bill_Payment::makePayment($options['stamp']);
		$this->getController()->addOutput("Charging Done");
	}

}
