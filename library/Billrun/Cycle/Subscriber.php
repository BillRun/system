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
	public function aggregate() {
		$aggregatedPlans = $this->aggregatePlans();
		$aggregatedServices = $this->aggregateServices();
		
		return $aggregatedPlans + $aggregatedServices;
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
		$results = array();
		foreach ($data as $current) {
			$results[] = $aggregator->aggregate($current);
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
		$services = $this->getByField($data, "services");
		$mongoPlans = $this->getByField($data, "mongo_plans");
		/**
		 * @var Billrun_DataTypes_CycleTime $cycle
		 */
		$cycle = $data['cycle'];
		$stumpLine = $data['stump_line'];
		foreach ($services as &$arrService) {
			foreach ($arrService as &$value) {
				// Plan name
				$index = $arrService['service'];
				if(!in_array($index, $mongoPlans)) {
					Billrun_Factory::log("Ignoring inactive plan: " . print_r($arrService,1));
					continue;
				}

				$serviceData = array_merge($value, $mongoPlans[$index]);
				$serviceData['cycle'] = $cycle;
				$serviceData['stump_line'] = $stumpLine;
				$this->records['services'][] = $serviceData;
			}
		}
	}
	
	/**
	 * Construct the plan array
	 * @param type $data
	 */
	protected function constructPlans($data) {
		$plans = $this->getByField($data, "plans");
		$plans += $this->nextPlan;
		$mongoPlans = $this->getByField($data, "mongo_plans");
		
		/**
		 * @var Billrun_DataTypes_CycleTime $cycle
		 */
		$cycle = $data['cycle'];
		
		foreach ($plans as &$value) {
			// Plan name
			$index = $value['plan'];
			if(!in_array($index, $mongoPlans)) {
				Billrun_Factory::log("Ignoring inactive plan: " . print_r($value,1));
				continue;
			}
			
			$planData = array_merge($value, $mongoPlans[$index]);
			$planData['cycle'] = $cycle;
			$this->records['plans'][] = $planData;
		}
	}
	
	protected function getByField($data, $field) {
		$toSet = array();
		if(isset($data[$field])) {
			$toSet = $data[$field];
		}
		return $toSet;
	}
	
}
