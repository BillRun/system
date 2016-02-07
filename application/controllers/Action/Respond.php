<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Respond action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class RespondAction extends Action_Base {

	/**
	 * method to execute the respond process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		$possibleOptions = array(
			'type' => false,
			'export-path' => true
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Loading Responder");
		$responder = Billrun_Responder::getInstance($options);
		$this->_controller->addOutput("Responder loaded");

		if ($responder) {
			$paths = $responder->respond($options);
			$this->_controller->addOutput("Responder responded on " . count($paths) . " files.");
		} else {
			$this->_controller->addOutput("Responder cannot be loaded");
		}
	}

}
