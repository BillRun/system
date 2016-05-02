<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class CycleAction extends Action_Base {
	
	
	protected $billing_cycle = null;
	protected $size = null;
	protected $stamp = null;
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
		if (is_null($options['stamp'])){
			$next_billrun_key = Billrun_Util::getBillrunKey(time());
			$current_billrun_key = Billrun_Util::getPreviousBillrunKey($next_billrun_key);
			$this->stamp = $current_billrun_key;
		}
		if (!$options['size']){
			$this->size = 100; // default value for size
		}
	
		$this->billing_cycle = Billrun_Factory::db()->billing_cycleCollection();
		$process_interval =  (int)Billrun_Factory::config()->getConfigValue('cycle.processes.interval');
		if (Billrun_Factory::config()->isProd()){
			if ($process_interval < 60){	  // 1 minute is minimum sleep time 
				$process_interval = 60;		
			}
		}
		do{
			$this->billing_cycle = Billrun_Factory::db()->billing_cycleCollection();
			$pid = pcntl_fork();	
			if ($pid == -1) {
				die('could not fork');
			} else if ($pid) {
					sleep($process_interval);
					pcntl_wait($status);
			} else {		
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
				break;
			}
		} 
		while (Billrun_Aggregator_Customer::isBillingCycleOver($this->billing_cycle, $this->stamp, (int)$this->size) === FALSE);
	} 
	
	
	

	
}
