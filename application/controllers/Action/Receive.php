<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Receive action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class ReceiveAction extends Action_Base {

	/**
	 * method to execute the receive process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		if (!$this->isOn()) {
			$this->getController()->addOutput(ucfirst($this->getRequest()->action) . " is off");
			return;
		}

		$possibleOptions = array(
			'type' => false,
			'path' => true,
			'workspace' => true,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->getController()->addOutput("Loading receiver");
		$receiver = Billrun_Receiver::getInstance($options);
		$this->getController()->addOutput("Receiver loaded");

		if (!$receiver) {
			$this->getController()->addOutput("Receiver cannot be loaded");
			return;
		}

		$this->getController()->addOutput("Starting to receive. This action can take a while...");
		$files = $receiver->receive();
		$this->getController()->addOutput("Received " . count($files) . " files");
	}

}
