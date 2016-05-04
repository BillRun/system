<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
			'stamp' => true,
			'page' => true,
			'size' => true,
			'fetchonly' => true,
		);

		if (($options = $this->_controller->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}

		$this->_controller->addOutput("Loading aggregator");
		try {
			$aggregator = Billrun_Aggregator::getInstance($options);
		} catch (Exception $e) {
			Billrun_Factory::log()->log($e->getMessage(), Zend_Log::NOTICE);
			$aggregator = FALSE;
		}
		$this->_controller->addOutput("Aggregator loaded");

		if ($aggregator) {
			$this->_controller->addOutput("Loading data to Aggregate...");
			$aggregator->load();
			if (!isset($options['fetchonly'])) {
				$this->_controller->addOutput("Starting to Aggregate. This action can take a while...");
				$aggregator->aggregate();
				$this->_controller->addOutput("Finish to Aggregate.");
			} else {
				$this->_controller->addOutput("Only fetched aggregate accounts info. Exit...");
			}
		} else {
			$this->_controller->addOutput("Aggregator cannot be loaded");
		}
	}

}
