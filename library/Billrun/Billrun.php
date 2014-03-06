<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing Billrun class
 *
 * @package  Billrun
 * @since    0.5
 */
class Billrun_Billrun {

	protected $aid;
	protected $billrun_key;
	protected $data;
	protected static $runtime_billrun_key;
	protected static $vatAtDates = array();
	protected static $vatsByBillrun = array();

	/**
	 * lines collection
	 * @var Mongodloid_Collection 
	 */
	protected $lines = null;

	/**
	 * fields to filter when pulling account lines
	 * @var array 
	 */
	protected $filter_fields = array();

	/**
	 * whether to exclude the _id field when pulling account lines
	 * @var boolean
	 */
	static protected $rates = array();
	static protected $plans = array();

	/**
	 * billrun collection
	 * @var Mongodloid_Collection 
	 */
	protected $billrun_coll = null;

	/**
	 * 
	 * @param type $options
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function __construct($options = array()) {
		$this->vat = Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18);
		if (isset($options['aid']) && isset($options['billrun_key'])) {
			$this->aid = $options['aid'];
			$this->billrun_key = $options['billrun_key'];
			if (isset($options['autoload']) && !$options['autoload']) {
				if (isset($options['data']) && !$options['data']->isEmpty()) {
					$this->data = $options['data'];
				} else {
					$this->resetBillrun($this->aid, $this->billrun_key);
				}
			} else {
				$this->load();
			}
			$this->data->collection(Billrun_Factory::db()->billrunCollection());
		} else {
			Billrun_Factory::log()->log("Returning an empty billrun!", Zend_Log::NOTICE);
		}
		if (isset($options['filter_fields'])) {
			$this->filter_fields = array_map("intval", $options['filter_fields']);
		}
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun_coll = Billrun_Factory::db()->billrunCollection();
	}

	/**
	 * Updates the billrun object to match the db
	 * @return Billrun_Billrun
	 */
	protected function load() {
		$this->data = $this->billrun_coll->query(array(
							'aid' => $this->aid,
							'billrun_key' => $this->billrun_key,
						))
						->cursor()->limit(1)->current();
		$this->data->collection($this->billrun_coll);
		return $this;
	}

	/**
	 * Save the billrun to the db
	 * @param type $param
	 * @return type
	 */
	public function save() {
		if (isset($this->data)) {
			try {
				$this->data->save();
				return true;
			} catch (Exception $ex) {
				Billrun_Factory::log()->log('Error saving billrun document. Error code: ' . $ex->getCode() . '. Message: ' . $ex->getMessage(), Zend_Log::ERR);
			}
		}
		return false;
	}

	/**
	 * Add a subscriber to the current billrun entry.
	 * @param Billrun_Subscriber $subscriber
	 * @param string $status
	 * @return Billrun_Billrun the current instance of the billrun entry.
	 */
	public function addSubscriber($subscriber, $status) {
		$current_plan_name = $subscriber->plan;
		if (is_null($current_plan_name) || $current_plan_name == "NULL") {
			Billrun_Factory::log()->log("Null current plan for subscriber $subscriber->sid", Zend_Log::INFO);
			$current_plan_ref = null;
		} else {
			$current_plan_ref = $subscriber->getPlan()->createRef();
		}
		$next_plan = $subscriber->getNextPlan();
		if (is_null($next_plan)) {
			$next_plan_ref = null;
		} else {
			$next_plan_ref = $next_plan->createRef();
		}
		$subscribers = $this->data['subs'];
		$subscriber_entry = $this->getEmptySubscriberBillrunEntry($subscriber->sid);
		$subscriber_entry['subscriber_status'] = $status;
		$subscriber_entry['current_plan'] = $current_plan_ref;
		$subscriber_entry['next_plan'] = $next_plan_ref;
		foreach ($subscriber->getExtraFieldsForBillrun() as $field) {
			$subscriber_entry[$field] = $subscriber->{$field};
		}
		$subscribers[] = $subscriber_entry;
		$this->data['subs'] = $subscribers;
		return $this;
	}

	/**
	 * Check if a given subscriber exists in the current billrun.
	 * @param int $sid the  subscriber id to check.
	 * @return boolean TRUE if the subscriber exists in the current billrun entry, FALSE otherwise.
	 */
	public function subscriberExists($sid) {
		return $this->getSubRawData($sid) != false;
	}

	/**
	 * Checks if a billrun document exists in the db
	 * @param int $aid the account id
	 * @param string $billrun_key the billrun key
	 * @return boolean true if yes, false otherwise
	 */
	public static function exists($aid, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$data = $billrun_coll->query(array(
							'aid' => $aid,
							'billrun_key' => $billrun_key,
						))
						->cursor()->limit(1)->current();
		return !$data->isEmpty();
	}

