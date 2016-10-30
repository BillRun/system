<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregated subscriber's invoice container
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Subscriber_Invoice {
	
	/**
	 *
	 * @var array
	 */
	protected $data;
	
	protected $rates = array();
		
	/**
	 * 
	 * @param array $data - Subscriber data
	 * @param integer $sid
	 * @param integer $aid
	 */
	public function __construct(&$rates, $data, $sid = 0, $aid = 0) {
		$this->rates = &$rates;
		if(!$data) {
			$this->data = $this->createClosedSubscriber($sid, $aid);
		} else {
			$this->data = $data;
		}
	}

	/**
	 * Create a closed subscriber record
	 * @param type $sid
	 * @param type $aid
	 * @return type
	 */
	protected function createClosedSubscriber($sid, $aid) {
		Billrun_Factory::log("Subscriber $sid is not active, yet has lines", Zend_Log::ALERT);
		$subscriber = array(
			'aid' => $aid, 
			'sid' => $sid, 
			'plan' => null, 
			'next_plan' => null,
			'subscriber_status' => 'closed'
		);
		return $subscriber;
	}

	/**
	 * Set data
	 * @param type $key
	 * @param type $value
	 */
	public function setData($key, $value) {
		$this->data[$key] = $value;
	}
	
	/**
	 * Gets the subscriber data.
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Updates the billrun costs
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param boolean $vatable is the row vatable
	 */
	public function updateCosts($pricingData, $row, $vatable) {
		$vat_key = ($vatable ? "vatable" : "vat_free");
		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			if (!isset($this->data['costs']['over_plan'][$vat_key])) {
				$this->data['costs']['over_plan'][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs']['over_plan'][$vat_key] += $pricingData['aprice'];
			}
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			if (!isset($this->data['costs']['out_plan'][$vat_key])) {
				$this->data['costs']['out_plan'][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs']['out_plan'][$vat_key] += $pricingData['aprice'];
			}
		} else if ($row['type'] == 'flat') {
			if (!isset($this->data['costs']['flat'][$vat_key])) {
				$this->data['costs']['flat'][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs']['flat'][$vat_key] += $pricingData['aprice'];
			}
		} else if ($row['type'] == 'credit') {
			if (!isset($this->data['costs']['credit'][$row['credit_type']][$vat_key])) {
				$this->data['costs']['credit'][$row['credit_type']][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs']['credit'][$row['credit_type']][$vat_key] += $pricingData['aprice'];
			}
		} else if ($row['type'] == 'service') {
			if (!isset($this->data['costs']['service'][$vat_key])) {
				$this->data['costs']['service'][$vat_key] = $pricingData['aprice'];
			} else {
				$this->data['costs']['service'][$vat_key] += $pricingData['aprice'];
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
	
	protected function updateBreakdown($breakdownKey, $rate, $cost, $count) {
		if (!isset($this->data['breakdown'][$breakdownKey])) {
			$this->data['breakdown'][$breakdownKey] = array();
		}
		$rate_key = $rate['key'];
		foreach ($this->data['breakdown'][$breakdownKey] as &$breakdowns) {
			if ($breakdowns['name'] === $rate_key) {
				$breakdowns['cost'] += $cost;
				$breakdowns['count'] += $count;
				return;
			}
		}
		
		if(!$rate_key) {
			$rate_key = "Plans and Services";
		}
		
		$this->data['breakdown'][$breakdownKey][] = array('name' => $rate_key, 'count' => $count, 'cost' => $cost);
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
	 * Add pricing and usage counters to the subscriber billrun breakdown.
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param boolean $vatable is the line vatable or not
	 * @param string $billrun_key the billrun_key of the billrun
	 * @todo remove billrun_key parameter
	 */
	public function addLine($counters, $row, $pricingData, $vatable) {
		if (!$breakdownKey = $this->getBreakdownKey($row)) {
			return;
		}
		$rate = $this->getRowRate($row);
		$this->updateBreakdown($breakdownKey, $rate, $pricingData['aprice'], $row['usagev']);
		
		// TODO: apply arategroup to new billrun object
		if (isset($row['arategroup'])) {
			$this->addLineGroupData($counters, $row);
		}
		
		if (!isset($this->data['totals'][$breakdownKey])) {
			$this->data['totals'][$breakdownKey] = array();
		}

		$priceAfterVat = $pricingData['aprice'];
		if ($vatable) {
			$priceAfterVat = $this->addLineVatableData($pricingData, $breakdownKey);
		} 
		
		$this->data['totals']['before_vat'] = Billrun_Util::getFieldVal($this->data['totals']['before_vat'], 0) + $pricingData['aprice'];
		$this->data['totals']['after_vat'] = Billrun_Util::getFieldVal($this->data['totals']['after_vat'], 0) + $priceAfterVat;
		$this->data['totals'][$breakdownKey]['before_vat'] = Billrun_Util::getFieldVal($this->data['totals'][$breakdownKey]['before_vat'], 0) + $pricingData['aprice'];
		$this->data['totals'][$breakdownKey]['after_vat'] = Billrun_Util::getFieldVal($this->data['totals'][$breakdownKey]['after_vat'], 0) + $priceAfterVat;
	}

	/**
	 * Add group data
	 * @param type $counters
	 * @param type $row
	 */
	protected function addLineGroupData($counters, $row) {
		if (isset($row['in_plan'])) {
			$usagev = Billrun_Util::getFieldVal($this->data['groups'][$row['arategroup']]['in_plan']['totals'][key($counters)]['usagev'], 0) + $row['in_plan'];
			$this->data['groups'][$row['arategroup']]['in_plan']['totals'][key($counters)]['usagev'] = $usagev;
		}
		if (isset($row['over_plan'])) {
			$usagev = Billrun_Util::getFieldVal($this->data['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['usagev'], 0) + $row['over_plan'];
			$this->data['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['usagev'] = $usagev;
			$cost = Billrun_Util::getFieldVal($this->data['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['cost'], 0) + $row['aprice'];
			$this->data['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['cost'] = $cost;
		}
	}
	
	/**
	 * Add the line vatable data to the totals
	 * @return integer Price after vat.
	 */
	protected function addLineVatableData($pricingData, $breakdownKey) {
		$this->data['totals']['vatable'] = Billrun_Util::getFieldVal($this->data['totals']['vatable'], 0) + $pricingData['aprice'];
		$this->data['totals'][$breakdownKey]['vatable'] = Billrun_Util::getFieldVal($this->data['totals'][$breakdownKey]['vatable'], 0) + $pricingData['aprice'];
		$vat = Billrun_Rates_Util::getVat();
		return $pricingData['aprice'] + ($pricingData['aprice'] * $vat);
	}
	
	/**
	 * Add a single subscriber record to the array of totals
	 * @param type $newTotals
	 * @return type
	 */
	public function updateTotals($newTotals) {
		$newTotals['before_vat'] += Billrun_Util::getFieldVal($this->data['totals']['before_vat'], 0);
		$newTotals['after_vat'] += Billrun_Util::getFieldVal($this->data['totals']['after_vat'], 0);
		$newTotals['after_vat_rounded'] = round($newTotals['after_vat'], 2);
		$newTotals['vatable'] += Billrun_Util::getFieldVal($this->data['totals']['vatable'], 0);
		$newTotals['flat']['before_vat'] += Billrun_Util::getFieldVal($this->data['totals']['flat']['before_vat'], 0);
		$newTotals['flat']['after_vat'] += Billrun_Util::getFieldVal($this->data['totals']['flat']['after_vat'], 0);
		$newTotals['flat']['vatable'] += Billrun_Util::getFieldVal($this->data['totals']['flat']['vatable'], 0);
		$newTotals['service']['before_vat'] += Billrun_Util::getFieldVal($this->data['totals']['service']['before_vat'], 0);
		$newTotals['service']['after_vat'] += Billrun_Util::getFieldVal($this->data['totals']['service']['after_vat'], 0);
		$newTotals['service']['vatable'] += Billrun_Util::getFieldVal($this->data['totals']['service']['vatable'], 0);
		$newTotals['usage']['before_vat'] += Billrun_Util::getFieldVal($this->data['totals']['usage']['before_vat'], 0);
		$newTotals['usage']['after_vat'] += Billrun_Util::getFieldVal($this->data['totals']['usage']['after_vat'], 0);
		$newTotals['usage']['vatable'] += Billrun_Util::getFieldVal($this->data['totals']['usage']['vatable'], 0);
		return $newTotals;
	}
	
	/**
	 * Add all lines of the account to the billrun object
	 * @param array $aggregated array of aggregated subscribers.
	 * @return array the stamps of the lines used to create the billrun
	 */
	public function addLines($aggregated) {
		$sid = $this->data['sid'];
		$aid = $this->data['aid'];
		Billrun_Factory::log("Querying subscriber " . $aid . ":" . $sid . " for lines...", Zend_Log::DEBUG);
		$subLines = $this->loadSubscriberLines();

		$lines = array_merge($subLines, $aggregated);
		Billrun_Factory::log("Processing account Lines $aid:$sid" . " lines: " . count($lines), Zend_Log::DEBUG);

		$updatedLines = $this->processLines(array_values($lines));
		Billrun_Factory::log("Finished processing account $aid:$sid lines. Total: " . count($updatedLines), Zend_Log::DEBUG);
		return $updatedLines;
	}

	/**
	 * Process an array of lines.
	 * @param type $subLines
	 * @return type
	 */
	protected function processLines($subLines) {
		$updatedLines = array();
		foreach ($subLines as $line) {
			
			// the check fix 2 issues:
			// 1. temporary fix for https://jira.mongodb.org/browse/SERVER-9858
			// 2. avoid duplicate lines
			if (isset($updatedLines[$line['stamp']])) {
				Billrun_Factory::log("Skipping duplicate line");
				continue;
			}
			
			// Process a single line.
			if(!$this->processLine($line)) {
				continue;
			}
			
			//Billrun_Factory::log("Done Processing account Line for $sid : ".  microtime(true));
			$updatedLines[$line['stamp']] = $line;
		}
		return $updatedLines;
	}

	/**
	 * Process a single line.
	 * @param type $line
	 * @return boolean
	 */
	protected function processLine($line) {
		$pricingData = $this->getPricingData($line);

		if ($line['type'] == 'flat') {
			if(!$this->processFlatLine($line)) {
				return false;
			}
		} else {
			$rate = $this->getRowRate($line);
			$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
			$this->updateInvoice(array($line['usaget'] => $line['usagev']), $pricingData, $line, $vatable);
		} 
		
		return true;
	}
	
	/**
	 * Get the pricing data array for a line
	 * @param type $line
	 * @return type
	 */
	protected function getPricingData($line) {
		$pricingData = array('aprice' => $line['aprice']);
		if (isset($line['over_plan'])) {
			$pricingData['over_plan'] = $line['over_plan'];
		} else if (isset($line['out_plan'])) {
			$pricingData['out_plan'] = $line['out_plan'];
		}
		return $pricingData;
	}
	
	/**
	 * Process a flat line
	 * @param arary $line
	 * @return boolean true if successful.
	 */
	protected function processFlatLine($line) {
		$vatable = isset($line['vatable']);
		$this->updateInvoice(array(), array('aprice' => $line['aprice']), $line, !$vatable);
		return true;
	}

		/**
	 * Updates the billrun costs, lines & breakdown with the input line if the line is not already included in it
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the input line
	 * @param boolean $vatable is the line vatable or not
	 */
	public function updateInvoice($counters, $pricingData, $row, $vatable) {
		$this->addLine($counters, $row, $pricingData, $vatable);
		$this->updateCosts($pricingData, $row, $vatable);
	}
	
	/**
	 * Gets all the account lines for this billrun from the db
	 * @return an array containing all the  accounts with thier lines.
	 */
	public function loadSubscriberLines() {
		$ret = array();
		$sid = $this->data['sid'];
		$aid = $this->data['aid'];
		$query = array(
			'aid' => $aid,
			'sid' => $sid,
			'billrun' => $this->data['key']
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
