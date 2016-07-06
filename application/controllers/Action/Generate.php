<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
			'page' => true,
			'size' => true,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Loading generator");
		$generator = Billrun_Generator::getInstance($options);

		if (!$generator) {
			$this->_controller->addOutput("Generator cannot be loaded");
			return;
		}
		$this->_controller->addOutput("Generator loaded");

		$this->_controller->addOutput("Loading data to Generate...");
		$generator->load();
		$this->_controller->addOutput("Starting to Generate. This action can take a while...");
		$generator->generate();
		$this->_controller->addOutput("Finished generating.");
		$this->_controller->addOutput("Exporting the file");
		$generator->move();
		$this->_controller->addOutput("Finished exporting");
	}

}
