<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculate action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class CalculateAction extends Action_Base {

	/**
	 * method to execute the calculate process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		if (!$this->isOn()) {
			$this->getController()->addOutput(ucfirst($this->getRequest()->action) . " is off");
			return;
		}

		$possibleOptions = array('type' => false);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Loading Calculator");
		$calculator = Billrun_Calculator::getInstance($options);
		$this->_controller->addOutput("Calculator loaded");

		if (!$calculator) {
			$this->_controller->addOutput("Calculator cannot be loaded");
		} else if (!$calculator->isEnabled()) {
			$this->_controller->addOutput("Calculator type " . $calculator->getCalculatorQueueType() . " is disabled");
		} else {
			$this->_controller->addOutput("Starting to calculate. This action can take a while...");
			$calculator->calc();
			$this->_controller->addOutput("Writing calculated data.");
			$calculator->write();
			$this->_controller->addOutput("Calculation finished.");
			$calculator->removeFromQueue();
		}
	}

}
