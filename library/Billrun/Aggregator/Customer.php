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
	protected $page = 1;

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

	public function __construct($options = array()) {
		parent::__construct($options);

		ini_set('mongo.native_long', 1); //Set mongo  to use  long int  for  all aggregated integer data.

		if (isset($options['aggregator']['page']) && is_numeric($options['aggregator']['page'])) {
			$this->page = $options['aggregator']['page'];
		}
		if (isset($options['page']) && is_numeric($options['page'])) {
			$this->page = $options['page'];
		}
		if (isset($options['aggregator']['size']) && $options['aggregator']['size']) {
			$this->size = $options['aggregator']['size'];
		}
		if (isset($options['size']) && $options['size']) {
			$this->size = $options['size'];
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

		$this->plans = Billrun_Factory::db()->plansCollection();
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun = Billrun_Factory::db()->billrunCollection();

		$this->loadRates();
	}

	/**
	 * load the data to aggregate
	 */
	public function load() {
		$billrun_key = $this->getStamp();
		$startTime = Billrun_Billrun::getStartTime($billrun_key);
		$endTime = Billrun_Billrun::getEndTime($billrun_key);
		$subscriber = Billrun_Factory::subscriber();
		Billrun_Factory::log("Loading page " . $this->page . " of size " . $this->size, Zend_Log::INFO);
		if ($this->overrideAccountIds) {
			$this->data = array();
			foreach ($this->overrideAccountIds as $account_id) {
				$this->data = $this->data + $subscriber->getList($startTime, $endTime, 0, 1, $account_id);
			}
		} else {
			$this->data = $subscriber->getList($startTime, $endTime, $this->page, $this->size);
		}

		Billrun_Factory::log("aggregator entities loaded: " . count($this->data), Zend_Log::INFO);

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
			
			$account_billrun->setBillrunAccountFields($account);
			
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
				$manual_lines = array_merge($manual_lines, $this->saveFlatLines($subscriber, $billrun_key));
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
	
	protected function addAccountFieldsToBillrun($billrun, $account) {
		$options = empty($account['options']) ? array() : $this->getOptionEntries($billrun, $account);
		$billrun->populateBillrunWithAccountData($account,$options);
	}

}
