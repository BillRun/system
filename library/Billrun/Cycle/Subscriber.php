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
		
		$plans = array();
		if(isset($data['plans'])) {
			$plans = $data['plans'];
		}
		$this->records['plans'] = $plans;
		
		$services = array();
		if(isset($data['services'])) {
			$services = $data['services'];
		}
		$this->records['services'] = $services;
	}

	protected function constructServices($data) {
		$this->constructField($data, "services");
	}
	
	protected function constructPlans($data) {
		$this->constructField($data, "plans");
	}
	
	protected function constructField($data, $field) {
		$toSet = array();
		if(isset($data[$field])) {
			$toSet = $data[$field];
		}
		$this->records[$field] = $toSet;

	}
	
}
