<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		$extraParams = $this->getController()->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}
		$this->getController()->addOutput("Loading aggregator");
		$aggregator = Billrun_Aggregator::getInstance($options);
		$this->getController()->addOutput("Aggregator loaded");

		if (!$aggregator || !$aggregator->isValid()) {
			$this->getController()->addOutput("Aggregator cannot be loaded");
			return;
		}

		$this->getController()->addOutput("Loading data to Aggregate...");
		$aggregator->load();
		if (isset($options['fetchonly'])) {
			$this->getController()->addOutput("Only fetched aggregate accounts info. Exit...");
		}

		$this->getController()->addOutput("Starting to Aggregate. This action can take a while...");
		$aggregator->aggregate();
		$this->getController()->addOutput("Finish to Aggregate.");
	}

}
