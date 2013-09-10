<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Aggregate action controller class
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class AggregateAction extends Action_Base {

	/**
	 * method to execute the aggregate process
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

		$this->_controller->addOutput("Loading aggregator");
		$aggregator = Billrun_Aggregator::getInstance($options);
		$this->_controller->addOutput("Aggregator loaded");

		if ($aggregator) {
			$this->_controller->addOutput("Loading data to Aggregate...");
			$aggregator->load();
			$this->_controller->addOutput("Starting to Aggregate. This action can take awhile...");
			$aggregator->aggregate();
			$this->_controller->addOutput("Finish to Aggregate.");
		} else {
			$this->_controller->addOutput("Aggregator cannot be loaded");
		}
	}

}