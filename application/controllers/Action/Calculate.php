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

		// BRCD-3562: the calculator name (type) is optional. Without it the run
		// starts the chain at the first configured stage; with it, at that
		// calculator's stage. one_calc=true (only with --type) keeps the previous
		// single-calculator behavior.
		$possibleOptions = array('type' => true);

		if (($options = $this->getController()->getInstanceOptions($possibleOptions)) === FALSE) {
			return;
		}
		$params = $this->getController()->getParameters();
		if(isset($params['file_types'])){
			$options['file_types'] = $params['file_types'];
		}

		// one_calc=true (relevant only with --type) requests the previous
		// single-calculator behavior: the given calculator runs and the lines return
		// to the queue for the next stage.
		$oneCalc = isset($params['one_calc']) && filter_var($params['one_calc'], FILTER_VALIDATE_BOOLEAN);
		$requestedType = isset($options['type']) ? $options['type'] : null;
		if ($oneCalc && $requestedType === null) {
			$this->getController()->addOutput("one_calc is only relevant together with --type; ignoring it");
			$oneCalc = false;
		}

		// The calculator to run: the requested one, or the first configured stage.
		// In chain mode it pulls the queue ONCE - its stage and every later stage -
		// and runs every pulled line through its remaining calculators in memory
		// (Billrun_Calculator::calcChain); with one_calc it runs on its own, as before.
		$calculatorNames = Billrun_Calculator::getQueueCalculatorTypes($requestedType);
		$calcOptions = array_merge($options, Billrun_Helpers_QueueCalculators::getCalculatorOptions($calculatorNames[0], $options));
		$calcOptions['chain'] = !$oneCalc;
		$this->runCalculator($calcOptions, !$oneCalc);
	}

	/**
	 * Load and run one calculator over the queue - in chain mode when it accepted
	 * the 'chain' option (it is a configured queue stage), else on its own.
	 *
	 * @param array $options calculator options (must include 'type')
	 * @param bool $chainRequested whether chain mode was asked for
	 */
	protected function runCalculator($options, $chainRequested = false) {
		$this->getController()->addOutput("Loading Calculator");
		$calculator = Billrun_Calculator::getInstance($options);
		$this->getController()->addOutput("Calculator loaded");

		if (!$calculator) {
			$this->getController()->addOutput("Calculator cannot be loaded");
		} else if (!$calculator->isEnabled()) {
			$this->getController()->addOutput("Calculator type " . $calculator->getCalculatorQueueType() . " is disabled");
		} else if ($calculator->isChainMode()) {
			$this->getController()->addOutput("Starting to calculate. This action can take a while...");
			$calculator->calcChain();
			$this->getController()->addOutput("Writing calculated data.");
			$calculator->writeChain();
			$this->getController()->addOutput("Calculation finished.");
			$calculator->removeChainFromQueue();
		} else {
			if ($chainRequested) {//not a queue chain stage (e.g. rebalance, which works off its own rebalance_queue) - nothing to chain into
				$this->getController()->addOutput("Calculator type " . $options['type'] . " is not a queue chain stage; running it on its own");
			}
			$this->getController()->addOutput("Starting to calculate. This action can take a while...");
			$calculator->calc();
			$this->getController()->addOutput("Writing calculated data.");
			$calculator->write();
			$this->getController()->addOutput("Calculation finished.");
			$calculator->removeFromQueue();
		}
	}
}
