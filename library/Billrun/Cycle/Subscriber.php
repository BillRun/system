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
		if(isset($data['next_plan'])) {
			$this->nextPlan = $data['next_plan'];
		}
		
		$this->sid = $data['sid'];
		$this->aid = $data['aid'];
		
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
		//Get services active at billing cycle date
		$mongoServices = $this->cycleAggregator->getServices();
		
		$cycle = $this->cycleAggregator->getCycle();
		$stumpLine = $data['line_stump'];
		
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
		$mongoPlans = $this->cycleAggregator->getPlans();
		
		$cycle = $this->cycleAggregator->getCycle();
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

		$requiredFields = array('aid' => 1, 'sid' => 1);
		$filter_fields = Billrun_Factory::config()->getConfigValue('billrun.filter_fields', array());

		$sort = array(
			'urt' => 1,
		);

		Billrun_Factory::log('Querying for subscriber ' . $aid . ':' . $sid . ' lines', Zend_Log::DEBUG);
		$addCount = $bufferCount = 0;
		$linesCol = Billrun_Factory::db()->linesCollection();
		$fields = array_merge($filter_fields, $requiredFields);
		$limit = Billrun_Factory::config()->getConfigValue('billrun.linesLimit', 10000);

		do {
			$bufferCount += $addCount;
			$cursor = $linesCol->query($query)->cursor()->fields($fields)
					->sort($sort)->skip($bufferCount)->limit($limit);
			foreach ($cursor as $line) {
				$ret[$line['stamp']] = $line->getRawData();
			}
		} while (($addCount = $cursor->count(true)) > 0);
		Billrun_Factory::log('Finished querying for account ' . $aid . ':' . $sid . ' lines: ' . count($ret), Zend_Log::DEBUG);
		
		return $ret;
	}
}
