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
	 * 
	 * @var Billrun_Cycle_Account_Invoice
	 */
	protected $invoice;
	
	/**
	 * Aggregate the data, store the results in the billrun container.
	 * @return array - Array of aggregated results
	 */
	public function aggregate($data = array()) {
		Billrun_Factory::log("Subscriber records to aggregate: " . count($this->records));
		
		$results = parent::aggregate();
		
		Billrun_Factory::log("Account aggregated " . count($results) . ' subscriber records.');
		
		return $results;
	}
	
	/**
	 * Write the invoice to the Billrun collection
	 * @param int $min_id minimum invoice id to start from
	 */
	public function writeInvoice($min_id) {
		foreach ($this->records as $subscriber) {
			$subInvoice = $subscriber->getInvoice();
			$this->invoice->addSubscriber($subInvoice);
		}
		$this->invoice->updateTotals();
		$this->invoice->applyDiscounts();
		$this->invoice->close($min_id);
	}
	
	/**
	 * Validate the input
	 * @param type $input
	 * @return type
	 */
	protected function validate($input) {
		// TODO: Complete
		return isset($input['subscribers']) && is_array($input['subscribers']) &&
			   isset($input['rates']) && is_array($input['rates']) &&
			   isset($input['invoice']) && is_a($input['invoice'], 'Billrun_Cycle_Account_Invoice');
	}

	/**
	 * Construct the subscriber records
	 * @param type $data
	 */
	protected function constructRecords($data) {
		$this->invoice = $data['invoice'];
		$this->records = array();
		$subscribers = $data['subscribers'];
		$cycle = $data['cycle'];
		$plans = &$data['plans'];
		$services = &$data['services'];
		$rates = &$data['rates'];
		
		$sorted = $this->sortSubscribers($subscribers, $cycle->end());
		
		// Filter subscribers.
		$filtered = $this->constructSubscriberData($sorted, $cycle);

		// Subscriber invoice
		$invoiceData = array();
		$invoiceData['key'] = $cycle->key();
		
		$aggregatableRecords = array();
		foreach ($sorted as $sid => $subscriberList) {
			Billrun_Factory::log("Constructing records for sid " . $sid);
			
			$filteredSid = array();
			if(isset($filtered[$sid])) {
				$filteredSid = $filtered[$sid];
			} else {
				Billrun_Factory::log("SID " . $sid . " not in filtered!");
			}
			
			$aggregatableRecords[] = $this->constructForSid($subscriberList, $filteredSid, $plans, $services, $rates, $cycle, $invoiceData);;
		}
		Billrun_Factory::log("Constructed: " . count($aggregatableRecords));
		$this->records = $aggregatableRecords;
	}

	/**
	 * Construct the subscriber records for an sid
	 * @param array $sorted - Sorted subscribers by sid
	 * @param array $filtered - Filtered plans ans services
	 * @param array $plans - Raw plan data from the mongo
	 * @param array $rates - Raw rate data from the mongo
	 * @param Billrun_DataTypes_CycleTime $cycle - Current cycle time.
	 * @param array $invoiceData Invoice
	 * @return Billrun_Cycle_Subscriber Aggregateable subscriber
	 */
	protected function constructForSid($sorted, $filtered, &$plans, &$services, &$rates, $cycle, $invoiceData) {		
		$aggregateable = reset($sorted);
		$changes = array(	'plans'=>array(),
							'services'=> array() );
		$invoice = new Billrun_Cycle_Subscriber_Invoice($rates, $invoiceData);
		foreach ($sorted as $sub) {
			$filterKey = "" . $sub['sto'] . "";
			if(isset($filtered[$filterKey])) {
				$changes = array_merge_recursive($changes, $filtered[$filterKey]); 
			} else {
				Billrun_Factory::log("Key not in dictionary. " . $filterKey);
			}
		}
		$aggregateable['plans'] = $changes['plans'];
		$aggregateable['services'] = $changes['services'];
		
		$aggregateable['invoice'] = &$invoice;
		$aggregateable['mongo_plans'] = &$plans;
		$aggregateable['mongo_services'] = &$services;
		$aggregateable['mongo_rates'] = &$rates;
		$aggregateable['cycle'] = &$cycle;
		$aggregateable['line_stump'] = $this->getLineStump($sub, $cycle);
		$cycleSub =  new Billrun_Cycle_Subscriber($aggregateable);

		return $cycleSub;
	}
	
	protected function getLineStump(array $subscriber, Billrun_DataTypes_CycleTime $cycle) {
		$flatEntry = array(
			'aid' => $subscriber['aid'],
			'sid' => $subscriber['sid'],
			'source' => 'billrun',
			'billrun' => $cycle->key(),
			'type' => 'flat',
			'usaget' => 'flat',
			'urt' => new MongoDate($cycle->end()),
		);
		
		return $flatEntry;
	}
	
	/**
	 * 
	 * @param type $subscribers
	 * @param type $endTime
	 * @return type
	 */
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
		//sort each of the subscriber histort from past to present
		foreach($sorted as  $sid => &$subHistory) {			
			usort($subHistory, function($a, $b){ return $a['sto'] - $b['sto'];});
		}
		return $sorted;
	}
	
	protected function handleSubscriberDates($subscriber, $endTime) {
		$to = $subscriber['to'];
		if(isset($subscriber['to']->sec)) {
			$to = $subscriber['to']->sec;
		}
		
		$from = $subscriber['from'];
		if(isset($subscriber['from']->sec)) {
			$from= $subscriber['from']->sec;
		}

		if($to > $endTime) {
			$to = $endTime;
			Billrun_Factory::log("Taking the end time! " . $endTime);
		}
		
		$subscriber['firstname'] = $subscriber['first_name'];
		$subscriber['lastname'] = $subscriber['last_name'];
		$subscriber['sfrom'] = $from;
		$subscriber['sto'] = $to;
		$subscriber['from'] = date(Billrun_Base::base_datetimeformat, $from);
		$subscriber['to'] = date(Billrun_Base::base_datetimeformat, $to);
		
		return $subscriber;
	}
	
	/**
	 * Construct subscriber data
	 * Consructs the plans and services to be aggregated with the subscriber data
	 * @param type $subscribers
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @return array
	 */
	protected function constructSubscriberData($subscribers, $cycle) {
		$filtered = array();
		
		foreach ($subscribers as $sid => $current) {
			$filtered[$sid] = $this->buildSubAggregator($current, $cycle->end());
		}
		
		return $filtered;
	}
	
	/**
	 * Build the aggregator plan data array
	 * @param array $plans
	 * @param $endTime
	 * @return array
	 */
	protected function buildPlansSubAggregator(array $plans, $endTime) {
		$name = null;
		$from = null;
		$to = null;
		$aggregatorData = array();
		//sort plans history by date
		usort($plans, function($a, $b){ return $a['to']->sec - $b['to']->sec;});
		// Go through the plans
		foreach ($plans as $subPlan) {
			// First iteration.
			if($name === null) {
				$name = $subPlan['plan'];
				$from = $subPlan['plan_activation']->sec;
				$to = $subPlan['to']->sec;
				continue;
			}
			$currName = $subPlan['plan'];
			// If it is the same plan name, continue
			if($name == $currName) {
				$to = $subPlan['to']->sec;
				continue;
			}
			
			// It is a different plan name, construct the aggregator plan record
			$toAdd = array("plan" => $name, "start" => $from, "end" => $to);
			$aggregatorData["$to"]['plans'][] = $toAdd;
			
			// Update all the details.
			$name = $subPlan['plan'];
			$from = max($subPlan['plan_activation']->sec,$subPlan['from']->sec);
			$to = $subPlan['to']->sec;
		}
		// Add the last value.
		$toAdd = array("plan" => $name, "start" => $from, "end" => $to);
		
		if($to > $endTime) {
			$to = $endTime;
			Billrun_Factory::log("Taking the end time! " . $endTime);
		}
		$aggregatorData["$to"]['plans'][] = $toAdd;
			
		return $aggregatorData;
	}
	
	/**
	 * Create a subscriber aggregator from an array of subscriber records.
	 * @param array $current - Array of subscriber records.
	 * @param int $endTime
	 * @todo: Rewrite this function better
	 */
	protected function buildSubAggregator(array $current, $endTime) {		
		$servicesAggregatorData = array();
		
		
		$subscriberPlans = array();
		$services = array();
		$servicesData = array();
		$sto = 0;
		$sstart = PHP_INT_MAX;
		foreach ($current as $subscriber) {
			$sto = $subscriber['sto'];
			$sfrom = $subscriber['sfrom'];
			//Find the earliest instance of the subscriber 
			$sstart = min($sfrom,$sstart);			
			// Get the plans
			$subscriberPlans= array_merge($subscriberPlans,$subscriber['plans']);
			
			// Get the services.
			$currServices = array();
			if(isset($subscriber['services']) && is_array($subscriber['services'])) {
				foreach($subscriber['services'] as  $tmpService) {
					 $serviceData = array( 'name' => $tmpService['name'],
											'quantity' => Billrun_Util::getFieldVal($tmpService['quantity'],1),
											'start'=> $tmpService['from']->sec,
											'end'=> min($tmpService['to']->sec, $endTime ) );
					 
					$stamp = Billrun_Util::generateArrayStamp($serviceData,array('name','start','quantity'));
					$currServices[$stamp] = $serviceData; 
				}
				// Check for removed services in the current subscriber record.
				$serviceCompare = function  ($a, $b)  {
					$aStamp = Billrun_Util::generateArrayStamp($a ,array('name','start','quantity'));
					$bStamp = Billrun_Util::generateArrayStamp($b ,array('name','start','quantity'));
					return strcmp($aStamp , $bStamp);
				};
				
				$removedServices  = array_udiff($services, $currServices, $serviceCompare);
				foreach($removedServices as $stamp => $removed) {
					if($sto < $removed['end'] && $sto <= $services[$stamp]['end']) {
						$services[$stamp]['end'] = $sto;
					} elseif ( $sfrom < $removed['end'] ) {
						$services[$stamp]['end'] = $sfrom;
					}
				}
				$services = array_merge($services,$currServices);
			}	
			
		}
		
		foreach($services as $service) {
				if($service['start'] < $sstart) {
					$service['start'] < $sstart;
				}
				$servicesAggregatorData[$service['end']][] = $service;
		}
		
		$planAggregatorData = $this->buildPlansSubAggregator($subscriberPlans, $endTime);
		
		// Merge the results
		foreach ($servicesAggregatorData as $key => $value) {
			$planAggregatorData[$key]['services'] = $value;
		}
		
		return $planAggregatorData;
	}
	
	public function getInvoice() {
		return $this->invoice;
	}
}
