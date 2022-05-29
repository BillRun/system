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
		return isset($input['history']) && is_array($input['history']) &&
			   isset($input['subscriber_info']['invoice']) && is_a($input['subscriber_info']['invoice'], 'Billrun_Cycle_Subscriber_Invoice');
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
	 * Get the sid of the subscriber.
	 * @return int
	 */
	public function getSid() {
		return $this->sid;
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
		Billrun_Factory::log("Aggregating plans!");
		$aggregatedPlans = $this->generalAggregate($this->records['plans'], Billrun_Cycle_Data_Plan::class);
		Billrun_Factory::log("Aggregating services!");
		$aggregatedServices = $this->generalAggregate($this->records['services'], Billrun_Cycle_Data_Service::class);

		$usageLines = $this->loadSubscriberLines();
		$results = array_merge($aggregatedPlans, $aggregatedServices);
		Billrun_Factory::log("Subscribers aggregated " . count($results) . ' lines');
		//TODO add usage aggregation per subscriber here
		// Write the results to the invoice
		$this->invoice->addLines(array_merge($usageLines,$results));
		return $results;
	}

	/**
	 * Gets all the account lines for this billrun from the db
	 * @return an array containing all the  accounts with thier lines.
	 */
	public function loadSubscriberLines() {
		$ret = array();
		$sid = $this->sid;
		$aid = $this->aid;
		$query = array(
			'aid' => $aid,
			'sid' => $sid,
			'billrun' => $this->cycleAggregator->getCycle()->key()
		);
		
		// in case of expected invoice we might want to ignore usage lines
		if ($this->cycleAggregator->ignoreCdrs) {
			$query['type'] = 'credit';
		}
		
		// in case of expected invoice for subscriber termintation we might want to prepone future installments
		if ($this->cycleAggregator->isFakeCycle() && Billrun_Factory::config()->getConfigValue('billrun.installments.prepone_on_termination', false)) {
			$installmentLines = $this->cycleAggregator->handleInstallmentsPrepone($this->cycleAggregator->getData());
			$futureCharges = [];
			foreach ($installmentLines as $line	) {
				if ($line['sid'] == $sid) {
					$futureCharges[] = $line;
				}
			}
		}
		
		$requiredFields = array('aid' => 1, 'sid' => 1);
		$filter_fields = Billrun_Factory::config()->getConfigValue('billrun.filter_fields', array());

		$sort = array(
			'urt' => 1,
		);

		Billrun_Factory::log('Querying for subscriber ' . $aid . ':' . $sid . ' lines', Zend_Log::DEBUG);
		$addCount = $bufferCount = 0;
		$linesCol = Billrun_Factory::db()->linesCollection();
		$fields = array_merge($filter_fields, $requiredFields);
		$limit = Billrun_Factory::config()->getConfigValue('billrun.linesLimit', 100000);

		do {
			$bufferCount += $addCount;
			$cursor = $linesCol->query($query)->cursor()->fields($fields)
					->sort($sort)->skip($bufferCount)->limit($limit)->timeout(Billrun_Factory::config()->getConfigValue('db.long_queries_timeout',14400000));
			foreach ($cursor as $line) {
				$ret[$line['stamp']] = $line->getRawData();
			}
		} while (($addCount = $cursor->count(true)) > 0);
		
		// Add future installments to cycle
		foreach ($futureCharges as $line) {
			$ret[$line['stamp']] = 	$line->getRawData();
		}
		
		Billrun_Factory::log('Finished querying for subscriber ' . $aid . ':' . $sid . ' lines: ' . count($ret), Zend_Log::DEBUG);

		return $ret;
	}

	//------------------------------------------ Protected -------------------------------------------

	/**
	 * This function wraps general internal aggregation logic
	 * @param type $data
	 * @return type
	 */
	protected function generalAggregate($data, $generatorClassName) {
		if(!$data) {
			Billrun_Factory::log("generalAggregate received empty data!");
			return array();
		}

		$results = array();

		foreach ($data as $current) {
			$billableLinesGenerator = new $generatorClassName($current);
			$results = array_merge($results, $billableLinesGenerator->getBillableLines());
		}
		return $results;
	}

	protected function constructRecords($data) {

		$constructedData = $this->constructSubscriberData($data['history'], $this->cycleAggregator->getCycle()->end());
		$dataForAggration = $data['subscriber_info'];
		$dataForAggration['plans'] = $constructedData['plans'];
		$dataForAggration['services'] = $constructedData['services'];

		if(isset($dataForAggration['next_plan'])) {
			$this->nextPlan = $dataForAggration['next_plan'];
		}

		$this->sid = intval($dataForAggration['sid']);
		$this->aid = intval($dataForAggration['aid']);

		$this->constructServices($dataForAggration);
		$this->constructPlans($dataForAggration);
		$this->constructInvoice($dataForAggration);
	}

	protected function constructInvoice($data) {
		$this->invoice = &$data['invoice'];

		$this->invoice->setData('aid', $data['aid']);
		$this->invoice->setData('sid', $data['sid']);
		$this->invoice->setData('firstname', $data['first_name']);
		$this->invoice->setData('lastname', $data['last_name']);
		foreach(Billrun_Factory::config()->getConfigValue('customer.aggregator.subscriber.passthrough_data',array()) as $dstField => $srcField) {
			// print_r($dstField);
			// print_r($data[$dstField]);
			// print_r($srcField);
			// print_r($data[$srcField]);
			if(is_array($srcField) && !empty($data[$dstField])) {
				$this->invoice->setData($dstField, $data[$dstField]);
			} else if(!is_array($srcField) && !empty($data[$srcField])) {
				$this->invoice->setData($dstField, $data[$srcField]);
			}
		}

		//$this->invoice->setData('plan', $data['plan']);
	}

	/**
	 * Construct the services array
	 * @param type $data
	 */
	protected function constructServices($data) {
		$this->records['services'] = array();

		$services = Billrun_Util::getFieldVal($data["services"], array());
		//Get services active at billing cycle date
		$mongoServices = $this->cycleAggregator->getServices();

		$cycle = $this->cycleAggregator->getCycle();
		$stumpLine = $data['line_stump'];

		Billrun_Factory::dispatcher()->trigger('beforeConstructServices',array($this,&$mongoServices,&$services,&$stumpLine));
		foreach ($services as &$arrService) {
			// Service name
			$index = $arrService['name'];
			if(!isset($mongoServices[$index])) {
				Billrun_Factory::log("Ignoring inactive service: " . print_r($arrService,1));
				continue;
			}

			$mongoServiceData = $mongoServices[$index]->getRawData();
			unset($mongoServiceData['_id']);
			$serviceData = array_merge($mongoServiceData, $arrService);
			$serviceData['cycle'] = $cycle;
			$serviceData['line_stump'] = $stumpLine;
			if (Billrun_Utils_Plays::isPlaysInUse()) {
				$serviceData['subscriber_fields'] = array('play' => isset($data['play']) ? $data['play'] : Billrun_Utils_Plays::getDefaultPlay()['name']);
			}
			$this->records['services'][] = $serviceData;
		}
		Billrun_Factory::dispatcher()->trigger('afterConstructServices',array($this,&$this->records['services'],&$cycle,&$mongoServices));
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
		$mongoPlans = $this->cycleAggregator->getPlans();

		$cycle = $this->cycleAggregator->getCycle();
		$stumpLine = $data['line_stump'];

		foreach ($plans as &$value) {
			// Plan name
			$index = $value['plan'];
			if(!isset($mongoPlans[$index])) {
				if(!empty($value['sid'])) {
				Billrun_Factory::log("Ignoring inactive plan: " . print_r($value,1));
				}
				continue;
			}

			$rawMongo = $mongoPlans[$index]->getRawData();
			unset($rawMongo['_id']);
			$planData = array_merge($value, $rawMongo);
			$planData['cycle'] = $cycle;
			if (Billrun_Utils_Plays::isPlaysInUse()) {
				$planData['subscriber_fields'] = array('play' => isset($data['play']) ? $data['play'] : Billrun_Utils_Plays::getDefaultPlay()['name']);
			} 
			$planData['line_stump'] = $stumpLine;
			$planData['deactivation_date'] = $data['deactivation_date'];
			$this->records['plans'][] = $planData;
		}
	}

	/**
	 * Construct subscriber data
	 * Consructs the plans and services to be aggregated with the subscriber data
	 * @param type $subscribers
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @return array
	 */
	protected function constructSubscriberData($subscriberHistory, $cycleEndTime) {

		$timedArray = $this->buildSubAggregator($subscriberHistory, $cycleEndTime);

		$filtered = array();
		foreach($timedArray as  $plansAndServices) {
			$filtered = array_merge_recursive($filtered,$plansAndServices);
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
				$to = empty($subPlan['plan_deactivation']) ? $subPlan['to']->sec : $subPlan['plan_deactivation']->sec;
				continue;
			}
			$currName = $subPlan['plan'];
			// If it is the same plan name, continue
			if($name == $currName && $from == $subPlan['plan_activation']->sec) {
				$to = empty($subPlan['plan_deactivation']) ? $subPlan['to']->sec : $subPlan['plan_deactivation']->sec;
				continue;
			}

			// It is a different plan name, construct the aggregator plan record
			$toAdd = array("plan" => $name, "start" => $from, "end" => $to);
			$aggregatorData["$to"]['plans'][] = $toAdd;

			// Update all the details.
			$name = $subPlan['plan'];
			$from = max($subPlan['plan_activation']->sec, $subPlan['from']->sec);
			$to = $subPlan['to']->sec;
		}
		// Add the last value.
		$toAdd = array("plan" => $name,'name'=>$name, "start" => $from, "end" => $to);

		if($to > $endTime) {
			$to = $endTime;
			Billrun_Factory::log("Taking the end time! " . $endTime);
		}
		$aggregatorData["$to"]['plans'][] = $toAdd;

		return $aggregatorData;
	}

	/**
	 * Build the services start and  end records for  a given subscriber
	 * @param type $subscriber
	 * @param type $previousServices
	 * @return type
	 */
	protected function buildServicesSubAggregator($subscriber, $previousServices, $endTime) {
		Billrun_Factory::dispatcher()->trigger('beforeBuildServicesSubAggregator',array($this,&$subscriber,&$previousServices,&$endTime));
		$currServices = array();
		$retServices = &$previousServices;
		$sto = $subscriber['sto'];
		$sfrom = $subscriber['sfrom'];
		$activationDate = @$subscriber['activation_date']->sec + (@$subscriber['activation_date']->usec/ 1000000) ?: 0;
		$deactivationDate = @$subscriber['deactivation_date']->sec + (@$subscriber['deactivation_date']->usec/ 1000000) ?: PHP_INT_MAX;

		if(isset($subscriber['services']) && is_array($subscriber['services'])) {
			foreach($subscriber['services'] as  $tmpService) {
				 $serviceData = array(  'name' => $tmpService['name'],
										'quantity' => Billrun_Util::getFieldVal($tmpService['quantity'],1),
										'service_id' => Billrun_Util::getFieldVal($tmpService['service_id'],null),
										'plan' => $subscriber['sid'] != 0 ? $subscriber['plan'] : null,
										'start'=> max($tmpService['from']->sec + ($tmpService['from']->usec/ 1000000), $activationDate),
										'end'=> min($tmpService['to']->sec +($tmpService['to']->usec/ 1000000), $endTime , $deactivationDate) );
				 if($serviceData['start'] !== $serviceData['end']) {
					$stamp = Billrun_Util::generateArrayStamp($serviceData,array('name','start','quantity','service_id'));
					$currServices[$stamp] = $serviceData;
				 }
			}
			// Function to Check for removed services in the current subscriber record.
			$serviceCompare = function  ($a, $b)  {
				$aStamp = Billrun_Util::generateArrayStamp($a ,array('name','start','quantity','service_id'));
				$bStamp = Billrun_Util::generateArrayStamp($b ,array('name','start','quantity','service_id'));
				return strcmp($aStamp , $bStamp);
			};

			$removedServices  = array_udiff($previousServices, $currServices, $serviceCompare);
			foreach($removedServices as $stamp => $removed) {
				if ( $sfrom <  $removed['end'] ) {
					$retServices[$stamp]['end'] = $sfrom;
				}
			}
			$retServices = array_merge($retServices, $currServices);
		}
		Billrun_Factory::dispatcher()->trigger('afterBuildServicesSubAggregator',array($this,&$retServices));
		return $retServices;
	}

	protected function getServicesIncludedInPlan($plansData) {
		$mongoPlans = $this->cycleAggregator->getPlans();
		$includedServices = array();
		if(!empty($plansData['plans']) ) {
			foreach($plansData['plans'] as $planData) {
				if(!empty($mongoPlans[$planData['plan']]['include']['services'])) {
					foreach($mongoPlans[$planData['plan']]['include']['services'] as $srvName) {
						$includedServices[] = array(
												'name'=> $srvName,
												'quantity' => 1,
												'plan' => $planData['plan'],
												'start' => $planData['start'],
												'end' => $planData['end'],
												'included' => 1,
											);
					}
				}
			}
		}
		return $includedServices;
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
		$subend = 0;
		foreach ($current as $subscriber) {
			$subscriber = $this->handleSubscriberDates($subscriber, $endTime);
			$subend = max($subscriber['sto'], $subend);
			// Get the services for the subscriber.
			$services = $this->buildServicesSubAggregator($subscriber, $services, $endTime);
			if(!$this->hasPlans($subscriber)) {
				continue;
			}
			// Get the plans
			$subscriberPlans= array_merge($subscriberPlans,Billrun_Util::getFieldVal($subscriber['plans'],array()));


		}

		foreach($services as $service) {
				//Adjust serives that mistakenly started before the subscriber existed to start at the  same time  of the subscriber creation
				$service['end'] =  min($subend, $service['end']);
				$service['start'] =  max($subscriber['activation_date']->sec, $service['start']);
				$servicesAggregatorData[$service['end']][] = $service;
		}

		$planAggregatorData = $this->buildPlansSubAggregator($subscriberPlans, $endTime);

		// Merge the results
		foreach ($servicesAggregatorData as $key => $value) {
			$planAggregatorData[$key]['services'] = $value;
		}

		//Added services  that are included in the plan
		foreach($planAggregatorData as $key =>$plansData) {
			$planAggregatorData[$key]['services'] = array_merge(
														$this->getServicesIncludedInPlan($plansData),
														Billrun_Util::getFieldVal($planAggregatorData[$key]['services'],array())
													);
		}

		ksort($planAggregatorData,SORT_NUMERIC);

		return array_reverse($planAggregatorData);
	}

	protected function handleSubscriberDates($subscriber, $endTime) {
		if (!empty($subscriber['from']->sec)) {
			$to = $subscriber['to']->sec;
			$from = $subscriber['from']->sec;
		} else {
		$to = $subscriber['to'];
		$from = $subscriber['from'];

		}
		if($to > $endTime) {
			$to = $endTime;
			Billrun_Factory::log("Taking the end time! " . $endTime);
		}

		$subscriber['sfrom'] = $from;
		$subscriber['sto'] = $to;
		$subscriber['from'] = date(Billrun_Base::base_datetimeformat, $from);
		$subscriber['to'] = date(Billrun_Base::base_datetimeformat, $to);

		return $subscriber;
	}

	/**
	 * Test if a subscription entry contain a plan (if not then it`s an account level "subscription") 
	 * @param type $subscription
	 * @return type true if the subscription contain a plan false otherwise (account as sub)
	 */
	protected function hasPlans($subscription) {
		return !empty($subscription['plans']);
	}
}
