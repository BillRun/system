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
	
	protected function validate($input) {
		// TODO: Complete
		return isset($input['plans']) && is_array($input['plans']) &&
			   (!isset($input['services']) || is_array($input['services'])); 
	}
	
	public function aggregate() {
		$aggregatedPlans = $this->aggregatePlans();
		$aggregatedServices = $this->aggregateServices();
		
		return $aggregatedPlans + $aggregatedServices;
	}

	protected function aggregatePlans() {
		$plans = $this->records['plans'];
		$aggregator = new Billrun_Cycle_Plan();
		
		return $this->generalAggregate($plans, $aggregator);
	}
	
	protected function aggregateServices() {
		$services = $this->records['services'];
		$aggregator = new Billrun_Cycle_Service();
		
		return $this->generalAggregate($services, $aggregator);
	}
	
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

	protected function constructServices($data) {
	}
	
	protected function constructPlans($data) {
		$plans = $this->getByField($data, "plans");
		$mongoPlans = $this->getByField($data, "mongo_plans");
		foreach ($plans as &$value) {
			// Plan name
			$index = $value['plan'];
			if(!in_array($index, $mongoPlans)) {
				Billrun_Factory::log("Ignoring inactive plan: " . print_r($value,1));
				continue;
			}
			
			$this->records['plans'][] = array_merge($value, $mongoPlans[$index]);
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
