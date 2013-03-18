<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Generate action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class GenerateAction extends Action_Base {

	/**
	 * method to execute the generate process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {

		$possibleOptions = array(
			'type' => false,
			'stamp' => false,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Loading generator");
		$generator = Billrun_Generator::getInstance($options);
		$this->_controller->addOutput("Generator loaded");

		if ($generator) {
			$this->_controller->addOutput("Loading data to Generate...");
			$generator->load();
			$this->_controller->addOutput("Starting to Generate. This action can take awhile...");
			$generator->generate();
			$this->_controller->addOutput("Finish to Generate. This action can take awhile...");
		} else {
			$this->_controller->addOutput("Aggregator cannot be loaded");
		}
	}

}