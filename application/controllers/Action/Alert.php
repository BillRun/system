<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Alert action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class AlertAction extends Action_Base {

	/**
	 * method to execute the alert process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		$possibleOptions = array(
			'type' => true,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Loading handler");
		$handler = Billrun_Handler::getInstance($options);

		if (!$handler) {
			$this->_controller->addOutput("Aggregator cannot be loaded");
			return;
		}

		$this->_controller->addOutput("Handler loaded");
		$handler->execute();
	}

}
