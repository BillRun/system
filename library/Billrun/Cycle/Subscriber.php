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
	
	// TODO: Process the dates!
	protected function constructPlans($plans) {
		$results = array();
		foreach ($plans as $plan) {
			// TODO: Handle the dates
		}
		return $results;
	}
	
	// TODO: Process the dates!
	protected function constructServices($services) {
		$results = array();
		foreach ($services as $service) {
			// TODO: Handle the dates
		}
		return $results;
	}
	
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
		$plans = $data['plans'];
		$this->records['plans'] = $this->constructPlans($plans);
		
		$services = $data['services'];
		$this->records['services'] = $this->constructServices($services);
	}

}
