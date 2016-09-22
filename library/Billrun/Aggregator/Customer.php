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

		$this->loadRates();
		if (!$this->recreateInvoices){
			$maxProcesses = Billrun_Factory::config()->getConfigValue('customer.aggregator.processes_per_host_limit');
			$zeroPages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit');
			$pageResult = $this->getPage($maxProcesses, $zeroPages);
			if ($pageResult === FALSE) {
				return;
			}
			$this->page = $pageResult;
		}
		
		// TODO: Get the types of subscriber aggregator
		$types = array('flat', 'credit', 'services');
		$this->subscriberAggregator = new Billrun_Aggregator_Subscriber_Manager($types);
		
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
		$cycle = new Billrun_DataTypes_CycleTime($this->getStamp());
		$data = array();
		if ($this->overrideAccountIds) {
			$data = array();
			foreach ($this->overrideAccountIds as $account_id) {
				$data = $data + $this->aggregateMongo($cycle, 0, 1, $account_id);
			}
		} else {
			$data = $this->aggregateMongo($cycle, $this->page, $this->size);
		}
		
		return $data;
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

		Billrun_Factory::log("aggregator entities loaded: " . count($data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
		
		if ($this->bulkAccountPreload) {
			$this->clearForAcountPreload($data);
		}

		
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($data, &$this));
	}
	
	protected function clearForAcountPreload($data) {
		Billrun_Factory::log('loading accounts that will be needed to be preloaded...', Zend_Log::INFO);
		$dataKeys = array_keys($data);
		//$existingAccounts = array();			
		foreach ($dataKeys as $key => $aid) {
			if (!$this->overrideAccountIds && Billrun_Billrun::exists($aid, $billrun_key)) {
				unset($dataKeys[$key]);
				//$existingAccounts[$aid]  = $this->data[$aid];
			}
		}
		return $dataKeys;
	}
	
	/**
	 * execute aggregate
	 */
	public function aggregate() {
		$account_billrun = false;
		$billrun_key = $this->getStamp();
		$billruns_count = 0;
		$skipped_billruns_count = 0;
		if ($this->bulkAccountPreload) {
			Billrun_Factory::log('loading accounts that will be needed to be preloaded...', Zend_Log::INFO);
			$dataKeys = array_keys($this->data);
			//$existingAccounts = array();			
			foreach ($dataKeys as $key => $aid) {
				if (!$this->overrideAccountIds && Billrun_Billrun::exists($aid, $billrun_key)) {
					unset($dataKeys[$key]);
					//$existingAccounts[$aid]  = $this->data[$aid];
				}
			}
		}
		foreach ($this->data as $accid => $account) {
			Billrun_Factory::log("Aggregate loop");
			if ($this->memory_limit > -1 && memory_get_usage() > $this->memory_limit) {
				// TODO: Memory limit should not be here as magic number.
				Billrun_Factory::log('Customer aggregator memory limit of ' . $this->memory_limit / 1048576 . 'M has reached. Exiting (page: ' . $this->page . ', size: ' . $this->size . ').', Zend_Log::ALERT);
				break;
			}
			//pre-load  account lines 
			if ($this->bulkAccountPreload && !($billruns_count % $this->bulkAccountPreload) && count($dataKeys) > $billruns_count) {
				$aidsToLoad = array_slice($dataKeys, $billruns_count, $this->bulkAccountPreload);
				Billrun_Billrun::preloadAccountsLines($aidsToLoad, $billrun_key);
			}
			Billrun_Factory::dispatcher()->trigger('beforeAggregateAccount', array($accid, $account, &$this));
			Billrun_Factory::log('Current account index: ' . ++$billruns_count, Zend_Log::INFO);
//			if (!Billrun_Factory::config()->isProd()) {
//				if ($this->testAcc && is_array($this->testAcc) && !in_array($accid, $this->testAcc)) {//TODO : remove this??
//					//Billrun_Factory::log(" Moving on nothing to see here... , account Id : $accid");
//					continue;
//				}
//			}

			if (!$this->overrideAccountIds && Billrun_Billrun::exists($accid, $billrun_key)) {
				Billrun_Factory::log("Billrun " . $billrun_key . " already exists for account " . $accid, Zend_Log::ALERT);
				$skipped_billruns_count++;
				continue;
			}
			$params = array(
				'aid' => $accid,
				'billrun_key' => $billrun_key,
				'autoload' => !empty($this->overrideAccountIds),
			);
			$account_billrun = Billrun_Factory::billrun($params);
			if ($this->overrideAccountIds) {
				$account_billrun->resetBillrun();
			}
			
			$this->addAccountFieldsToBillrun($account_billrun, $account);
			
			$manual_lines = array();
			$deactivated_subscribers = array();
			foreach ($account['subscribers'] as $subscriber) {
				/* @var $subscriber Billrun_Subscriber */
				Billrun_Factory::dispatcher()->trigger('beforeAggregateSubscriber', array($subscriber, $account_billrun, &$this));
				$sid = $subscriber->getId();
				if ($account_billrun->subscriberExists($sid)) {
					Billrun_Factory::log("Billrun " . $billrun_key . " already exists for subscriber " . $sid, Zend_Log::ALERT);
					continue;
				}
				$next_plan_name = $subscriber->getNextPlanName();
				if (is_null($next_plan_name)) {
					$subscriber_status = "closed";
					$currentPlans = $subscriber->getCurrentPlans();
					if (empty($currentPlans)) {
						Billrun_Factory::log("Subscriber " . $sid . " has current plan null and next plan null", Zend_Log::INFO);
						$deactivated_subscribers[] = array("sid" => $sid);
					}
				} else {
					$subscriber_status = "open";
				}
				foreach ($deactivated_subscribers as $value) {
					
				}
				$manual_lines = array_merge($manual_lines, $this->subscriberAggregator->aggregate($subscriber, $billrun_key));
				$account_billrun->addSubscriber($subscriber, $subscriber_status);
				Billrun_Factory::dispatcher()->trigger('afterAggregateSubscriber', array($subscriber, $account_billrun, &$this));
			}
			$lines = $account_billrun->addLines($manual_lines, $deactivated_subscribers);

			$account_billrun->filter_disconected_subscribers($deactivated_subscribers);

			//save the billrun
			if ($account_billrun->is_deactivated() === true) {
				Billrun_Factory::log('deactivated account, no need for invoice ' . $accid, Zend_Log::DEBUG);
				continue;
			}
			Billrun_Factory::log('Saving account ' . $accid, Zend_Log::DEBUG);
			if ($account_billrun->save() === false) {
				Billrun_Factory::log('Error saving account ' . $accid, Zend_Log::ALERT);
				continue;
			}
			$this->successfulAccounts[] = $accid;
			Billrun_Factory::log('Finished saving account ' . $accid, Zend_Log::DEBUG);

			Billrun_Factory::dispatcher()->trigger('aggregateBeforeCloseAccountBillrun', array($accid, $account, $account_billrun, $lines, &$this));
			Billrun_Factory::log("Closing billrun $billrun_key for account $accid", Zend_Log::DEBUG);
			$account_billrun->close($this->min_invoice_id);
			Billrun_Factory::log("Finished closing billrun $billrun_key for account $accid", Zend_Log::DEBUG);
			Billrun_Factory::dispatcher()->trigger('afterAggregateAccount', array($accid, $account, $account_billrun, $lines, &$this));
			if ($this->bulkAccountPreload) {
				Billrun_Billrun::clearPreLoadedLines(array($accid));
			}
		}
		if ($billruns_count == count($this->data)) {
			$end_msg = "Finished iterating page $this->page of size $this->size. Memory usage is " . memory_get_usage() / 1048576 . " MB\n";
			$end_msg .="Processed " . ($billruns_count - $skipped_billruns_count) . " accounts, Skipped over {$skipped_billruns_count} accounts, out of a total of {$billruns_count} accounts";
			Billrun_Factory::log($end_msg, Zend_Log::INFO);
			$this->sendEndMail($end_msg);
		}

		// @TODO trigger after aggregate
		if (!$this->recreateInvoices){
			$cycleQuery = array('billrun_key' => $billrun_key, 'page_number' => $this->page, 'page_size' => $this->size);
			$cycleUpdate = array('$set' => array('end_time' => new MongoDate()));
			$this->billingCycle->update($cycleQuery, $cycleUpdate);
		}
		Billrun_Factory::dispatcher()->trigger('afterAggregate', array($this->data, &$this));
		return $this->successfulAccounts;
	}

	protected function sendEndMail($msg) {
		$recipients = Billrun_Factory::config()->getConfigValue('log.email.writerParams.to');
		if ($recipients) {
			Billrun_Util::sendMail("BillRun customer aggregate page finished", $msg, $recipients);
		}
	}
	
	/**
	 * Aggregate mongo with a query
	 * @param Billrun_DataTypes_CycleTime $cycle - Current cycle time
	 * @param int $page - page
	 * @param int $size - size
	 * @param int $aid - Account id, null by deafault
	 * @return array 
	 */
	public function aggregateMongo($cycle, $page, $size, $aid = null) {
		$mongoCycle = new Billrun_DataTypes_MongoCycleTime($cycle);
		if ($aid) {
			$page = 0;
			$size = 1;
		}
		$pipelines[] = $this->getMatchPiepline($mongoCycle);
		if ($aid) {
			$pipelines[count($pipelines) - 1]['$match']['aid'] = intval($aid);
		}
		$pipelines[] = $this->getSortPipeline();
		
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
		$pipelines[] = array(
			'$project' => array(
				'_id' => 0,
				'id' => '$_id',
				'plan_dates' => 1,
				'card_token' => 1,
			)
		);
		$coll = Billrun_Factory::db()->subscribersCollection();
		$results = iterator_to_array($coll->aggregate($pipelines));
		
		if (!is_array($results) || empty($results) ||
			(isset($results['success']) && ($results['success'] === FALSE))) {
			return array();
		} 
		return $results;
	}
	
	protected function getMatchPiepline($mongoCycle) {
		return array(
			'$match' => array(
				'$or' => array(
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
					),
					array( // Account records
						'type' => 'account',
						'from' => array(
							'$lte' => $mongoCycle->end(),
						),
						'to' => array(
							'$gte' => $mongoCycle->start(),
						),
					),
				)
			)
		);
	}
	
	protected function getSortPipeline() {
		return array(
			'$sort' => array(
				'aid' => 1,
				'sid' => 1,
				'plan' => 1,
				'from' => 1,
			),
		);
	}
	
	/**
	 * Creates and saves a flat line to the db
	 * @param Billrun_Subscriber $subscriber the subscriber to create a flat line to
	 * @param string $billrun_key the billrun for which to add the flat line
	 * @return array the inserted line or the old one if it already exists
	 * @deprecated since version 5.1
	 */
	protected function saveFlatLines($subscriber, $billrun_key) {
		$flatEntries = $subscriber->getFlatEntries($billrun_key, true);
		try {
			if ($flatEntries) {
				$flatEntriesRaw = array_map(function($obj) {
					return $obj->getRawData();
				}, $flatEntries);
				$ret = $this->lines->batchInsert($flatEntriesRaw, array("w" => 1));
				if (empty($ret['ok']) || empty($ret['nInserted']) || $ret['nInserted'] != count($flatEntries)) {
					Billrun_Factory::log('Error when trying to insert ' . count($flatEntries) . ' flat entries for subscriber ' . $subscriber->sid . '. Details: ' . print_r($ret, 1), Zend_Log::ALERT);
				}
			}
		} catch (Exception $e) {
			if ($e->getCode() == Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR) {
				Billrun_Factory::log("Flat line already exists for subscriber " . $subscriber->sid . " for billrun " . $billrun_key, Zend_Log::ALERT);
			} else {
				Billrun_Factory::log("Problem inserting flat lines for subscriber " . $subscriber->sid . " for billrun " . $billrun_key . ". error message: " . $e->getMessage() . ". error code: " . $e->getCode(), Zend_Log::ALERT);
				Billrun_Util::logFailedCreditRow($flatEntries);
			}
		}
		return $flatEntries;
	}

	/**
	 * create and save service lines
	 * @param type $subscriber
	 * @param type $billrun_key
	 * @return array of inserted lines
	 * @deprecated since version 5.1

	 */
	protected function saveServiceLines($subscriber, $billrun_key) {
		$services = $subscriber->getServices($billrun_key, true);
		$ret = array();
		foreach ($services as $service) {
			$rawData = $service->getRawData();
			try {
				$this->lines->insert($rawData, array("w" => 1));
			} catch (Exception $e) {
				if ($e->getCode() == Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR) {
					Billrun_Factory::log("Service already exists for subscriber " . $subscriber->sid . " for billrun " . $billrun_key . " service details: " . print_R($rawData, 1), Zend_Log::ALERT);
				} else {
					Billrun_Factory::log("Problem inserting service for subscriber " . $subscriber->sid . " for billrun " . $billrun_key
						. ". error message: " . $e->getMessage() . ". error code: " . $e->getCode() . ". service details:" . print_R($rawData, 1), Zend_Log::ALERT);
					Billrun_Util::logFailedServiceRow($rawData);
				}
			}
			$ret[$service['stamp']] = $service;
		}
		return $ret;
	}

	/**
	 * create and save credit lines
	 * @param type $subscriber
	 * @param type $billrun_key
	 * @return array of inserted lines
	 * @deprecated since version 5.1
	 */
	protected function saveCreditLines($subscriber, $billrun_key) {
		$credits = $subscriber->getCredits($billrun_key, true);
		$ret = array();
		foreach ($credits as $credit) {
			$rawData = $credit->getRawData();
			try {
				$this->lines->insert($rawData, array("w" => 1));
			} catch (Exception $e) {
				if ($e->getCode() == Mongodloid_General::DUPLICATE_UNIQUE_INDEX_ERROR) {
					Billrun_Factory::log("Credit already exists for subscriber " . $subscriber->sid . " for billrun " . $billrun_key . " credit details: " . print_R($rawData, 1), Zend_Log::ALERT);
				} else {
					Billrun_Factory::log("Problem inserting credit for subscriber " . $subscriber->sid . " for billrun " . $billrun_key
						. ". error message: " . $e->getMessage() . ". error code: " . $e->getCode() . ". credit details:" . print_R($rawData, 1), Zend_Log::ALERT);
					Billrun_Util::logFailedCreditRow($rawData);
				}
			}
			$ret[$credit['stamp']] = $credit;
		}
		return $ret;
	}

	protected function saveCredit($credit, $billrun_key) {
		return $insertRow;
	}

	protected function save($data) {
		
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
	 * Load all rates from db into memory
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query()->cursor();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			$this->rates[strval($rate->getId())] = $rate;
		}
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
		$billrun->populateBillrunWithAccountData($account, $options);
	}

}