	/**
	 * Get an empty billrun account entry structure.
	 * @param int $aid the account id of the billrun document
	 * @param string $billrun_key the billrun key of the billrun document
	 * @return array an empty billrun document
	 */
	public function getAccountEmptyBillrunEntry($aid, $billrun_key) {
		$vat = self::getVATByBillrunKey($billrun_key);
		return array(
			'aid' => $aid,
			'subs' => array(
			),
			'vat' => $vat,
			'billrun_key' => $billrun_key,
		);
	}

	/**
	 * Get the VAT value for some billing
	 * @param string $billrun_key the billing period to get VAT for
	 * @return float the VAT at the given time (0-1)
	 */
	public static function getVATByBillrunKey($billrun_key) {
		if (!isset(self::$vatsByBillrun[$billrun_key])) {
			$billrun_end_time = Billrun_Util::getEndTime($billrun_key);
			self::$vatsByBillrun[$billrun_key] = self::getVATAtDate($billrun_end_time);
			if (is_null(self::$vatsByBillrun[$billrun_key])) {
				self::$vatsByBillrun[$billrun_key] = floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18));
			}
		}
		return self::$vatsByBillrun[$billrun_key];
	}

	public function getBillrunKey() {
		return $this->billrun_key;
	}

	/**
	 * Get the VAT value for some unix timestamp
	 * @param int $timestamp the time to get VAT for
	 * @return float the VAT at the given time (0-1)
	 */
	protected static function getVATAtDate($timestamp) {
		if (!isset(self::$vatAtDates[$timestamp])) {
			self::$vatAtDates[$timestamp] = Billrun_Util::getVATAtDate($timestamp);
		}
		return self::$vatAtDates[$timestamp];
	}

	/**
	 * Get an empty billrun subscriber entry
	 * @return array an empty billrun subscriber entry
	 */
	public static function getEmptySubscriberBillrunEntry($sid) {
		return array(
			'sid' => $sid,
		);
	}

	/**
	 * Closes the billrun in the db by creating a unique invoice id
	 * @param int $min_id minimum invoice id to start from
	 */
	public function close($min_id) {
		$billrun_entity = $this->getRawData();
		if (is_null($ret = $billrun_entity->createAutoInc("invoice_id", $min_id))) {
			Billrun_Factory::log()->log("Failed to create invoice for account " . $this->aid, Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Created invoice " . $ret . " for account " . $this->aid, Zend_Log::INFO);
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
	 * Returns the breakdown keys for the row
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param boolean $vatable is the line vatable or not
	 * @return array an array containing the plan, category & zone keys respectively
	 */
	protected static function getBreakdownKeys($row, $pricingData, $vatable) {
		if ($row['type'] != 'flat') {
			//$rate = $row['arate'];
			$rate = self::getRowRate($row);
		}
		if ($row['type'] == 'credit') {
			$plan_key = 'credit';
			$zone_key = $row['service_name'];
		} else if (!isset($pricingData['over_plan']) && !isset($pricingData['out_plan'])) { // in plan
			$plan_key = 'in_plan';
			if ($row['type'] == 'flat') {
				$zone_key = 'service';
			}
		} else if (isset($pricingData['over_plan']) && $pricingData['over_plan']) { // over plan
			$plan_key = 'over_plan';
		} else { // out plan
			$plan_key = "out_plan";
		}

		if ($row['type'] == 'credit') {
			$category_key = $row['credit_type'] . "_" . ($vatable ? "vatable" : "vat_free");
		} else if (isset($rate['rates'][$row['usaget']]['category'])) {
			$category = $rate['rates'][$row['usaget']]['category'];
			switch ($category) {
				case "roaming":
					$category_key = "roaming";
					$zone_key = $row['serving_network'];
					break;
				case "special":
					$category_key = "special";
					break;
				case "intl":
					$category_key = "intl";
					break;
				default:
					$category_key = "base";
					break;
			}
		} else {
			$category_key = "base";
		}

		if (!isset($zone_key)) {
			//$zone_key = $row['arate']['key'];
			$zone_key = self::getRowRate($row)['key'];
		}
		return array($plan_key, $category_key, $zone_key);
	}

	/**
	 * Updates the billrun costs, lines & breakdown with the input line if the line is not already included in it
	 * @param string $billrun_key the billrun_key to insert into the billrun
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the input line
	 * @param boolean $vatable is the line vatable or not
	 * @return Mongodloid_Entity the billrun doc of the line, false if no such billrun exists
	 */
	public function updateBillrun($billrun_key, $counters, $pricingData, $row, $vatable) {
		$sid = $row['sid'];

		$sraw = $this->getSubRawData($sid);
		if ($sraw) { // it could be that this sid hasn't been returned on active_subscribers...
			$this->addLineToSubscriber($counters, $row, $pricingData, $vatable, $billrun_key, $sraw);
			$this->updateCosts($pricingData, $row, $vatable, $sraw);
			$this->setSubRawData($sraw);
		} else {
			Billrun_Factory::log("Subscriber $sid is not active, yet has lines", Zend_log::ALERT);
			$subscriber_general_settings = Billrun_Config::getInstance()->getConfigValue('subscriber', array());
			$null_subscriber_params = array(
				'data' => array('aid' => $row['aid'], 'sid' => $sid, 'plan' => null, 'next_plan' => null,),
			);
			$subscriber_settings = array_merge($subscriber_general_settings, $null_subscriber_params);
			$subscriber = Billrun_Subscriber::getInstance($subscriber_settings);
			$this->addSubscriber($subscriber, "closed");
			$this->updateBillrun($billrun_key, $counters, $pricingData, $row, $vatable);
		}
	}

	/**
	 * * Returns a more general usage type to be used as a key for billrun lines
	 * @param string $specific_usage_type specific usage type (usually lines' 'usaget' field) such as 'call', 'incoming_call' etc.
	 * @return string the general usage type
	 */
	public static function getGeneralUsageType($specific_usage_type) {
		switch ($specific_usage_type) {
			case 'call':
			case 'incoming_call':
				return 'call';
			case 'sms':
			case 'incoming_sms':
				return 'sms';
			case 'data':
				return 'data';
			case 'mms':
				return 'mms';
			case 'flat':
				return 'flat';
			case 'credit':
				return 'credit';
			default:
				return 'call';
		}
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
		}
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
	protected function addLineToSubscriber($counters, $row, $pricingData, $vatable, $billrun_key, &$sraw) {
		$usage_type = self::getGeneralUsageType($row['usaget']);
		list($plan_key, $category_key, $zone_key) = self::getBreakdownKeys($row, $pricingData, $vatable);
		$zone = &$sraw['breakdown'][$plan_key][$category_key][$zone_key];

		if ($plan_key != 'credit') {
			if (!empty($counters)) {
				if (isset($pricingData['over_plan']) && $pricingData['over_plan'] < current($counters)) { // volume is partially priced (in & over plan)
					$volume_priced = $pricingData['over_plan'];
					$planZone = &$sraw['breakdown']['in_plan'][$category_key][$zone_key];
					$planZone['totals'][key($counters)]['usagev'] = $this->getFieldVal($planZone['totals'][key($counters)]['usagev'], 0) + current($counters) - $volume_priced; // add partial usage to flat
					$planZone['totals'][key($counters)]['cost'] = $this->getFieldVal($planZone['totals'][key($counters)]['cost'], 0);
					$planZone['totals'][key($counters)]['count'] = $this->getFieldVal($planZone['totals'][key($counters)]['count'], 0) + 1;
					$planZone['vat'] = ($vatable ? floatval($this->vat) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
				} else {
					$volume_priced = current($counters);
				}
				$zone['totals'][key($counters)]['usagev'] = $this->getFieldVal($zone['totals'][key($counters)]['usagev'], 0) + $volume_priced;
				$zone['totals'][key($counters)]['cost'] = $this->getFieldVal($zone['totals'][key($counters)]['cost'], 0) + $pricingData['aprice'];
				$zone['totals'][key($counters)]['count'] = $this->getFieldVal($zone['totals'][key($counters)]['count'], 0) + 1;
			}
			if ($plan_key != 'in_plan' || $zone_key == 'service') {
				$zone['cost'] = $this->getFieldVal($zone['cost'], 0) + $pricingData['aprice'];
			}
			$zone['vat'] = ($vatable ? floatval($this->vat) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
		} else {
			$zone += $pricingData['aprice'];
		}
		if ($usage_type == 'data' && $row['type'] != 'tap3') {
			$date_key = date("Ymd", $row['urt']->sec);
			$sraw['lines'][$usage_type]['counters'][$date_key]['usagev'] = $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key]['usagev'], 0) + $row['usagev'];
			$sraw['lines'][$usage_type]['counters'][$date_key]['aprice'] = $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key]['aprice'], 0) + $row['aprice'];
			$sraw['lines'][$usage_type]['counters'][$date_key]['plan_flag'] = $this->getDayPlanFlagByDataRow($row, $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key]['plan_flag'], 'in'));
		}

		if ($vatable) {
			$sraw['totals']['vatable'] = $this->getFieldVal($sraw['totals']['vatable'], 0) + $pricingData['aprice'];
			$price_after_vat = $pricingData['aprice'] + ($pricingData['aprice'] * self::getVATByBillrunKey($billrun_key));
		} else {
			$price_after_vat = $pricingData['aprice'];
		}
		$sraw['totals']['before_vat'] = $this->getFieldVal($sraw['totals']['before_vat'], 0) + $pricingData['aprice'];
		$sraw['totals']['after_vat'] = $this->getFieldVal($sraw['totals']['after_vat'], 0) + $price_after_vat;
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
		$newTotals = array('before_vat' => 0, 'after_vat' => 0, 'vatable' => 0);
		foreach ($this->data['subs'] as $sub) {
			//Billrun_Factory::log(print_r($sub));
			$newTotals['before_vat'] += $this->getFieldVal($sub['totals']['before_vat'], 0);
			$newTotals['after_vat'] += $this->getFieldVal($sub['totals']['after_vat'], 0);
			$newTotals['vatable'] += $this->getFieldVal($sub['totals']['vatable'], 0);
		}
		$rawData['totals'] = $newTotals;
		$this->data->setRawData($rawData);
	}

	/**
	 * Returns an array value if it is set
	 * @param mixed $field the array value
	 * @param mixed $defVal the default value to return if $field is not set
	 * @return mixed the array value if it is set, otherwise returns $defVal
	 */
	protected function getFieldVal(&$field, $defVal) {
		if (isset($field)) {
			return $field;
		}
		return $defVal;
	}

	/**
	 * HACK TO MAKE THE BILLLRUN FASTER
	 * Get a rate from the row
	 * @param Mongodloid_Entity the row to get rate from
	 * @return Mongodloid_Entity the rate of the row
	 */
	protected static function getRowRate($row) {
		$raw_rate = $row->get('arate', true);
		$id_str = strval($raw_rate['$id']);
		return self::getRateById($id_str);
	}

	/**
	 * Get a rate by hexadecimal id
	 * @param string $id hexadecimal id of rate (taken from Mongo ID)
	 * @return Mongodloid_Entity the corresponding rate
	 */
	protected static function getRateById($id) {
		if (!isset(self::$rates[$id])) {
			$rates_coll = Billrun_Factory::db()->ratesCollection();
			self::$rates[$id] = $rates_coll->findOne($id);
		}
		return self::$rates[$id];
	}

	/**
	 * Get a plan by hexadecimal id
	 * @param string $id hexadecimal id of a plan (taken from Mongo ID)
	 * @return Mongodloid_Entity the corresponding plan
	 */
	protected static function getPlanById($id) {
		if (!isset(self::$plans[$id])) {
			self::$plans[$id] = Billrun_Factory::db()->plansCollection()->findOne($id);
		}
		return self::$plans[$id];
	}

	/**
	 * Load all rates from db into memory
	 */
	public static function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rates = $rates_coll->query()->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			self::$rates[strval($rate->getId())] = $rate;
		}
	}

	/**
	 * Load all plans from db into memory
	 */
	public static function loadPlans() {
		$plans_coll = Billrun_Factory::db()->plansCollection();
		$plans = $plans_coll->query()->cursor()->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED);
		foreach ($plans as $plan) {
			$plan->collection($plans_coll);
			self::$plans[strval($plan->getId())] = $plan;
		}
	}

	/**
	 * Add all lines of the account to the billrun object
	 * @param boolean $update_lines whether to set the billrun key as the billrun stamp of the lines
	 * @param int $start_time lower bound date to get lines from. A unix timestamp 
	 * @return array the stamps of the lines used to create the billrun
	 */
	public function addLines($start_time = 0, $flat_lines = array()) {
		Billrun_Factory::log()->log("Querying account " . $this->aid . " for lines...", Zend_Log::INFO);
		$account_lines = $this->getAccountLines($this->aid, $start_time, false);
		Billrun_Factory::log("Processing account Lines $this->aid", Zend_Log::INFO);
		$updatedLines = array_merge($this->processLines($account_lines), $this->processLines($flat_lines));
		Billrun_Factory::log("Finished processing account $this->aid lines. Total: " . count($updatedLines), Zend_log::INFO);
		$this->updateTotals();
		return $updatedLines;
	}

	protected function processLines($account_lines) {
		$updatedLines = array();
		foreach ($account_lines as $line) {
			if (isset($updatedLines[$line['stamp']])) { // temporary fix for https://jira.mongodb.org/browse/SERVER-9858
				continue;
			}
			$line->collection($this->lines);
			$pricingData = array('aprice' => $line['aprice']);
			if (isset($line['over_plan'])) {
				$pricingData['over_plan'] = $line['over_plan'];
			} else if (isset($line['out_plan'])) {
				$pricingData['out_plan'] = $line['out_plan'];
			}

			if ($line['type'] != 'flat') {
				$rate = $this->getRowRate($line);
				$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
				$this->updateBillrun($this->billrun_key, array($line['usaget'] => $line['usagev']), $pricingData, $line, $vatable);
			} else {
				$plan = self::getPlanById(strval($line->get('plan_ref', true)['$id']));
				$this->updateBillrun($this->billrun_key, array(), array('aprice' => $line['aprice']), $line, $plan->get('vatable'));
			}
			//Billrun_Factory::log("Done Processing account Line for $sid : ".  microtime(true));
			$updatedLines[$line['stamp']] = $line;
		}
		return $updatedLines;
	}

	/**
	 * Gets all the account lines for this billrun from the db
	 * @param int $aid the account id
	 * @param int $start_time lower bound date to get lines from. A unix timestamp
	 * @return Mongodloid_Cursor the mongo cursor used to iterate over the lines
	 * @todo remove aid parameter
	 */
	protected function getAccountLines($aid, $start_time = 0, $include_flats = true) {
		$start_time = new MongoDate($start_time);
		$end_time = new MongoDate(Billrun_Util::getEndTime($this->billrun_key));
		$query = array(
			'aid' => $aid,
			'urt' => array(
				'$lte' => $end_time,
				'$gte' => $start_time, // needed for current balance
			),
//			'aprice' => array(
//				'$exists' => true,
//			),
//			'type' => array(
//				'$ne' => 'ggsn',
//			),
		);
		if (!$include_flats) {
			$query['type'] = array(
				'$ne' => 'flat',
			);
		}

		$query['billrun'] = $this->billrun_key;

		$hint = array(
			'aid' => 1,
			'urt' => 1,
		);

		$sort = array(
			'aid' => 1,
			'urt' => 1,
		);
		Billrun_Factory::log()->log("Querying for account " . $aid . " lines", Zend_Log::INFO);
		$cursor = $this->lines->query($query)->cursor()->fields($this->filter_fields)->sort($sort)->setReadPreference(MongoClient::RP_SECONDARY_PREFERRED)->hint($hint);
		Billrun_Factory::log()->log("Finished querying for account " . $aid . " lines", Zend_Log::INFO);
//		$results = array();
//		Billrun_Factory::log()->log("Saving account " . $aid . " lines to array", Zend_Log::DEBUG);
//		foreach ($cursor as $entity) {
//			$results[] = $entity;
//		}
//		Billrun_Factory::log()->log("Finished saving account " . $aid . " lines to array", Zend_Log::DEBUG);
//		return $results;
		return $cursor;
	}

	/**
	 * Resets the billrun data
	 * @param type $account_id
	 * @param type $billrun_key
	 */
	public function resetBillrun($account_id, $billrun_key) {
		$this->data = new Mongodloid_Entity($this->getAccountEmptyBillrunEntry($account_id, $billrun_key));
	}

	/**
	 * Returns the minimum billrun key greater than all the billrun keys in billrun collection
	 * @return string billrun_key
	 * @todo create an appropriate index on billrun collection
	 */
	public static function getActiveBillrun() {
		$now = time();
		$sort = array(
			'billrun_key' => -1,
		);
		$fields = array(
			'billrun_key' => 1,
		);
		$runtime_billrun_key = Billrun_Util::getBillrunKey($now);
		$last = Billrun_Factory::db()->billrunCollection()->query()->cursor()->limit(1)->fields($fields)->sort($sort)->current();
		if ($last->isEmpty()) {
			$active_billrun = $runtime_billrun_key;
		} else {
			$active_billrun = Billrun_Util::getFollowingBillrunKey($last['billrun_key']);
			$billrun_start_time = Billrun_Util::getStartTime($active_billrun);
			if ($now - $billrun_start_time > 5184000) { // more than two months diff (60*60*24*30*2)
				$active_billrun = $runtime_billrun_key;
			}
		}
		return $active_billrun;
	}

}

Billrun_Billrun::loadRates();
Billrun_Billrun::loadPlans();
