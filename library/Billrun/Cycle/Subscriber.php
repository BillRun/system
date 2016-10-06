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

	protected $nextPlan;
	
	/**
	 * Validate the input
	 * @param array $input
	 * @return true if valid
	 */
	protected function validate($input) {
		// TODO: Complete
		return isset($input['plans']) && is_array($input['plans']) &&
			   isset($input['cycle']) && is_a($input['cycle'], 'Billrun_DataTypes_CycleTime') &&
			   (!isset($input['services']) || is_array($input['services'])); 
	}
	
	public function getStatus() {
		if (!is_null($this->nextPlan)) {
			return "open";
		}
		return "closed";
	}
	
	/**
	 * Main aggreagte function
	 * @return Aggregated data.
	 */
	public function aggregate($data = array()) {
		$aggregatedPlans = $this->aggregatePlans();
		$aggregatedServices = $this->aggregateServices();
		
		$results = $aggregatedPlans + $aggregatedServices;
		Billrun_Factory::log("Subscriber aggregated: " . count($results));
		return $results;
	}

	/**
	 * Aggregate the plan data
	 * @return type
	 */
	protected function aggregatePlans() {
		$plans = $this->records['plans'];
		$aggregator = new Billrun_Cycle_Plan();
		
		return $this->generalAggregate($plans, $aggregator);
	}
	
	/**
	 * Aggreagte the services
	 * @return type
	 */
	protected function aggregateServices() {
		$services = $this->records['services'];
		$aggregator = new Billrun_Cycle_Service();
		
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
	}

	/**
	 * Construct the services array
	 * @param type $data
	 */
	protected function constructServices($data) {
		$this->records['services'] = array();
		
		$services = Billrun_Util::getFieldVal($data["services"], array());
		$mongoPlans = Billrun_Util::getFieldVal($data["mongo_plans"], array());
		/**
		 * @var Billrun_DataTypes_CycleTime $cycle
		 */
		$cycle = $data['cycle'];
		$stumpLine = $data['line_stump'];
		
		foreach ($services as &$arrService) {
			// Plan name
			$index = $arrService['key'];
			if(!in_array($index, $mongoPlans)) {
				Billrun_Factory::log("Ignoring inactive plan: " . print_r($arrService,1));
				continue;
			}

			$serviceData = array_merge($arrService, $mongoPlans[$index]);
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
			Billrun_Factory::log("Received no plans!");
			return;
		}
		
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
			
			$planData = array_merge($value, $mongoPlans[$index]->getRawData());
			$planData['cycle'] = $cycle;
			$planData['line_stump'] = $stumpLine;
			$this->records['plans'][] = $planData;
		}
	}
}
