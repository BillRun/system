<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing aggregator class for Golan customers records
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
	protected $billing_cycle = null;

	/**
	 *
	 * @var Mongodloid_Collection
	 */
	protected $billrun = null;

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
	
	protected $recreate_invoices = null;
	

	public function __construct($options = array()) {
		parent::__construct($options);

		ini_set('mongo.native_long', 1); //Set mongo  to use  long int  for  all aggregated integer data.
		
		if (isset($options['aggregator']['recreate_invoices']) && $options['aggregator']['recreate_invoices']) {
			$this->recreate_invoices = $options['aggregator']['recreate_invoices'];
		}
		if (isset($options['aggregator']['page'])) {
			$this->page = (int)$options['aggregator']['page'];
		}
		if (isset($options['stamp']) && $options['stamp']) {
			$this->stamp = $options['stamp'];
		} else if (isset($options['aggregator']['stamp']) && (Billrun_Util::isBillrunKey($options['aggregator']['stamp']))) {
				$this->stamp = $options['aggregator']['stamp'];
		} else {
			$next_billrun_key = Billrun_Util::getBillrunKey(time());
			$current_billrun_key = Billrun_Util::getPreviousBillrunKey($next_billrun_key);
			$this->stamp = $current_billrun_key;
		}
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
		
		$this->billing_cycle = Billrun_Factory::db()->billing_cycleCollection();
		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun = Billrun_Factory::db(array('name' => 'billrun'))->billrunCollection();

		$this->loadRates();
		
		if (!$this->recreate_invoices){
			if (($this->page = $this->getPage()) === FALSE) {
				 throw new Exception('Failed getting next page');
			}
		}
	}

	/**
	 * load the data to aggregate
	 */
	public function load() {
		$billrun_key = $this->getStamp();
		$date = date(Billrun_Base::base_dateformat, Billrun_Util::getEndTime($billrun_key));
		$subscriber = Billrun_Factory::subscriber();
		$subscriber->setBillrunKey($billrun_key);
		Billrun_Factory::log()->log("Loading page " . $this->page . " of size " . $this->size, Zend_Log::INFO);
		if ($this->overrideAccountIds) {
			$this->data = array();
			foreach ($this->overrideAccountIds as $account_id) {
				$this->data = $this->data + $subscriber->getList(0, 1, $date, $account_id);
			}
		} else {
			$this->data = $subscriber->getList($this->page, $this->size, $date);
		}
		if (!$this->recreate_invoices){
			$this->billing_cycle->update(array('billrun_key' => $billrun_key, 'page_number' => $this->page, 'page_size' => $this->size), array('$set' => array('count' => count($this->data))));
		}
		Billrun_Factory::log()->log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);

		Billrun_Factory::dispatcher()->trigger('afterAggregatorLoadData', array('aggregator' => $this));
	}

	/**
	 * execute aggregate
	 */
	public function aggregate() {
		Billrun_Factory::dispatcher()->trigger('beforeAggregate', array($this->data, &$this));
		$account_billrun = false;
		$billrun_key = $this->getStamp();
		$billruns_count = 0;
		$skipped_billruns_count = 0;
		if ($this->bulkAccountPreload) {
			Billrun_Factory::log('loading accounts that will be needed to be preloaded...', Zend_log::INFO);
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
			if ($this->memory_limit > -1 && memory_get_usage() > $this->memory_limit) {
				Billrun_Factory::log('Customer aggregator memory limit of ' . $this->memory_limit / 1048576 . 'M has reached. Exiting (page: ' . $this->page . ', size: ' . $this->size . ').', Zend_log::ALERT);
				break;
			}
			//pre-load  account lines 
			if ($this->bulkAccountPreload && !($billruns_count % $this->bulkAccountPreload) && count($dataKeys) > $billruns_count) {
				$aidsToLoad = array_slice($dataKeys, $billruns_count, $this->bulkAccountPreload);
				Billrun_Billrun::preloadAccountsLines($aidsToLoad, $billrun_key);
			}
			Billrun_Factory::dispatcher()->trigger('beforeAggregateAccount', array($accid, $account, &$this));
			Billrun_Factory::log('Current account index: ' . ++$billruns_count, Zend_log::INFO);
//			if (!Billrun_Factory::config()->isProd()) {
//				if ($this->testAcc && is_array($this->testAcc) && !in_array($accid, $this->testAcc)) {//TODO : remove this??
//					//Billrun_Factory::log(" Moving on nothing to see here... , account Id : $accid");
//					continue;
//				}
//			}

			if (!$this->overrideAccountIds && Billrun_Billrun::exists($accid, $billrun_key)) {
				Billrun_Factory::log()->log("Billrun " . $billrun_key . " already exists for account " . $accid, Zend_Log::ALERT);
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
			$manual_lines = array();
			$deactivated_subscribers = array();
			foreach ($account as $subscriber) {
				Billrun_Factory::dispatcher()->trigger('beforeAggregateSubscriber', array($subscriber, $account_billrun, &$this));
				$sid = $subscriber->sid;
				if ($account_billrun->subscriberExists($sid)) {
					Billrun_Factory::log()->log("Billrun " . $billrun_key . " already exists for subscriber " . $sid, Zend_Log::ALERT);
					continue;
				}
				$offers = $subscriber->getPlans();
				$lastOffer = $this->getLastOffer($offers);
				if (!empty($lastOffer) && strtotime($lastOffer['end_date']) < Billrun_Util::getEndTime($billrun_key)) {
					$subscriber_status = "closed";
				}
				if ($sid == 0) {
					$subscriber->setBillrunKey($billrun_key);
					$offers = $this->getAccountPlan($subscriber);
				}
				if (is_null($offers)) {
					Billrun_Factory::log()->log("Subscriber " . $sid . " has current plan null and next plan null", Zend_Log::INFO);
					$subscriber_status = "closed";
					$deactivated_subscribers[] = array("sid" => $sid);
				} 
				
				if (!empty($offers)) {
					$subscriber_status = "open";
					$subscriber->setBillrunKey($billrun_key);
					foreach ($offers as $offer) {
						Billrun_Factory::log("Getting flat price for subscriber $sid", Zend_log::INFO);
						$subscriber->setPlanName($offer['plan']);
						$subscriber->setPlanId($offer['id']);
						$flat_price = isset($offer['offer_amount']) ? ($offer['offer_amount'] * $offer['fraction']) : $subscriber->getFlatPrice($offer['fraction']);
						Billrun_Factory::log("Finished getting flat price for subscriber $sid", Zend_log::INFO);
						if (is_null($flat_price)) {
							Billrun_Factory::log()->log("Couldn't find flat price for subscriber " . $sid . " for billrun " . $billrun_key, Zend_Log::ALERT);
							continue;
						}
						Billrun_Factory::log('Adding flat line to subscriber ' . $sid, Zend_Log::INFO);
						$flat = $this->saveFlatLine($subscriber, $billrun_key, $offer);
						$manual_lines = array_merge($manual_lines, array($flat['stamp'] => $flat));
						Billrun_Factory::log('Finished adding flat line to subscriber ' . $sid, Zend_Log::INFO);
					}
				}
				$manual_lines = array_merge($manual_lines, $this->saveCreditLines($subscriber, $billrun_key));
				$manual_lines = array_merge($manual_lines, $this->saveServiceLines($subscriber, $billrun_key));
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
			Billrun_Factory::log('Saving account ' . $accid, Zend_Log::INFO);
			if ($account_billrun->save() === false) {
				Billrun_Factory::log('Error saving account ' . $accid, Zend_Log::ALERT);
				continue;
			}
			$this->successfulAccounts[] = $accid;
			Billrun_Factory::log('Finished saving account ' . $accid, Zend_Log::INFO);

			Billrun_Factory::dispatcher()->trigger('aggregateBeforeCloseAccountBillrun', array($accid, $account, $account_billrun, $lines, &$this));
			Billrun_Factory::log("Closing billrun $billrun_key for account $accid", Zend_log::INFO);
			$account_billrun->close($this->min_invoice_id);
			Billrun_Factory::log("Finished closing billrun $billrun_key for account $accid", Zend_log::INFO);
			Billrun_Factory::dispatcher()->trigger('afterAggregateAccount', array($accid, $account, $account_billrun, $lines, &$this));
			if ($this->bulkAccountPreload) {
				Billrun_Billrun::clearPreLoadedLines(array($accid));
			}
		}
		if (!$this->recreate_invoices){
			$this->billing_cycle->update(array('billrun_key' => $billrun_key, 'page_number' => $this->page, 'page_size' => $this->size), array('$set' => array('end_time' => new MongoDate())));
		}
		if ($billruns_count == count($this->data)) {
			$end_msg = "Finished iterating page $this->page of size $this->size. Memory usage is " . memory_get_usage() / 1048576 . " MB\n";
			$end_msg .="Processed " . ($billruns_count - $skipped_billruns_count) . " accounts, Skipped over {$skipped_billruns_count} accounts, out of a total of {$billruns_count} accounts";
			Billrun_Factory::log($end_msg, Zend_log::INFO);
			$this->sendEndMail($end_msg);
		}

		// @TODO trigger after aggregate
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
	 * Creates and saves a flat line to the db
	 * @param Billrun_Subscriber $subscriber the subscriber to create a flat line to
	 * @param string $billrun_key the billrun for which to add the flat line
	 * @return array the inserted line or the old one if it already exists
	 */
	protected function saveFlatLine($subscriber, $billrun_key, $offer) {
		$flat_entry = $subscriber->getFlatEntry($billrun_key, true, $offer);
		try {
			$this->lines->insert($flat_entry->getRawData(), array("w" => 1));
		} catch (Exception $e) {
			if ($e->getCode() == 11000) {
				Billrun_Factory::log("Flat line already exists for subscriber " . $subscriber->sid . " for billrun " . $billrun_key, Zend_log::ALERT);
			} else {
				Billrun_Factory::log("Problem inserting flat line for subscriber " . $subscriber->sid . " for billrun " . $billrun_key . ". error message: " . $e->getMessage() . ". error code: " . $e->getCode(), Zend_log::ALERT);
				Billrun_Util::logFailedCreditRow($flat_entry->getRawData());
			}
		}
		return $flat_entry;
	}
	
	/**
	 * Creates and saves a freeze line to the db
	 * @param Billrun_Subscriber $subscriber the subscriber to create a freeze line to
	 * @param string $billrun_key the billrun for which to add the freeze line
	 * @return array the inserted line or the old one if it already exists
	 */
	protected function saveFreezeLine($subscriber, $billrun_key) {
		$freeze_entry = $subscriber->getFreezeEntry($billrun_key, true);
		try {
			$this->lines->insert($freeze_entry->getRawData(), array("w" => 1));
		} catch (Exception $e) {
			if ($e->getCode() == 11000) {
				Billrun_Factory::log("Freeze line already exists for subscriber " . $subscriber->sid . " for billrun " . $billrun_key, Zend_log::ALERT);
			} else {
				Billrun_Factory::log("Problem inserting freeze line for subscriber " . $subscriber->sid . " for billrun " . $billrun_key . ". error message: " . $e->getMessage() . ". error code: " . $e->getCode(), Zend_log::ALERT);
				Billrun_Util::logFailedCreditRow($freeze_entry->getRawData());
			}
		}
		return $freeze_entry;
	}

	/**
	 * create and save service lines
	 * @param type $subscriber
	 * @param type $billrun_key
	 * @return array of inserted lines
	 */
	protected function saveServiceLines($subscriber, $billrun_key) {
		$services = $subscriber->getServices($billrun_key, true);
		$ret = array();
		foreach ($services as $service) {
			$rawData = $service->getRawData();
			try {
				$this->lines->insert($rawData, array("w" => 1));
			} catch (Exception $e) {
				if ($e->getCode() == 11000) {
					Billrun_Factory::log("Service already exists for subscriber " . $subscriber->sid . " for billrun " . $billrun_key . " service details: " . print_R($rawData, 1), Zend_log::ALERT);
				} else {
					Billrun_Factory::log("Problem inserting service for subscriber " . $subscriber->sid . " for billrun " . $billrun_key
						. ". error message: " . $e->getMessage() . ". error code: " . $e->getCode() . ". service details:" . print_R($rawData, 1), Zend_log::ALERT);
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
	 */
	protected function saveCreditLines($subscriber, $billrun_key) {
		$credits = $subscriber->getCredits($billrun_key, true);
		$ret = array();
		foreach ($credits as $credit) {
			$rawData = $credit->getRawData();
			try {
				$this->lines->insert($rawData, array("w" => 1));
			} catch (Exception $e) {
				if ($e->getCode() == 11000) {
					Billrun_Factory::log("Credit already exists for subscriber " . $subscriber->sid . " for billrun " . $billrun_key . " credit details: " . print_R($rawData, 1), Zend_log::ALERT);
				} else {
					Billrun_Factory::log("Problem inserting credit for subscriber " . $subscriber->sid . " for billrun " . $billrun_key
						. ". error message: " . $e->getMessage() . ". error code: " . $e->getCode() . ". credit details:" . print_R($rawData, 1), Zend_log::ALERT);
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
	
	
	public static function isBillingCycleOver($billing_cycle, $stamp, $size){
		$zero_pages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit', 1);
		if (empty($zero_pages) || !is_numeric($zero_pages)) {
			$zero_pages = 1;
		}
		if ($billing_cycle->query(array('billrun_key' => $stamp, 'page_size' => $size, 'count' => 0))->count() >= $zero_pages) {
			Billrun_Factory::log()->log("Finished going over all the pages", Zend_Log::DEBUG);
			return TRUE;
		}		
		return FALSE;
	}
	
	/**
	 * Finding which page is next in the biiling cycle
	 * @param the number of max tries to get the next page in the billing cycle
	 * @return number of the next page that should be taken
	 */
	protected function getPage($max_tries = 100) {

		if ($max_tries <= 0) { // 100 is arbitrary number and should be enough
			Billrun_Factory::log()->log("Failed getting next page", Zend_Log::ALERT);
			return FALSE;
		}
		$host = gethostname();
		if ($this->isBillingCycleOver($this->billing_cycle, $this->stamp, $this->size) === TRUE){
			 return FALSE;
		}
		$max_num_processes = Billrun_Factory::config()->getConfigValue('customer.aggregator.processes_per_host_limit');
		if ($this->billing_cycle->query(array('billrun_key' => $this->stamp, 'page_size' => $this->size, 'host' => $host,'end_time' => array('$exists' => false)))->count() >= $max_num_processes) {
			Billrun_Factory::log()->log("Host ". $host. " is already running max number of ". $max_num_processes . " processes", Zend_Log::DEBUG);
			return FALSE;
		}
		$current_document = $this->billing_cycle->query(array('billrun_key' => $this->stamp, 'page_size' => $this->size))->cursor()->sort(array('page_number' => -1))->limit(1)->current();
		if (is_null($current_document)) {
			return FALSE;
		}
		$current_page = $current_document['page_number'];
		if (isset($current_page)) {
			$next_page = $current_page + 1;
		} else {
			$next_page = 0; // first page
		}
		try {
			$check_exists = $this->billing_cycle->findAndModify(
				array('billrun_key' => $this->stamp, 'page_number' => $next_page, 'page_size' => $this->size), array('$setOnInsert' => array('billrun_key' => $this->stamp, 'page_number' => $next_page, 'page_size' => $this->size, 'host' => $host, 'start_time' => new MongoDate())), null, array(
				"upsert" => true
				)
			);
			if (!$check_exists->isEmpty()) {
				throw new Exception("Page number ". $next_page ." already exists.");
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log($e->getMessage() . " Trying Again...", Zend_Log::NOTICE);
			return $this->getPage($max_tries - 1);
		}
		return $next_page;
	}
	
	protected function getLastOffer($offers) {
		if (empty($offers)) {
			return array();
		}
		$lastOffer = array();
		foreach ($offers as $offer) {
			if (empty($lastOffer)) {
				$lastOffer = $offer;
			}
			if ($offer['end_date'] > $lastOffer['end_date']) {
				$lastOffer = $offer;
			}
		}
		return $lastOffer;
	}
	
	protected function getAccountPlan($subscriber) {
		return array(
			array(
				'plan' => 'ACCOUNT',
				'id' => 0,
				'fraction' => $subscriber->calcFractionOfMonth($subscriber->getActivationStartDay(), $subscriber->getActivationEndDay(), $subscriber->sid),
			)
		);
	}

}
