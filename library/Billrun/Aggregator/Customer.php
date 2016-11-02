<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing aggregator class for customers records
 *
 * @package  calculator
 * @since    0.5
 */
class Billrun_Aggregator_Customer extends Billrun_Aggregator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'customer';

	/**
	 * 
	 * @var int
	 */
	protected $page = null;

	/**
	 * 
	 * @var int
	 */
	protected $size = 200;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $plans = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $lines = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billingCycle = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billrunCol = null;

	/**
	 *
	 * @var int invoice id to start from
	 */
	protected $min_invoice_id = 101;

	/**
	 *
	 * @var boolean is customer price vatable by default
	 */
	protected $vatable = true;
	protected $rates;
	protected $testAcc = false;

	/**
	 * Memory limit in bytes, after which the aggregation is stopped. Set to -1 for no limit.
	 * @var int 
	 */
	protected $memory_limit = -1;

	/**
	 * Accounts to override
	 * @var array
	 */
	protected $overrideAccountIds = array();

	/**
	 * the amount of account lines to preload from the db at a time.
	 * @param int $bulkAccountPreload
	 */
	protected $bulkAccountPreload = 10;

	/**
	 * the account ids that were successfully aggregated
	 * @var array
	 */
	protected $successfulAccounts = array();
	
	/**
	 *
	 * @var int flag to represent if recreate_invoices 
	 */
	
	protected $recreateInvoices = null;
	
	/**
	 * Manager for the aggregate subscriber logic.
	 * @var Billrun_Aggregator_Subscriber_Manager
	 */
	protected $subscriberAggregator;
	
	/**
	 *
	 * @var Billrun_DataTypes_Billrun
	 */
	protected $billrun;
	
	protected $acounts;
	
	public function __construct($options = array()) {
		$this->isValid = false;
		parent::__construct($options);

		ini_set('mongo.native_long', 1); //Set mongo  to use  long int  for  all aggregated integer data.
		
		if (isset($options['aggregator']['recreate_invoices']) && $options['aggregator']['recreate_invoices']) {
			$this->recreateInvoices = $options['aggregator']['recreate_invoices'];
		}
		
		if (isset($options['aggregator']['page'])) {
			$this->page = (int)$options['aggregator']['page'];
		}
		
		$this->buildBillrun($options);
		
		if (isset($options['aggregator']['size']) && $options['aggregator']['size']) {
			$this->size = (int)$options['aggregator']['size'];
		}
		if (isset($options['size']) && $options['size']) {
			$this->size = (int)$options['size'];
		}
		if (isset($options['aggregator']['vatable'])) {
			$this->vatable = $options['aggregator']['vatable'];
		}

		if (isset($options['aggregator']['test_accounts'])) {
			$this->testAcc = $options['aggregator']['test_accounts'];
		}
		if (isset($options['aggregator']['min_invoice_id'])) {
			$this->min_invoice_id = (int) $options['aggregator']['min_invoice_id'];
		}

		if (isset($options['aggregator']['memory_limit_in_mb'])) {
			if ($options['aggregator']['memory_limit_in_mb'] > -1) {
				$this->memory_limit = $options['aggregator']['memory_limit_in_mb'] * 1048576;
			} else {
				$this->memory_limit = $options['aggregator']['memory_limit_in_mb'];
			}
		}
		if (isset($options['aggregator']['bulk_account_preload'])) {
			$this->bulkAccountPreload = (int) $options['aggregator']['bulk_account_preload'];
		}

		if (isset($options['aggregator']['override_accounts'])) {
			$this->overrideAccountIds = $options['aggregator']['override_accounts'];
		}

		$this->billingCycle = Billrun_Factory::db()->billing_cycleCollection();
		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrunCol = Billrun_Factory::db()->billrunCollection();

		if (!$this->recreateInvoices){
			$maxProcesses = Billrun_Factory::config()->getConfigValue('customer.aggregator.processes_per_host_limit');
			$zeroPages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit');
			$pageResult = $this->getPage($maxProcesses, $zeroPages);
			if ($pageResult === FALSE) {
				return;
			}
			$this->page = $pageResult;
		}
		
		$this->isValid = true;
	}
	
	protected function buildBillrun($options) {
		if (isset($options['stamp']) && $options['stamp']) {
			$this->stamp = $options['stamp'];  
			// TODO: Why is there a check for "isBillrunKey"??
		} else if (isset($options['aggregator']['stamp']) && (Billrun_Util::isBillrunKey($options['aggregator']['stamp']))) {
			$this->stamp = $options['aggregator']['stamp'];
		} else {
			$next_billrun_key = Billrun_Billrun::getBillrunKeyByTimestamp(time());
			$current_billrun_key = Billrun_Billrun::getPreviousBillrunKey($next_billrun_key);
  			$this->stamp = $current_billrun_key;
		}
		$this->billrun = new Billrun_DataTypes_Billrun($this->stamp);
	}
	
	protected function beforeLoad() {
		Billrun_Factory::log("Loading page " . $this->page . " of size " . $this->size, Zend_Log::INFO);
	}
	
	/**
	 * load the data to aggregate
	 */
	public function load() {
		$billrunKey = $this->getStamp();
		$cycle = new Billrun_DataTypes_CycleTime($billrunKey);
		$rawResults = $this->loadRawData($cycle);
		$plans = $rawResults['plans'];
		$rates = $rawResults['rates'];
		$services = $rawResults['services'];
		$data = $rawResults['data'];
		
		$sortedRates = $this->constructRates($rates);
		$sortedPlans = $this->constructPlans($plans);
		$sortedServices = $this->constructServices($services);
		$accounts = $this->parseToAccounts($data, $cycle, $sortedPlans, $sortedRates, $sortedServices);
		
		return $accounts;
	}

	/**
	 * Construct the rates array from the mongo raw data.
	 * @param type $rates
	 * @return type
	 */
	protected function constructRates($rates) {
		$sorted = array();
		foreach ($rates as $value) {
			$key = strval($value['_id']);
			$sorted[$key] = $value;
		}
		return $sorted;
	}
	
	/**
	 * Construct the plans array from the mongo raw data.
	 * @param type $plans
	 * @return type
	 */
	protected function constructPlans($plans) {
		$sorted = array();
		foreach ($plans as $value) {
			$name = $value['plan'];
			$translatedDates = Billrun_Utils_Mongo::convertRecordMongoDatetimeFields($value);
			$sorted[$name] = $translatedDates;
		}
		return $sorted;
	}
	
	protected function constructServices($services) {
		$sorted = array();
		foreach ($services as $value) {
			$name = $value['name'];
			$translatedDates = Billrun_Utils_Mongo::convertRecordMongoDatetimeFields($value);
			$sorted[$name] = $translatedDates;
		}
		return $sorted;
	}
	
	/**
	 * Get the raw data
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @return array of raw data
	 */
	protected function loadRawData($cycle) {
		$mongoCycle = new Billrun_DataTypes_MongoCycleTime($cycle);
		
		// Load the plans
		$planResults = $this->aggregatePlans($mongoCycle);
		Billrun_Factory::log("PlanResults: " . count($planResults));
		$ratesResults = $this->aggregateRates($mongoCycle);
		Billrun_Factory::log("RateResults: " . count($ratesResults));
		$servicesResults = $this->aggregateServices($mongoCycle);
		Billrun_Factory::log("ServicesResults: " . count($servicesResults));
		$result = array('plans' => $planResults, 'rates' => $ratesResults, 'services' => $servicesResults);
		if (!$this->overrideAccountIds) {
			$data = $this->aggregateMongo($mongoCycle, $this->page, $this->size);
			$result['data'] = $data;
			return $result;
		}
		
		$data = array();
		foreach ($this->overrideAccountIds as $account_id) {
			$data = $data + $this->aggregateMongo($mongoCycle, 0, 1, $account_id);
		}
		$result['data'] = $data;
		return $result;
	}
	
	/**
	 * 
	 * @param type $outputArr
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @param array $plans
	 * @param array $rates
	 * @return \Billrun_Cycle_Account
	 */
	protected function parseToAccounts($outputArr, Billrun_DataTypes_CycleTime $cycle, array &$plans, array &$rates, array &$services) {
		$accounts = array();
		$lastAid = null;
		$accountData = array();
		$billrunData = array(
			'billrun_key' => $cycle->key(),
			'autoload' => !empty($this->overrideAccountIds));
		foreach ($outputArr as $subscriberPlan) {
			$aid = $subscriberPlan['id']['aid'];
			
			// If the aid is different, store the account.
			if($accountData && $lastAid && ($lastAid != $aid)) {	
				$accountToAdd = $this->getAccount($billrunData, $accountData, $lastAid, $cycle, $plans, $services, $rates);
				if($accountToAdd) {
					$accounts[] = $accountToAdd;
				}
				$accountData = array();
			}
			
			$lastAid = $aid;
			
			$type = $subscriberPlan['id']['type'];
			if ($type === 'account') {
				$accountData['attributes'] = $this->constructAccountAttributes($subscriberPlan);
				continue;
			}
			
			if (($type === 'subscriber') && $accountData) {
				$raw = $subscriberPlan['id'];
				$raw['plans'] = $subscriberPlan['plan_dates'];
				$raw['from'] = $subscriberPlan['plan_dates'][0]['from'];
				$raw['to'] = $subscriberPlan['plan_dates'][count($subscriberPlan['plan_dates']) - 1]['to'];
				$accountData['subscribers'][] = $raw;
			}
		}
		
		if($accountData) {
			$accountToAdd = $this->getAccount($billrunData, $accountData, $lastAid, $cycle, $plans, $services, $rates);
			if($accountToAdd) {
				$accounts[] = $accountToAdd;
			}
		}
		
		return $accounts;
	}
	
	/**
	 * Returns a single cycle account instnace.
	 * If the account already exists in billrun, returns false..
	 * @param array $billrunData
	 * @param int $aid
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @param array $plans
	 * @param array $services
	 * @param array $rates
	 * @return Billrun_Cycle_Account | false 
	 */
	protected function getAccount($billrunData, $accountData, $aid, Billrun_DataTypes_CycleTime $cycle, array &$plans, array &$services, array &$rates) {
		// Handle no subscribers.
		if(!isset($accountData['subscribers'])) {
			$accountData['subscribers'] = array();
		}
		
		$accountData['cycle'] = $cycle;
		$accountData['plans'] = &$plans;
		$accountData['services'] = &$services;
		$accountData['rates'] = &$rates;

		$billrunData['aid'] = $aid;
		$billrunData['attributes'] = $accountData['attributes'];
		$invoice = new Billrun_Cycle_Account_Invoice($billrunData);

		// Check if already exists.
		if($invoice->exists()) {
			Billrun_Factory::log("Billrun " . $cycle->key() . " already exists for account " . $aid, Zend_Log::ALERT);
			return false;
		} 
		
		$accountData['invoice'] = $invoice;
		return new Billrun_Cycle_Account($accountData);
	}
	
	/**
	 * Construct the account data
	 * @param string $key - Billrun key
	 * @param int $aid - Current account id
	 * @param array $subscriberPlan - Current subscriber plan
	 * @return type
	 */
	protected function constructAccountData($key, $aid, $subscriberPlan) {
		$vat = self::getVATByBillrunKey($key);
		$accountData = array(
			'aid' => $aid,
			'vat' => $vat,
			'billrun_key' => $key,
		);
		
		$accountData['attributes'] = $this->constructAccountAttributes($subscriberPlan);
	}
	
	/**
	 * This function constructs the account attributes for a billrun cycle account
	 * @param array $subscriberPlan - Current subscriber plan.
	 */
	protected function constructAccountAttributes($subscriberPlan) {
		$firstname = $subscriberPlan['id']['first_name'];
		$lastname = $subscriberPlan['id']['last_name'];
		
		$paymentDetails = 'No payment details';
		if (isset($subscriberPlan['card_token']) && !empty($token = $subscriberPlan['card_token'])) {
			$paymentDetails = Billrun_Util::getTokenToDisplay($token);
		}
		
		return array(
			'firstname' => $firstname,
			'lastname' => $lastname,
			'fullname' => $firstname . ' ' . $lastname,
			'address' => $subscriberPlan['id']['address'],
			'payment_details' => $paymentDetails
		);
	}
	
	protected function handleInvoices($data) {
		$query = array('billrun_key' => $this->stamp, 'page_number' => (int)$this->page, 'page_size' => $this->size);
		$dataCount = count($data);
		$update = array('$set' => array('count' => $dataCount));
		$this->billingCycle->update($query, $update);
	}
	
	protected function afterLoad($data) {
		if (!$this->recreateInvoices){			
			$this->handleInvoices($data);
		}

		Billrun_Factory::log("Acount entities loaded: " . count($data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
		
		if ($this->bulkAccountPreload) {
			$this->clearForAcountPreload($data);
		}
		
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($data, &$this));
		$this->acounts = &$data;
	}
	
	protected function clearForAcountPreload($data) {
		Billrun_Factory::log('loading accounts that will be needed to be preloaded...', Zend_Log::INFO);
		$dataKeys = array_keys($data);
		//$existingAccounts = array();			
		foreach ($dataKeys as $key => $aid) {
			if (!$this->overrideAccountIds && $this->billrun->exists($aid)) {
				unset($dataKeys[$key]);
			}
		}
		return $dataKeys;
	}
	
	protected function afterAggregate($results) {
		Billrun_Factory::log("Writing the invoice data!");
		// Write down the invoice data.
		foreach ($this->acounts as $account) {
			$account->writeInvoice($this->min_invoice_id);
		}
		
		$end_msg = "Finished iterating page $this->page of size $this->size. Memory usage is " . memory_get_usage() / 1048576 . " MB\n";
		$end_msg .="Processed " . (count($results)) . " accounts";
		Billrun_Factory::log($end_msg, Zend_Log::INFO);
		$this->sendEndMail($end_msg);

		// @TODO trigger after aggregate
		if (!$this->recreateInvoices){
			$cycleQuery = array('billrun_key' => $this->stamp, 'page_number' => $this->page, 'page_size' => $this->size);
			$cycleUpdate = array('$set' => array('end_time' => new MongoDate()));
			$this->billingCycle->update($cycleQuery, $cycleUpdate);
		}
		Billrun_Factory::dispatcher()->trigger('afterAggregate', array($results, &$this));
	}
	
	protected function sendEndMail($msg) {
		$recipients = Billrun_Factory::config()->getConfigValue('log.email.writerParams.to');
		if ($recipients) {
			Billrun_Util::sendMail("BillRun customer aggregate page finished", $msg, $recipients);
		}
	}

	// TODO: Move this function to a "collection aggregator class"
	protected function aggregatePlans($cycle) {
		$pipelines[] = $this->getPlansMatchPipeline($cycle);
		$pipelines[] = $this->getPlansProjectPipeline();
		$coll = Billrun_Factory::db()->plansCollection();
		$results = iterator_to_array($coll->aggregate($pipelines));
		
		if (!is_array($results) || empty($results) ||
			(isset($results['success']) && ($results['success'] === FALSE))) {
			return array();
		}
		return $results;
	}
	
	// TODO: Move this function to a "collection aggregator class"
	protected function aggregateServices($cycle) {
		$pipelines[] = $this->getPlansMatchPipeline($cycle);
		$coll = Billrun_Factory::db()->servicesCollection();
		$results = iterator_to_array($coll->aggregate($pipelines));
		
		if (!is_array($results) || empty($results) ||
			(isset($results['success']) && ($results['success'] === FALSE))) {
			return array();
		}
		return $results;
	}
	
	// TODO: Move this function to a "collection aggregator class"
	protected function aggregateRates($cycle) {
		$pipelines[] = $this->getPlansMatchPipeline($cycle);
		
		$coll = Billrun_Factory::db()->ratesCollection();
		$results = iterator_to_array($coll->aggregate($pipelines));
		
		if (!is_array($results) || empty($results) ||
			(isset($results['success']) && ($results['success'] === FALSE))) {
			return array();
		}
		return $results;
	}
	
	/**
	 * 
	 * @param Billrun_DataTypes_MongoCycleTime $cycle
	 * @return type
	 */
	// TODO: Move this function to a "collection aggregator class"
	protected function getPlansMatchPipeline($cycle) {
		return array(
			'$match' => array(
				'from' => array(
					'$lt' => $cycle->end()
					),
				'to' => array(
					'$gt' => $cycle->start()
					)
				)
			);
	}
	
	// TODO: Move this function to a "collection aggregator class"
	protected function getPlansProjectPipeline() {
		return array(
			'$project' => array(
				'plan' => '$name',
				'upfront' => 1,
				'vatable' => 1,
				'price' => 1,
				'recurrence.periodicity' => 1,
				'plan_activation' => 1,
				'plan_deactivation' => 1
			)
		);
	}
	
	/**
	 * Aggregate mongo with a query
	 * @param Billrun_DataTypes_MongoCycleTime $cycle - Current cycle time
	 * @param int $page - page
	 * @param int $size - size
	 * @param int $aid - Account id, null by deafault
	 * @return array 
	 */
	public function aggregateMongo($cycle, $page, $size, $aid = null) {
		if ($aid) {
			$page = 0;
			$size = 1;
		}
		$pipelines[] = $this->getMatchPiepline($cycle);
		if ($aid) {
			$pipelines[count($pipelines) - 1]['$match']['aid'] = intval($aid);
		}
		
		$pipelines[] = array(
			'$group' => array(
				'_id' => array(
					'aid' => '$aid',
				),
				'sub_plans' => array(
					'$push' => array(
						'type' => '$type',
						'sid' => '$sid',
						'plan' => '$plan',
						'from' => '$from',
						'to' => '$to',
						'plan_activation' => '$plan_activation',
						'plan_deactivation' => '$plan_deactivation',
						'firstname' => '$firstname',
						'lastname' => '$lastname',
						'address' => '$address',
						'services' => '$services'
					),
				),
				'card_token' => array(
					'$first' => '$card_token'
				),
			),
		);
		$pipelines[] = array(
			'$skip' => $page * $size,
		);
		$pipelines[] = array(
			'$limit' => intval($size),
		);
		$pipelines[] = array(
			'$unwind' => '$sub_plans',
		);
		$pipelines[] = array(
			'$group' => array(
				'_id' => array(
					'aid' => '$_id.aid',
					'sid' => '$sub_plans.sid',
					'plan' => '$sub_plans.plan',
					'first_name' => '$sub_plans.firstname',
					'last_name' => '$sub_plans.lastname',
					'type' => '$sub_plans.type',
					'address' => '$sub_plans.address',
					'services' => '$sub_plans.services'
				),
				'plan_dates' => array(
					'$push' => array(
						'plan' => '$sub_plans.plan',
						'from' => '$sub_plans.from',
						'to' => '$sub_plans.to',
						'plan_activation' => '$sub_plans.plan_activation',
						'plan_deactivation' => '$sub_plans.plan_deactivation',
					),
				),
				'card_token' => array(
					'$first' => '$card_token'
				),
			),
		);
		
		$pipelines[] = $this->getSortPipeline();

		$pipelines[] = array(
			'$project' => array(
				'_id' => 0,
				'id' => '$_id',
				'plan_dates' => 1,
				'card_token' => 1,
			)
		);
		
	
		$coll = Billrun_Factory::db()->subscribersCollection();
		return $this->aggregatePipelines($pipelines, $coll);
	}
	
	
	protected function aggregatePipelines(array $pipelines, Mongodloid_Collection $collection) {
		$cursor = $collection->aggregate($pipelines);
		$results = iterator_to_array($cursor);
		if (!is_array($results) || empty($results) ||
			(isset($results['success']) && ($results['success'] === FALSE))) {
			return array();
		} 	
		return $results;
	}
	
	/**
	 * 
	 * @param Billrun_DataTypes_MongoCycleTime $mongoCycle
	 * @return type
	 */
	protected function getMatchPiepline($mongoCycle) {
		$match = array(
			'$match' => array(
				'$or' => array(
					array( // Account records
						'type' => 'account',
						'from' => array(
							'$lte' => $mongoCycle->end(),
						),
						'to' => array(
							'$gte' => $mongoCycle->start(),
						),
					),
					array( // Subscriber records
						'type' => 'subscriber',
						'plan' => array(
							'$exists' => 1
						),
						'$or' => array(
							array(
								'from' => array(// plan started during billing cycle
									'$gte' => $mongoCycle->start(),
									'$lt' => $mongoCycle->end(),
								),
							),
							array(
								'to' => array(// plan ended during billing cycle
									'$gte' => $mongoCycle->start(),
									'$lt' => $mongoCycle->end(),
								),
							),
							array(// plan started before billing cycle and ends after
								'from' => array(
									'$lt' => $mongoCycle->start()
								),
								'to' => array(
									'$gte' => $mongoCycle->end(),
								),
							),
							array(// searches for a next plan. used for prepaid plans
								'from' => array(
									'$lte' => $mongoCycle->end(),
								),
								'to' => array(
									'$gt' => $mongoCycle->end(),
								),
							),
						)
					)
				)
			)
		);
		
		// If the accounts should not be overriden, filter the existing ones before.
		if(!$this->overrideAccountIds) {
			// Get the aid exclusion query
			$exclusionQuery = $this->billrun->existingAccountsQuery();
			$match['$match']['aid'] = $exclusionQuery;
		}
		
		return $match;
	}
	
	protected function getSortPipeline() {
		return array(
			'$sort' => array(
				'_id.aid' => 1,
				'_id.sid' => 1,
				'_id.type' => -1,
				'_id.plan' => 1,
				
				// TODO: We might want to uncomment this
//				'plan_dates.from' => 1,
			),
		);
	}

	protected function saveCredit($credit, $billrun_key) {
		return $insertRow;
	}

	protected function save($results) {
		if(empty($results)) {
			Billrun_Factory::log("Empty aggregate customer results, skipping save");
			return;
		}
		$linesCol = Billrun_Factory::db()->linesCollection();
		$linesCol->batchInsert($results);
	}

	/**
	 *
	 * @param type $sid
	 * @param type $item
	 * @deprecated update of billing line is done in customer pricing stage
	 */
	protected function updateBillingLine($sid, $item) {
		
	}

	/**
	 * method to update the billrun by the billing line (row)
	 * @param Mongodloid_Entity $billrun the billrun line
	 * @param Mongodloid_Entity $line the billing line
	 *
	 * @return boolean true on success else false
	 */
	protected function updateBillrun($billrun, $line) {
		
	}

	/**
	 * HACK TO MAKE THE BILLLRUN FASTER
	 * Get a rate from the row
	 * @param Mongodloid_Entity the row to get rate from
	 * @return Mongodloid_Entity the rate of the row
	 */
	protected function getRowRate($row) {
		$raw_rate = $row->get('arate', true);
		$id_str = strval($raw_rate['$id']);
		if (isset($this->rates[$id_str])) {
			return $this->rates[$id_str];
		} else {
			return $row->get('arate', false);
		}
	}
	
	// TODO: Move to Billrun_Billingcycle
	public static function isBillingCycleOver($cycleCol, $stamp, $size, $zeroPages=1){
		if (empty($zeroPages) || !Billrun_Util::IsIntegerValue($zeroPages)) {
			$zeroPages = 1;
		}
		$cycleQuery = array('billrun_key' => $stamp, 'page_size' => $size, 'count' => 0);
		$cycleCount = $cycleCol->query($cycleQuery)->count();
		
		if ($cycleCount >= $zeroPages) {
			Billrun_Factory::log("Finished going over all the pages", Zend_Log::DEBUG);
			return true;
		}		
		return false;
	}
	
	/**
	 * Finding which page is next in the biiling cycle
	 * @param the number of max tries to get the next page in the billing cycle
	 * @return number of the next page that should be taken
	 */
	protected function getPage($maxProcesses, $zeroPages, $retries = 100) {
		if ($retries <= 0) { // 100 is arbitrary number and should be enough
			Billrun_Factory::log()->log("Failed getting next page, retries exhausted", Zend_Log::ALERT);
			return false;
		}
		if ($this->isBillingCycleOver($this->billingCycle, $this->stamp, $this->size, $zeroPages) === TRUE){
			 return false;
		}
		
		$host = gethostname();
		if(!$this->validateMaxProcesses($host, $maxProcesses)) {
			return false;
		}
		
		$nextPage = $this->getNextPage();
		if($nextPage === false) {
			Billrun_Factory::log("getPage: Failed getting next page.");
			return false;
		}
		
		if($this->checkExists($nextPage, $host)) {
			$error = "Page number ". $nextPage ." already exists.";
			Billrun_Factory::log($error . " Trying Again...", Zend_Log::NOTICE);
			return $this->getPage($maxProcesses, $zeroPages, $retries - 1);
		}
		
		return $nextPage;
	}
	
	/**
	 * Validate the max processes config valus
	 * @param string $host - Host name value
	 * @param int $maxProcesses - The max number of proccesses
	 * @return boolean true if valid
	 */
	protected function validateMaxProcesses($host, $maxProcesses) {
		$query = array('billrun_key' => $this->stamp, 'page_size' => $this->size, 'host' => $host,'end_time' => array('$exists' => false));
		$processCount = $this->billingCycle->query($query)->count();
		if ($processCount >= $maxProcesses) {
			Billrun_Factory::log("Host ". $host. "is already running max number of [". $maxProcesses . "] processes", Zend_Log::DEBUG);
			return false;
		}
		return true;
	}
	
	/**
	 * Get the next page index
	 * @return boolean|int
	 */
	protected function getNextPage() {
		$cycleQuery = array('billrun_key' => $this->stamp, 'page_size' => $this->size);
		$currentDocument = $this->billingCycle->query($cycleQuery)->cursor()->sort(array('page_number' => -1))->limit(1)->current();
		if (is_null($currentDocument)) {
			Billrun_Factory::log("getNexPage: failed to retrieve document");
			return false;
		}
		
		// First page
		if (!isset($currentDocument['page_number'])) {
			return 0;
		} 
		
		return $currentDocument['page_number'] + 1;
	}
	
	/**
	 * Check if 
	 * @param type $nextPage
	 * @param type $host
	 * @return type
	 */
	protected function checkExists($nextPage, $host) {
		$query = array('billrun_key' => $this->stamp, 'page_number' => $nextPage, 'page_size' => $this->size);
		$modifyQuery = array_merge($query, array('host' => $host, 'start_time' => new MongoDate()));
		$modify = array('$setOnInsert' => $modifyQuery);
		$checkExists = $this->billingCycle->findAndModify($query,$modify,null, array("upsert" => true));
		
		return !$checkExists->isEmpty();
	}
	
	protected function addAccountFieldsToBillrun($billrun, $account) {
		$options = empty($account['options']) ? array() : $this->getOptionEntries($billrun, $account);
		$billrun->populateInvoiceWithAccountData($account, $options);
	}

}
