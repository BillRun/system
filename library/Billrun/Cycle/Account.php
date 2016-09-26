<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregatble account
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Account extends Billrun_Cycle_Common {
	
	/**
	 * Array of account attributes
	 * @var array 
	 */
	protected $attributes = array();
	
	/**
	 * 
	 * @param type $data
	 */
	public function __construct($data) {
		parent::__construct($data);
	}
	
	/**
	 * Validate the input
	 * @param type $input
	 * @return type
	 */
	protected function validate($input) {
		// TODO: Complete
		return isset($input['subscribers']) && is_array($input['subscribers']);
	}

	/**
	 * Construct the subscriber records
	 * @param type $data
	 */
	protected function constructRecords($data) {
//		$this->populateBillrunWithAccountData($data);
		$subscribers = $data['subscribers'];
		$cycle = $data['cycle'];
		$plans = &$data['plans'];
		
		$sorted = $this->sortSubscribers($subscribers);
		
		// Filter subscribers.
		$filtered = $this->filterSubscribers($sorted, $cycle);
		
		foreach ($sorted as $sid => $subscriberList) {
			Billrun_Factory::log("Constructing records for sid " . $sid);
			
			$filteredSid = array();
			if(isset($filtered[$sid])) {
				$filteredSid = $filtered[$sid];
			}
			
			$this->records[] = $this->constructForSid($subscriberList, $filteredSid, $plans);
		}
	}

	/**
	 * Construct the subscriber records for an sid
	 * @param array $sorted - Sorted subscribers by sid
	 * @param array $filtered - Filtered plans ans services
	 * @param array $plans - Raw plan data from the mongo
	 * @param Billrun_DataTypes_CycleTime $cycle - Current cycle time.
	 */
	protected function constructForSid($sorted, $filtered, &$plans, $cycle) {
		$aggregateable = array();
		foreach ($sorted as $sub) {
			$constructed = $sub;
			$filterKey = $sub['to'];
			if(isset($filtered[$filterKey])) {
				$constructed = array_merge($constructed, $filtered[$filterKey]);
			}
			$constructed['mongo_plans'] = &$plans;
			$constructed['cycle'] = &$cycle;
			$constructed['line_stump'] = $this->getLineStump($sub, $cycle);
			
			$aggregateable[] = new Billrun_Cycle_Subscriber($constructed);
		}
	}
	
	protected function getLineStump(array $subscriber, Billrun_DataTypes_CycleTime $cycle) {
		$flatEntry = array(
			'aid' => $subscriber['aid'],
			'sid' => $subscriber['sid'],
			'source' => 'billrun',
			'billrun' => $cycle->key(),
			'type' => 'flat',
			'usaget' => 'flat',
			'cycle' => $cycle,
			'urt' => new MongoDate($cycle->start()),
		);
		
		return $flatEntry;
	}
	
	protected function sortSubscribers($subscribers, $endTime) {
		$sorted = array();
		
		foreach ($subscribers as $subscriber) {
			if(!isset($subscriber['sid'])) {
				Billrun_Factory::log("Invalid subscriber record in filter subscribers!", Zend_Log::NOTICE);
				continue;
			}
			
			$sid = $subscriber['sid'];
			$sorted[$sid][] = $this->handleSubscriberDates($subscriber, $endTime);
		}
		
		return $sorted;
	}
	
	protected function handleSubscriberDates($subscriber, $endTime) {
		if(isset($subscriber['to']->sec)) {
			$subscriber['to'] = $subscriber['to']->sec;
		}
		if(isset($subscriber['from']->sec)) {
			$subscriber['from'] = $subscriber['from']->sec;
		}

		if($subscriber['to'] > $endTime) {
			$subscriber['to'] = $endTime;
		}
		
		return $subscriber;
	}
	
	/**
	 * 
	 * @param type $subscribers
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @return array
	 */
	protected function filterSubscribers($subscribers, $cycle) {
		$filtered = array();
		
		foreach ($subscribers as $sid => $current) {
			$filtered[$sid] = $this->buildSubAggregator($current, $cycle->end());
		}
		
		return $filtered;
	}
	
	/**
	 * Create a subscriber aggregator from an array of subscriber records.
	 * @param array $current - Array of subscriber records.
	 * @param int $endTime
	 * @todo: Rewrite this function better
	 */
	protected function buildSubAggregator(array $current, $endTime) {		
		$aggregatorData = array();
		
		$plan = null;
		$services = array();
		$servicesData = array();
		foreach ($current as $subscriber) {
			// Get the plan
			$currPlan = $subscriber['plan'];
			
			// If it is the same plan
			if(($currPlan && !$plan) || ($plan['name'] !== $currPlan)) {
				// Update the last plan
				if($plan) {
					$planEnd = $subscriber['from'];
					$aggregatorData[$planEnd]['plan'] = array("plan" => $plan['name'], "start" => $plan['start'], "end" => $planEnd);
				}

				// Set the plan
				$plan['name'] = $currPlan;
				$plan['start'] = $subscriber['plan_activation'];
			}
			
			// Get the services.
			$currServices = array();
			if(isset($subscriber['services'])) {
				$currServices = $subscriber['services'];
			}
			
			// Check the differences in the services.
			$added = array_diff($services, $currServices);
			$removed = array_diff($currServices, $services);
			
			$services = $currServices;
			
			// Add the services to the services data
			foreach ($added as $addedService) {
				$serviceRow = array("service" => $addedService, "start" => $subscriber['from']);
				$servicesData[$addedService] = $serviceRow;
			}
			
			// Handle the removed services. 
			foreach ($removed as $removedService) {
				$updateService = $servicesData[$removedService];
				unset($servicesData[$removedService]);
				$serviceEnd = $subscriber['from'];
				$updateService['end'] = $serviceEnd;
				
				// Push back
				$aggregatorData[$serviceEnd]['services'][] = $updateService;
			}
		}
		
		// Handle all the remaining values.
		if($plan) {
			$planData = array("plan" => $plan['name'], "start" => $plan['start'], "end" => $endTime);
			$aggregatorData[$endTime]['plan'] = $planData;
			$aggregatorData['next_plan'] = $plan;
		}
		
		foreach ($servicesData as $lastService) {
			$lastService['end'] = $endTime;
			$aggregatorData[$endTime]['services'][] = $lastService;
		}
		
		return $aggregatorData;
	}
	
	/**
	 * Get an empty billrun account entry structure.
	 * @param int $aid the account id of the billrun document
	 * @param string $billrun_key the billrun key of the billrun document
	 * @return array an empty billrun document
	 */
//	protected function populateBillrunWithAccountData($account) {
//		$attr = array();
//		foreach (Billrun_Factory::config()->getConfigValue('billrun.passthrough_data', array()) as $key => $remoteKey) {
//			if (isset($account['attributes'][$remoteKey])) {
//				$attr[$key] = $account['attributes'][$remoteKey];
//			}
//		}
//		if (isset($account['attributes']['first_name']) && isset($account['attributes']['last_name'])) {
//			$attr['full_name'] = $account['attributes']['first_name'] . ' ' . $account['attributes']['last_name'];
//		}
//
//		$this->attributes = $attr;
//	}
}
