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

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->getController()->addOutput("Loading handler");
		$handler = Billrun_Handler::getInstance($options);

		if (!$handler) {
			$this->getController()->addOutput("Aggregator cannot be loaded");
			return;
		}

		$this->getController()->addOutput("Handler loaded");
		$handler->execute();
	}

}
