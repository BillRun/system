<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregated account's billrun container
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Account_Billrun {
	
	protected $aid;
	protected $key;
	
	/**
	 *
	 * @var Mongodloid_Entity
	 */
	protected $data;
	
	/**
	 * lines collection
	 * @var Mongodloid_Collection 
	 */
	protected $lines = null;

	/**
	 * billrun collection
	 * @var Mongodloid_Collection 
	 */
	protected $billrun_coll = null;

	/**
	 * True if the account already exists in billrun
	 * @var boolean
	 */
	protected $exists = false;
	
	protected $rates = array();
	
	/**
	 * 
	 * @param type $options
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function __construct($options = array()) {
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun_coll = Billrun_Factory::db()->billrunCollection();
		$this->constructByOptions($options);
		$this->populateBillrunWithAccountData($options['attributes']);
	}

	/**
	 * Construct the billrun with the input options
	 * @param array $options
	 */
	protected function constructByOptions($options) {
		if (!isset($options['aid'],$options['billrun_key'], $options['rates'])) {
			Billrun_Factory::log("Returning an empty billrun!", Zend_Log::NOTICE);
			return;
		}
		
		$this->aid = $options['aid'];
		$this->key = $options['billrun_key'];
		$this->rates = &$options['rates'];
		$force = (isset($options['autoload']) && $options['autoload']);
		$this->load($force);
	}
	
	/**
	 * Return true if the account exists in the billrun.
	 * @return boolean true if exists.
	 */
	public function exists() {
		return $this->exists;
	}
	
	/**
	 * Load the billrun object, if already exists change the internal indication.
	 * @param boolean $force - If true, force the load.
	 */
	protected function load($force) {
		$this->loadData();
		if (!$this->data->isEmpty()) {
			$this->exists = !$force;
			return;
		}
		
		$this->resetBillrun();
	}
	
	protected function getVat() {
		return Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18);
	}
	
	/**
	 * Updates the billrun object to match the db
	 * @return Billrun_Billrun
	 */
	protected function loadData() {
		$query = array(
					'aid' => $this->aid,
					'billrun_key' => $this->key);
		$cursor = $this->billrun_coll->query($query)->cursor(); 
		$this->data = $cursor->limit(1)->current();
		return $this;
	}

	/**
	 * Save the billrun to the db
	 * @param type $param
	 * @return type
	 */
	public function save() {
		if (!isset($this->data)) {
			// TODO: Report error?
			return false;
		}

		try {
			// TODO: Check if save returns false?
			$this->billrun_coll->save($this->data, 1);
			return true;
		} catch (Exception $ex) {
			// TODO: Wrap this exception with a billrun type exception.
			throw $ex;
		}

		return false;
	}

	/**
	 * Add a subscriber to the current billrun entry.
	 * @param Billrun_Cycle_Subscriber $subscriber Subscriber to add.
	 */
	public function addSubscriber($subscriber, $subData) {
		$subscriberEntry = $subData;
		$subscriberEntry['subscriber_status'] = $subscriber->getStatus();
		$rawData = $this->data->getRawData();
		if(!isset($rawData['subs'])) {
			$rawData['subs'] = array();
		}
		$rawData['subs'][] = $subscriberEntry;
		$this->data->setRawData($rawData);
	}
	
	protected function addClosedSubscriber($sid, $aid) {
		Billrun_Factory::log("Subscriber $sid is not active, yet has lines", Zend_Log::ALERT);
		$subscriber = array(
			'aid' => $aid, 
			'sid' => $sid, 
			'plan' => null, 
			'next_plan' => null,
			'subscriber_status' => 'closed'
		);
		$rawData = $this->data->getRawData();
		$rawData['subs'][] = $subscriber;
		$this->data->setRawData($rawData);
	}

	/**
	 * Get an empty billrun account entry structure.
	 * @param int $aid the account id of the billrun document
	 * @param string $billrun_key the billrun key of the billrun document
	 * @return array an empty billrun document
	 */
	public function getAccountEmptyBillrunEntry($aid, $billrun_key) {
		$vat = $this->getVat();
		return array(
			'aid' => $aid,
			'subs' => array(
			),
			'vat' => $vat,
			'billrun_key' => $billrun_key,
		);
	}

	public function getBillrunKey() {
		return $this->key;
	}

	/**
	 * Closes the billrun in the db by creating a unique invoice id
	 * @param int $min_id minimum invoice id to start from
	 */
	public function close($min_id) {
		if(!$this->data['subs']) {
			Billrun_Factory::log("Deactivated account: " . $this->aid, Zend_Log::INFO);
			return;
		}
		
		$billrun_entity = $this->getRawData();
		$ret = $this->billrun_coll->createAutoIncForEntity($billrun_entity, "invoice_id", $min_id);
		$this->billrun_coll->save($billrun_entity);
		if (is_null($ret)) {
			Billrun_Factory::log("Failed to create invoice for account " . $this->aid, Zend_Log::INFO);
		} else {
			Billrun_Factory::log("Created invoice " . $ret . " for account " . $this->aid, Zend_Log::INFO);
		}
	}

	/**
	 * Gets a subscriber entry from the current billrun
	 * @param int $sid the subscriber id
	 * @return mixed the subscriber entry (array) or false if the subscriber does not exists in the billrun
	 */
	protected function getSubRawData($sid) {
		foreach ($this->data['subs'] as $sub_entry) {
			if ($sub_entry['sid'] == $sid) {
				return $sub_entry;
			}
		}
		return false;
	}

	/**
	 * Updates a subscriber entry in the current billrun
	 * @param array $rawData the subscriber entry to update to the billrun
	 * @return boolean TRUE when the subscriber entry was found and updated, FALSE otherwise
	 */
	protected function setSubRawData($rawData) {
		$data = $this->data->getRawData();
		foreach ($data['subs'] as &$sub_entry) {
			if ($sub_entry['sid'] == $rawData['sid']) {
				$sub_entry = $rawData;
				$this->data->setRawData($data, false);
				return true;
			}
		}
		return false;
	}

	/**
	 * Gets the current billrun document raw data
	 * @return Mongodloid_Entity
	 */
	public function getRawData() {
		return $this->data;
	}

	/**
	 * Returns the breakdown key for the row
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	
	 * @return breakdown key
	 */
	protected function getBreakdownKey($row) {
		if (in_array($row['type'], array('flat', 'service'))) {
			return $row['type'];
		}
		
		$fileTypes = Billrun_Factory::config()->getFileTypes();
		if (in_array($row['type'], $fileTypes)) {
			return 'usage';
		} 
		
		Billrun_Factory::log("Cannot get type for line. Details: " . print_R($row, 1), Zend_Log::ALERT);
		return FALSE;
	}

	/**
	 * Updates the billrun costs, lines & breakdown with the input line if the line is not already included in it
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the input line
	 * @param boolean $vatable is the line vatable or not
	 */
	public function updateBillrun($counters, $pricingData, $row, $vatable) {
		if(!isset($row['sid'])) {
			Billrun_Factory::log("Invalid line:");
			Billrun_Factory::log(print_r($row,1));
			return;
		}
		
		$sid = $row['sid'];

		$sraw = $this->getSubRawData($sid);
		
		// it could be that this sid hasn't been returned on active_subscribers...
		if (!$sraw) {
			$this->addClosedSubscriber($sid, $row['aid']);
			$this->updateBillrun($counters, $pricingData, $row, $vatable);
			return;
		}
		
		$this->addLineToSubscriber($counters, $row, $pricingData, $vatable, $sraw);
		$this->updateCosts($pricingData, $row, $vatable, $sraw);
		$this->setSubRawData($sraw);
	}

	/**
	 * Updates the billrun costs
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param boolean $vatable is the row vatable
	 * @param array $sraw the subscriber entry raw data
	 */
	protected function updateCosts($pricingData, $row, $vatable, &$sraw) {
		$vat_key = ($vatable ? "vatable" : "vat_free");
		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			if (!isset($sraw['costs']['over_plan'][$vat_key])) {
				$sraw['costs']['over_plan'][$vat_key] = $pricingData['aprice'];
			} else {
				$sraw['costs']['over_plan'][$vat_key] += $pricingData['aprice'];
			}
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			if (!isset($sraw['costs']['out_plan'][$vat_key])) {
				$sraw['costs']['out_plan'][$vat_key] = $pricingData['aprice'];
			} else {
				$sraw['costs']['out_plan'][$vat_key] += $pricingData['aprice'];
			}
		} else if ($row['type'] == 'flat') {
			if (!isset($sraw['costs']['flat'][$vat_key])) {
				$sraw['costs']['flat'][$vat_key] = $pricingData['aprice'];
			} else {
				$sraw['costs']['flat'][$vat_key] += $pricingData['aprice'];
			}
		} else if ($row['type'] == 'credit') {
			if (!isset($sraw['costs']['credit'][$row['credit_type']][$vat_key])) {
				$sraw['costs']['credit'][$row['credit_type']][$vat_key] = $pricingData['aprice'];
			} else {
				$sraw['costs']['credit'][$row['credit_type']][$vat_key] += $pricingData['aprice'];
			}
		} else if ($row['type'] == 'service') {
			if (!isset($sraw['costs']['service'][$vat_key])) {
				$sraw['costs']['service'][$vat_key] = $pricingData['aprice'];
			} else {
				$sraw['costs']['service'][$vat_key] += $pricingData['aprice'];
			}
		} else {
			Billrun_Factory::log("Updating unknown type: " . $row['type']);
		}
	}

	/**
	 * Add pricing and usage counters to a non credit record subscriber.
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param boolean $vatable is the line vatable or not
	 * @param string $billrun_key the billrun_key of the billrun
	 */
	protected function addLineToNonCreditSubscriber($counters, $row, $pricingData, $vatable, &$sraw, $zone, $plan_key, $category_key, $zone_key) {
		if (!empty($counters)) {
			if (!(isset($pricingData['over_plan']) && $pricingData['over_plan'] < current($counters))) { // volume is partially priced (in & over plan)
				$volume_priced = current($counters);
			} else {
				$volume_priced = $pricingData['over_plan'];
				$planZone = &$sraw['breakdown']['in_plan'][$category_key][$zone_key];
				$planZone['totals'][key($counters)]['usagev'] = Billrun_Util::getFieldVal($planZone['totals'][key($counters)]['usagev'], 0) + current($counters) - $volume_priced; // add partial usage to flat
				$planZone['totals'][key($counters)]['cost'] = Billrun_Util::getFieldVal($planZone['totals'][key($counters)]['cost'], 0);
				$planZone['totals'][key($counters)]['count'] = Billrun_Util::getFieldVal($planZone['totals'][key($counters)]['count'], 0) + 1;
				$planZone['vat'] = ($vatable ? floatval($this->vat) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
			}

			$zone['totals'][key($counters)]['usagev'] = $this->getFieldVal($zone['totals'][key($counters)]['usagev'], 0) + $volume_priced;
			$zone['totals'][key($counters)]['cost'] = $this->getFieldVal($zone['totals'][key($counters)]['cost'], 0) + $pricingData['aprice'];
			$zone['totals'][key($counters)]['count'] = $this->getFieldVal($zone['totals'][key($counters)]['count'], 0) + 1;
			if ($row['type'] == 'ggsn') {
				// TODO: What is this magic number 06? There should just be a ggsn row class
				if (isset($row['rat_type']) && $row['rat_type'] == '06') {
					$data_generation = 'usage_4g';
				} else {
					$data_generation = 'usage_3g';
				}
				$zone['totals'][key($counters)][$data_generation]['usagev'] = $this->getFieldVal($zone['totals'][key($counters)]['usagev_' . $data_generation], 0) + $volume_priced;
				$zone['totals'][key($counters)][$data_generation]['cost'] = $this->getFieldVal($zone['totals'][key($counters)]['cost_' . $data_generation], 0) + $pricingData['aprice'];
				$zone['totals'][key($counters)][$data_generation]['count'] = $this->getFieldVal($zone['totals'][key($counters)]['count_' . $data_generation], 0) + 1;
			}
		}
		if ($plan_key != 'in_plan' || $zone_key == 'service') {
			$zone['cost'] = $this->getFieldVal($zone['cost'], 0) + $pricingData['aprice'];
		}
		$zone['vat'] = ($vatable ? floatval($this->vat) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
	}
	
	protected function updateBreakdown(&$sraw, $breakdownKey, $rate, $cost, $count) {
		if (!isset($sraw['breakdown'][$breakdownKey])) {
			$sraw['breakdown'][$breakdownKey] = array();
		}
		$rate_key = $rate['key'];
		foreach ($sraw['breakdown'][$breakdownKey] as &$breakdowns) {
			if ($breakdowns['name'] === $rate_key) {
				$breakdowns['cost'] += $cost;
				$breakdowns['count'] += $count;
				return;
			}
		}
		$sraw['breakdown'][$breakdownKey][] = array('name' => $rate_key, 'count' => $count, 'cost' => $cost);
	}

	/**
	 * Add pricing and usage counters to the subscriber billrun breakdown.
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param boolean $vatable is the line vatable or not
	 * @param string $billrun_key the billrun_key of the billrun
	 * @param array $sraw the subscriber's billrun entry
	 * @todo remove billrun_key parameter
	 */
	protected function addLineToSubscriber($counters, $row, $pricingData, $vatable, &$sraw) {
		if (!$breakdownKey = $this->getBreakdownKey($row)) {
			return;
		}
		$rate = $this->getRowRate($row);
		$this->updateBreakdown($sraw, $breakdownKey, $rate, $pricingData['aprice'], $row['usagev']);
		
		// TODO: apply arategroup to new billrun object
		if (isset($row['arategroup'])) {
			if (isset($row['in_plan'])) {
				$sraw['groups'][$row['arategroup']]['in_plan']['totals'][key($counters)]['usagev'] = Billrun_Util::getFieldVal($sraw['groups'][$row['arategroup']]['in_plan']['totals'][key($counters)]['usagev'], 0) + $row['in_plan'];
			}
			if (isset($row['over_plan'])) {
				$sraw['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['usagev'] = Billrun_Util::getFieldVal($sraw['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['usagev'], 0) + $row['over_plan'];
				$sraw['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['cost'] = Billrun_Util::getFieldVal($sraw['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['cost'], 0) + $row['aprice'];
			}
		}
		
		if (!isset($sraw['totals'][$breakdownKey])) {
			$sraw['totals'][$breakdownKey] = array();
		}

		if ($vatable) {
			$sraw['totals']['vatable'] = Billrun_Util::getFieldVal($sraw['totals']['vatable'], 0) + $pricingData['aprice'];
			$sraw['totals'][$breakdownKey]['vatable'] = Billrun_Util::getFieldVal($sraw['totals'][$breakdownKey]['vatable'], 0) + $pricingData['aprice'];
			$price_after_vat = $pricingData['aprice'] + ($pricingData['aprice'] * $this->getVat());
		} else {
			$price_after_vat = $pricingData['aprice'];
		}
		$sraw['totals']['before_vat'] = Billrun_Util::getFieldVal($sraw['totals']['before_vat'], 0) + $pricingData['aprice'];
		$sraw['totals']['after_vat'] = Billrun_Util::getFieldVal($sraw['totals']['after_vat'], 0) + $price_after_vat;
		$sraw['totals'][$breakdownKey]['before_vat'] = Billrun_Util::getFieldVal($sraw['totals'][$breakdownKey]['before_vat'], 0) + $pricingData['aprice'];
		$sraw['totals'][$breakdownKey]['after_vat'] = Billrun_Util::getFieldVal($sraw['totals'][$breakdownKey]['after_vat'], 0) + $price_after_vat;
	}

	/**
	 * Returns the plan flag (in / over / out / partial) for a given row day based on the previous flag
	 * @param type $row the row to get plan flag by
	 * @param type $current_flag the previous flag of the row day
	 * @return string the new plan flag for the row day after considering the input row plan flag
	 */
	protected function getDayPlanFlagByDataRow($row, $current_flag = 'in') {
		$levels = array(
			'in' => 0,
			'over' => 1,
			'partial' => 2,
			'out' => 3
		);
		if (isset($row['over_plan'])) {
			if (($row['usagev'] - $row['over_plan']) > 0) {
				$plan_flag = 'partial';
			} else {
				$plan_flag = 'over';
			}
		} else if (isset($row['out_plan'])) {
			$plan_flag = 'out';
		} else {
			$plan_flag = 'in';
		}
		if ($levels[$plan_flag] <= $levels[$current_flag]) {
			return $current_flag;
		}
		return $plan_flag;
	}

	/**
	 * Add pricing data to the account totals.
	 */
	public function updateTotals() {
		$rawData = $this->data->getRawData();
		/*

		  if ($vatable) {
		  $rawData['totals']['vatable'] = $pricingData['aprice'];
		  $vat = self::getVATByBillrunKey($billrun_key);
		  $price_after_vat = $pricingData['aprice'] + $pricingData['aprice'] * $vat;
		  } else {
		  $price_after_vat = $pricingData['aprice'];
		  }
		  $rawData['totals']['before_vat'] =  $this->getFieldVal($rawData,array('totals','before_vat'),0 ) + $pricingData['aprice'];
		  $rawData['totals']['after_vat'] =  $this->getFieldVal($rawData['totals'],array('after_vat'), 0) + $price_after_vat;
		  $rawData['totals']['vatable'] = $pricingData['aprice'];
		 */
		$newTotals = array('before_vat' => 0, 'after_vat' => 0, 'after_vat_rounded' => 0, 'vatable' => 0, 
			'flat' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0), 
			'service' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0), 
			'usage' => array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0)
		);
		foreach ($this->data['subs'] as $sub) {
			//Billrun_Factory::log(print_r($sub));
			$newTotals['before_vat'] += Billrun_Util::getFieldVal($sub['totals']['before_vat'], 0);
			$newTotals['after_vat'] += Billrun_Util::getFieldVal($sub['totals']['after_vat'], 0);
			$newTotals['after_vat_rounded'] = round($newTotals['after_vat'], 2);
			$newTotals['vatable'] += Billrun_Util::getFieldVal($sub['totals']['vatable'], 0);
			$newTotals['flat']['before_vat'] += Billrun_Util::getFieldVal($sub['totals']['flat']['before_vat'], 0);
			$newTotals['flat']['after_vat'] += Billrun_Util::getFieldVal($sub['totals']['flat']['after_vat'], 0);
			$newTotals['flat']['vatable'] += Billrun_Util::getFieldVal($sub['totals']['flat']['vatable'], 0);
			$newTotals['service']['before_vat'] += Billrun_Util::getFieldVal($sub['totals']['service']['before_vat'], 0);
			$newTotals['service']['after_vat'] += Billrun_Util::getFieldVal($sub['totals']['service']['after_vat'], 0);
			$newTotals['service']['vatable'] += Billrun_Util::getFieldVal($sub['totals']['service']['vatable'], 0);
			$newTotals['usage']['before_vat'] += Billrun_Util::getFieldVal($sub['totals']['usage']['before_vat'], 0);
			$newTotals['usage']['after_vat'] += Billrun_Util::getFieldVal($sub['totals']['usage']['after_vat'], 0);
			$newTotals['usage']['vatable'] += Billrun_Util::getFieldVal($sub['totals']['usage']['vatable'], 0);
		}
		$rawData['totals'] = $newTotals;
		$this->data->setRawData($rawData);
	}

	/**
	 * HACK TO MAKE THE BILLLRUN FASTER
	 * Get a rate from the row
	 * @param Mongodloid_Entity the row to get rate from
	 * @return Mongodloid_Entity the rate of the row
	 */
	protected function getRowRate($row) {
		if(!isset($row['arate'])) {
			return null;
		}
		
		$raw_rate = $row['arate'];
		$id_str = strval($raw_rate['$id']);
		if(!isset($this->rates[$id_str])) {
			return null;
		}
		return $this->rates[$id_str];
	}

	/**
	 * Add all lines of the account to the billrun object
	 * @param array $aggregated array of aggregated subscribers.
	 * @return array the stamps of the lines used to create the billrun
	 */
	public function addLines($aggregated) {
		Billrun_Factory::log("Querying account " . $this->aid . " for lines...", Zend_Log::DEBUG);
		$accountLines = $this->loadAccountLines();

		$lines = array_merge($accountLines, $aggregated);
		Billrun_Factory::log("Processing account Lines $this->aid" . " lines: " . count($lines), Zend_Log::DEBUG);

		$updatedLines = $this->processLines(array_values($lines));
		Billrun_Factory::log("Finished processing account $this->aid lines. Total: " . count($updatedLines), Zend_Log::DEBUG);
		$this->updateTotals();
		return $updatedLines;
	}

	protected function processLines($account_lines) {
		$updatedLines = array();
		foreach ($account_lines as $line) {
			
			// the check fix 2 issues:
			// 1. temporary fix for https://jira.mongodb.org/browse/SERVER-9858
			// 2. avoid duplicate lines
			if (isset($updatedLines[$line['stamp']])) {
				continue;
			}
			$pricingData = array('aprice' => $line['aprice']);
			if (isset($line['over_plan'])) {
				$pricingData['over_plan'] = $line['over_plan'];
			} else if (isset($line['out_plan'])) {
				$pricingData['out_plan'] = $line['out_plan'];
			}

			if ($line['type'] == 'flat') {
				if(!$this->processFlatLine($line)) {
					continue;
				}
			} else {
				$rate = $this->getRowRate($line);
				$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
				$this->updateBillrun(array($line['usaget'] => $line['usagev']), $pricingData, $line, $vatable);
			} 
			
			//Billrun_Factory::log("Done Processing account Line for $sid : ".  microtime(true));
			$updatedLines[$line['stamp']] = $line;
		}
		return $updatedLines;
	}

	protected function getPricingData($line) {
		$pricingData = array('aprice' => $line['aprice']);
		if (isset($line['over_plan'])) {
			$pricingData['over_plan'] = $line['over_plan'];
		} else if (isset($line['out_plan'])) {
			$pricingData['out_plan'] = $line['out_plan'];
		}
	}
	
	/**
	 * Process a flat line
	 * @param arary $line
	 * @return boolean true if successful.
	 */
	protected function processFlatLine($line) {
		$vatable = isset($line['vatable']);
		$this->updateBillrun(array(), array('aprice' => $line['aprice']), $line, !$vatable);
		return true;
	}
	
	/**
	 * removes deactivated accounts from the list if they still have lines (and therfore should be in the billrun)
	 * @param $deactivated_subscribers array of subscribers sids and their deactivation date
	 */
	protected function filterSubscribers($account_lines, &$deactivated_subscribers) {
		if (empty($deactivated_subscribers) || empty($account_lines)) {
			return;
		}
		foreach ($account_lines as $line) {
			foreach ($deactivated_subscribers as $key => $ds) {
				if ($ds['sid'] == $line['sid']) {
					Billrun_Factory::log("Subscriber " . $ds['sid'] . " has current plan null and next plan null, yet has lines", Zend_Log::NOTICE);
					unset($deactivated_subscribers[$key]);
				}
			}
		}
	}

	/**
	 * Gets all the account lines for this billrun from the db
	 * @return an array containing all the  accounts with thier lines.
	 */
	public function loadAccountLines() {
		$ret = array();
		$query = array(
			'aid' => $this->aid,
			'billrun' => $this->key
		);

		$requiredFields = array('aid' => 1);
		$filter_fields = Billrun_Factory::config()->getConfigValue('billrun.filter_fields', array());

		$sort = array(
			'urt' => 1,
		);

		Billrun_Factory::log('Querying for account ' . $this->aid . ' lines', Zend_Log::DEBUG);
		$addCount = $bufferCount = 0;
		$linesCol = Billrun_Factory::db()->linesCollection();
		$fields = array_merge($filter_fields, $requiredFields);
		$limit = Billrun_Factory::config()->getConfigValue('billrun.linesLimit', 10000);

		do {
			$bufferCount += $addCount;
			$cursor = $linesCol->query($query)->cursor()->fields($fields)
					->sort($sort)->skip($bufferCount)->limit($limit);
			foreach ($cursor as $line) {
				$ret[$line['stamp']] = $line;
			}
		} while (($addCount = $cursor->count(true)) > 0);
		Billrun_Factory::log('Finished querying for account ' . $this->aid . ' lines: ' . count($ret), Zend_Log::DEBUG);
		
		return $ret;
	}

	/**
	 * Resets the billrun data. If an invoice id exists, it will be kept.
	 */
	public function resetBillrun() {
		$this->exists = false;
		$empty_billrun_entry = $this->getAccountEmptyBillrunEntry($this->aid, $this->key);
		$invoice_id_field = (isset($this->data['invoice_id']) ? array('invoice_id' => $this->data['invoice_id']) : array());
		$id_field = (isset($this->data['_id']) ? array('_id' => $this->data['_id']->getMongoID()) : array());
		$this->data = new Mongodloid_Entity(array_merge($empty_billrun_entry, $invoice_id_field, $id_field), $this->billrun_coll);
		$this->initBillrunDates();
	}

	/**
	 * returns true if account has no active subscribers and no relevant lines for next billrun
	 * @return true if account is deactivated (causes no xml to be produced for this account)
	 */
	public function is_deactivated() {
		$deactivated = true;
		foreach ($this->data['subs'] as $subscriber) {
			$its_empty = $this->empty_subscriber($subscriber);
			if (!$its_empty) {
				$deactivated = false;
				break;
			}
		}
		return $deactivated;
	}

	/**
	 * checks for a given account if its "empty" : its status is closed and it has no relevant lines for next billrun
	 * @param type $subscriber : sid
	 * @return true if its "emtpy"
	 */
	public function empty_subscriber($subscriber) {
		$status = $subscriber['subscriber_status'];
		return ( ($status == "closed") && !isset($subscriber['breakdown']));
	}
	
	/**
	 * Get an empty billrun account entry structure.
	 * @return array an empty billrun document
	 */
	public function populateBillrunWithAccountData($attributes) {
		$rawData = $this->data->getRawData();
		$rawData['attributes'] = $attributes;
		$this->data->setRawData($rawData);
	}
	
	protected function initBillrunDates() {
		
		$billrunDate = Billrun_Billingcycle::getEndTime($this->getBillrunKey());
		$this->data['creation_date'] = new MongoDate(time());
		$this->data['invoice_date'] = new MongoDate(strtotime(Billrun_Factory::config()->getConfigValue('billrun.invoicing_date', "first day of this month"), $billrunDate));
		$this->data['end_date'] = new MongoDate($billrunDate);
		$this->data['start_date'] = new MongoDate(Billrun_Billingcycle::getStartTime($this->getBillrunKey()));
		$this->data['due_date'] = new MongoDate(strtotime(Billrun_Factory::config()->getConfigValue('billrun.due_date_interval', "+14 days"), $billrunDate));
	}
}
