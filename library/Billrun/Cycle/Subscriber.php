<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregatble subscriber
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Subscriber extends Billrun_Cycle_Common {

	/**
	 * 
	 * @var Billrun_Cycle_Subscriber_Invoice
	 */
	protected $invoice;
	
	/**
	 * The next plan for the subscriber.
	 * @var string
	 */
	protected $nextPlan;
	
	/**
	 * Current plan.
	 * @var string 
	 */
	protected $plan;
	
	/**
	 * Validate the input
	 * @param array $input
	 * @return true if valid
	 */
	protected function validate($input) {
		// TODO: Complete
		return isset($input['plans']) && is_array($input['plans']) &&
			   isset($input['mongo_plans']) && is_array($input['mongo_plans']) &&
			   isset($input['cycle']) && is_a($input['cycle'], 'Billrun_DataTypes_CycleTime') &&
			   isset($input['invoice']) && is_a($input['invoice'], 'Billrun_Cycle_Subscriber_Invoice') &&
			   (!isset($input['services']) || is_array($input['services'])); 
	}
	
	/**
	 * Get the subscriber invoice data
	 * @return Billrun_Cycle_Subscriber_Invoice
	 */
	public function getInvoice() {
		return $this->invoice;
	}
	
	/**
	 * Get the subscriber plan 
	 * @return string
	 */
	public function getPlan() {
		return $this->plan;
	}
	
	/**
	 * Get the subscriber's next plan
	 * @return string or null
	 */
	public function getNextPlan() {
		return $this->nextPlan;
	}
	
	/**
	 * Get the current status of the subscriber.
	 * @return string
	 */
	public function getStatus() {
		if (!is_null($this->nextPlan)) {
			return "open";
		}
		return "closed";
	}
	
	/**
	 * Get the plan related data of the subscriber
	 * @return array
	 */
	public function getPlanData() {
		$data = array();
		if($this->plan) {
			$data['plan'] = $this->getPlan();
		}
		if($this->nextPlan) {
			$data['next_plan'] = $this->nextPlan;
		}
		$data['subscriber_status'] = $this->getStatus();
		return $data;
	}
	
	/**
	 * Main aggreagte function
	 * @return Aggregated data.
	 */
	public function aggregate($data = array()) {
		$aggregatedPlans = $this->aggregatePlans();
		$aggregatedServices = $this->aggregateServices();
		
		$results = $aggregatedPlans + $aggregatedServices;
		Billrun_Factory::log("Subscribers aggregated " . count($results) . ' lines');
		
		// Write the results to the invoice
		$this->invoice->addLines($results);
		return $results;
	}

	/**
	 * Aggregate the plan data
	 * @return type
	 */
	protected function aggregatePlans() {
		$plans = $this->records['plans'];
		$aggregator = new Billrun_Cycle_Plan();
		Billrun_Factory::log("Aggregating plans!");		
		return $this->generalAggregate($plans, $aggregator);
	}
	
	/**
	 * Aggreagte the services
	 * @return type
	 */
	protected function aggregateServices() {
		$services = $this->records['services'];
		$aggregator = new Billrun_Cycle_Service();
		Billrun_Factory::log("Aggregating services!");
		return $this->generalAggregate($services, $aggregator);
	}
	
	/**
	 * This function wraps general internal aggregation logic
	 * @param type $data
	 * @param type $aggregator
	 * @return type
	 */
	protected function generalAggregate($data, $aggregator) {
		if(!$data) {
			Billrun_Factory::log("generalAggregate received empty data!");
			return array();
		}
		
		$results = array();
			
		foreach ($data as $current) {
			// Add the stump line.
			$results += $aggregator->aggregate($current);
		}
		return $results;
	}
	
	protected function constructRecords($data) {
		if(isset($data['next_plan'])) {
			$this->nextPlan = $data['next_plan'];
		}
		
		$this->constructServices($data);
		$this->constructPlans($data);
		$this->constructInvoice($data);
	}

	protected function constructInvoice($data) {
		$this->invoice = &$data['invoice'];
		
		$this->invoice->setData('aid', $data['aid']);
		$this->invoice->setData('sid', $data['sid']);
		$this->invoice->setData('firstname', $data['firstname']);
		$this->invoice->setData('lastname', $data['lastname']);
		$this->invoice->setData('plan', $data['plan']);
	}
	
	/**
	 * Construct the services array
	 * @param type $data
	 */
	protected function constructServices($data) {
		$this->records['services'] = array();
		
		$services = Billrun_Util::getFieldVal($data["services"], array());
		$mongoServices = Billrun_Util::getFieldVal($data["mongo_services"], array());
		/**
		 * @var Billrun_DataTypes_CycleTime $cycle
		 */
		$cycle = $data['cycle'];
		$stumpLine = $data['line_stump'];
		
		foreach ($services as &$arrService) {
			// Plan name
			$index = $arrService['service'];
			if(!isset($mongoServices[$index])) {
				Billrun_Factory::log("Ignoring inactive plan: " . print_r($arrService,1));
				continue;
			}
			
			$mongoServiceData = $mongoServices[$index]->getRawData();
			unset($mongoServiceData['_id']);
			$serviceData = array_merge($mongoServiceData, $arrService);
			$serviceData['cycle'] = $cycle;
			$serviceData['line_stump'] = $stumpLine;
			$this->records['services'][] = $serviceData;
		}
	}
	
	/**
	 * Construct the plan array
	 * @param type $data
	 */
	protected function constructPlans($data) {
		$this->records['plans'] = array();
		$plans = Billrun_Util::getFieldVal($data['plans'], array());
		if(empty($plans)) {
			$this->plan = "";
			Billrun_Factory::log("Received no plans!");
			return;
		}
		$this->plan = $plans[count($plans) - 1]['plan'];
		$mongoPlans = Billrun_Util::getFieldVal($data["mongo_plans"], array());
		
		/**
		 * @var Billrun_DataTypes_CycleTime $cycle
		 */
		$cycle = $data['cycle'];
		$stumpLine = $data['line_stump'];
		
		foreach ($plans as &$value) {
			// Plan name
			$index = $value['plan'];
			if(!isset($mongoPlans[$index])) {
				Billrun_Factory::log("Ignoring inactive plan: " . print_r($value,1));
				continue;
			}
			
			$rawMongo = $mongoPlans[$index]->getRawData();
			unset($rawMongo['_id']);
			$planData = array_merge($value, $rawMongo);
			$planData['cycle'] = $cycle;
			$planData['line_stump'] = $stumpLine;
			$this->records['plans'][] = $planData;
		}
	}
}
