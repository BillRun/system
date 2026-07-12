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

		// BRCD-3562: the calculator name (type) is optional. When omitted, every
		// queue calculator runs so a line flows through the whole chain; when
		// given, only that calculator runs.
		$possibleOptions = array('type' => true);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		$params = $this->getController()->getParameters();
		if(isset($params['file_types'])){
			$options['file_types'] = $params['file_types'];
		}

		$requestedType = isset($options['type']) ? $options['type'] : null;
		$calculatorNames = Billrun_Calculator::getQueueCalculatorTypes($requestedType);
		if (empty($calculatorNames)) {
			$this->getController()->addOutput("No calculators to run");
			return;
		}

		// Map each queue calculator name to its concrete instantiation options
		// (e.g. 'rate' -> rate_Usage, 'pricing' -> customerPricing) so a no-type
		// run drives the whole chain.
		foreach ($calculatorNames as $calculatorName) {
			$calcOptions = array_merge($options, Billrun_Helpers_QueueCalculators::getCalculatorOptions($calculatorName, $options));
			$this->runCalculator($calcOptions);
		}
	}

	/**
	 * Load and run a single calculator over the queue.
	 *
	 * @param array $options calculator options (must include 'type')
	 */
	protected function runCalculator($options) {
		$this->getController()->addOutput("Loading Calculator");
		$calculator = Billrun_Calculator::getInstance($options);
		$this->getController()->addOutput("Calculator loaded");

		if (!$calculator) {
			$this->getController()->addOutput("Calculator cannot be loaded");
		} else if (!$calculator->isEnabled()) {
			$this->getController()->addOutput("Calculator type " . $calculator->getCalculatorQueueType() . " is disabled");
		} else {
			$this->getController()->addOutput("Starting to calculate. This action can take a while...");
			$calculator->calc();
			$this->getController()->addOutput("Writing calculated data.");
			$calculator->write();
			$this->getController()->addOutput("Calculation finished.");
			$calculator->removeFromQueue();
		}
	}

}
