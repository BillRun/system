<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Cycle action class
 *
 * @package  Action
 * @since    5.0
 * 
 */
class CycleAction extends Action_Base {
	use Billrun_Traits_OnChargeDay;
	
	protected $billingCycleCol = null;

	/**
	 * Build the options for the cycle
	 * @return boolean
	 * @todo This is a generic function, might be better to create a basic action class
	 * that uses an cycle and have all these functions in it.
	 */
	protected function buildOptions() {
		$possibleOptions = array(
			'type' => false,
			'stamp' => true,
			'page' => true,
			'size' => true,
			'fetchonly' => true,
		);

		$options = $this->getController()->getInstanceOptions($possibleOptions);
		if ($options === false) {
			return false;
		}

		if (empty($options['stamp'])) {
			$nextBillrunKey = Billrun_Billingcycle::getBillrunKeyByTimestamp(time());
			$currentBillrunKey = Billrun_Billrun::getPreviousBillrunKey($nextBillrunKey);
			$options['stamp'] = $currentBillrunKey;
		}

		if (!isset($options['size']) || !$options['size']) {
			// default value for size
			$options['size'] = Billrun_Factory::config()->getConfigValue('customer.aggregator.size');
		}
		
		$options['action'] = 'cycle';
		return $options;
	}
	
	/**
	 * Get the process interval
	 * @return int
	 */
	protected function getProcessInterval() {
		$processInterval = (int) Billrun_Factory::config()->getConfigValue('cycle.processes.interval');
		if (Billrun_Factory::config()->isProd()) {
			if ($processInterval < 60) {   // 1 minute is minimum sleep time 
				$processInterval = 60;
			}
		}
		return $processInterval;
	}
	
	/**
	 * method to execute the aggregate process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {		
		$options = $this->buildOptions();
		$extraParams = $this->getController()->getParameters();
		if (!empty($extraParams)) {
			$options = array_merge($extraParams, $options);
		}

		$this->billingCycleCol = Billrun_Factory::db()->billing_cycleCollection();
		$processInterval = $this->getProcessInterval();

		$stamp = $options['stamp'];
		$size = (int)$options['size'];
        $allowPrematureRun = (int)Billrun_Factory::config()->getConfigValue('cycle.allow_premature_run');
		if (Billrun_Factory::config()->isMultiDayCycle()) {
			$this->_controller->addOutput("Running on multi cycle day mode");
			$this->_controller->addOutput("Filtering relevant invoicing days according to the current time.");
			$invoicing_days = $this->getInvoicingDays($options);
			if (empty($invoicing_days)) {
				$this->_controller->addOutput("There were no relevant invoicing days. No cycle was run");
				return;
			}
			$options['invoicing_days'] = $invoicing_days;
		} elseif (!$allowPrematureRun && time() < Billrun_Billingcycle::getEndTime($stamp)) {
        // Check if we should cycle.
			$this->getController()->addOutput("Can't run billing cycle before the cycle end time.");
            return;
		} 

		$zeroPages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit');
		if (Billrun_Factory::config()->isMultiDayCycle()) {
			foreach ($invoicing_days as $index => $invoicing_day) {
				$this->runCycle($stamp, $size, $zeroPages, $processInterval, $options, $invoicing_day);
				}
		} else {
			$this->runCycle($stamp, $size, $zeroPages, $processInterval, $options);
				}
			}
	
	protected function executeParentProcess($processInterval) {
		$this->getController()->addOutput("Going to sleep for " . $processInterval . " seconds");
		sleep($processInterval);
		pcntl_signal(SIGCHLD, SIG_IGN);
	}
	
	/**
	 * Execute the child process logic
	 * @param type $options
	 * @return type
	 */
	protected function executeChildProcess($options) {
		Billrun_Factory::clearInstance('db',array(),true);
		Mongodloid_Connection::clearInstances();
		$aggregator = $this->getAggregator($options);
		if($aggregator == false) {
			return;
		}
		
		$this->getController()->addOutput("Loading data to Aggregate...");
		$aggregator->load();
		if (isset($options['fetchonly'])) {
			$this->getController()->addOutput("Only fetched aggregate accounts info. Exit...");
			return;
		}

		$this->getController()->addOutput("Starting to Aggregate. This action can take a while...");
		$aggregator->aggregate();
		$this->getController()->addOutput("Finish to Aggregate.");
	}
	
	/**
	 * Get an aggregator with input options
	 * @param array $options - Array of options to initialize the aggregator with.
	 * @return Aggregator
	 * @todo getAggregator might be common in actions, maybe create a basic aggregate action class?
	 */
	protected function getAggregator($options) {
		$this->getController()->addOutput("Loading aggregator");
		if(!Billrun_Factory::config()->getConfigValue('customer.aggregator.should_fork',TRUE)) {
			$options = array_merge($options,['rand'=>  microtime(true)]);
		}
		$aggregator = Billrun_Aggregator::getInstance($options);
		
		if(!$aggregator || !$aggregator->isValid()) {
			$this->getController()->addOutput("Aggregator cannot be loaded");
			return false;
		}
		
		$this->getController()->addOutput("Aggregator loaded");
		return $aggregator;
	}
	
	public function runCycle($stamp, $size, $zeroPages, $processInterval, $options, $invoicing_day = null) {
		while(!Billrun_Billingcycle::isBillingCycleOver($this->billingCycleCol, $stamp, $size, $zeroPages, $invoicing_day)) {
			if(Billrun_Factory::config()->getConfigValue('customer.aggregator.should_fork',TRUE)) {
				$pid = Billrun_Util::fork();
				if ($pid == -1) {
					die('could not fork');
}

				$this->getController()->addOutput("Running on PID " . $pid);

				// Parent process.
				if ($pid) {
					$this->executeParentProcess($processInterval);
					continue;
				}
			}
			// Child process / Actual aggregate  when not forking
			$this->executeChildProcess($options);
			
			if(Billrun_Factory::config()->getConfigValue('customer.aggregator.should_fork',TRUE)) {
				break;
			}
		}
		//Wait for all the childrens to finish  before  exiting to prevent issues with shared resources.
		$status = 0;
		pcntl_wait($status);
	}
	
	public function getInvoicingDays($options) {
		if (!empty($options['invoicing_days'])) {
			$options['invoicing_days'] = !is_array($options['invoicing_days']) ? [$options['invoicing_days']] : $options['invoicing_days'];
			if (!$allowPrematureRun) {
				$stamp = $options['stamp'];
				return array_filter($options['invoicing_days'], function($invoicing_day) use ($stamp) {
					return time() < Billrun_Billingcycle::getEndTime($stamp, $invoicing_day);
				});
			} else {
				return $options['invoicing_days'];
			}
		}else {
			return array_map('strval', Billrun_Factory::config()->getConfigValue('cycle.allow_premature_run', false) ? range(1, 28) : range(1, date("d", strtotime("yesterday"))));
		}
	}
}