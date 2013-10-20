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

	/**
	 * 
	 * @param type $options
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function __construct($options = array()) {
		if (isset($options['aid']) && isset($options['billrun_key'])) {
			$this->aid = $options['aid'];
			$this->billrun_key = $options['billrun_key'];
			if (isset($options['autoload']) && !$options['autoload']) {
				if (isset($options['data']) && !$options['data']->isEmpty()) {
					$this->data = $options['data'];
				} else {
					$this->data = new Mongodloid_Entity($this->getAccountEmptyBillrunEntry($this->aid, $this->billrun_key));
				} 
			} else {
				$this->load();
			}
			$this->data->collection(Billrun_Factory::db()->billrunCollection());
		} else {
			Billrun_Factory::log()->log("Returning an empty billrun!",Zend_Log::NOTICE);
		}
	}

	/**
	 * 
	 * @param type $aid
	 * @param type $billrun_key
	 * @return \Billrun_Billrun
	 * @todo used only in current balance API. Needs refactoring
	 */
	protected function load() {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$this->data = $billrun_coll->query(array(
							'aid' => $this->aid,
							'billrun_key' => $this->billrun_key,
						))
						->cursor()->limit(1)->current();
		$this->data->collection($billrun_coll);
		return $this;
	}
	
	/**
	 * Save the current billrun
	 * @param type $param
	 * @return type
	 */
	public function save() {
		
		return isset($this->data) ? $this->data->save() : false;
	}

	/**
	 * Add a subscriber to the current billrun entry.
	 * @param type $sid the  subscriber id  to add.
	 * @return \Billrun_Billrun the current instance  of the billrun entry.
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function addSubscriber($sid) {
		$subscribers = $this->data['subs'];
		$subscribers[] = $this->getEmptySubscriberBillrunEntry($sid);
		$this->data['subs'] = $subscribers;
		return $this;
	}

	/**
	 * check if a given subscriber exists in the current billrun.
	 * @param type $sid the  subscriber id to check.
	 * @return boolean TRUE  is  the subscriber  exists in the current billrun entry FALSE otherwise.
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function exists($sid) {
		return $this->getSubRawData($sid) != false;
	}

	/**
	 * Get an empty billrun account  entry structure.
	 * @param type $aid the account id that the enery belongs to.
	 * @return Array tan empty billrun account  structure.
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

	protected static function getVATByBillrunKey($billrun_key) {
		$billrun_end_time = Billrun_Util::getEndTime($billrun_key);
		$vat = self::getVATAtDate($billrun_end_time);
		if (is_null($vat)) {
			$vat = floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18));
		}
		return $vat;
	}

	protected static function getVATAtDate($timestamp) {
		if (!isset(self::$vatAtDates[$timestamp])) {
			self::$vatAtDates[$timestamp] = Billrun_Util::getVATAtDate($timestamp);
		}
		return self::$vatAtDates[$timestamp];
	}

	/**
	 * Get an empty billrun subscriber entry
	 * @return Array an empty billrun subscriber entry
	 */
	public static function getEmptySubscriberBillrunEntry($sid) {
		return array(
			'sid' => $sid,
		);
	}

	protected static function getVATTypes() {
		return array(
			'vatable' => 0,
			'vat_free' => 0,
		);
	}

	/**
	 * 
	 * @return type
	 * @todo in order to save space, it may be unnecessary to initialize the billrun with categories.
	 */
	protected static function getCategories() {
		return array(
			'base' => new stdclass,
			'intl' => new stdclass,
			'special' => new stdclass,
			'roaming' => new stdclass,
		);
	}

	/**
	 * Closes the current billrun by creating invoice ID and saves it.
	 */
	public static function close($aid, $billrun_key, $min_id) {
		$billrun = self::createBillrunIfNotExists($aid, $billrun_key);
		if (is_null($ret = $billrun->createAutoInc("invoice_id", $min_id))) {
			Billrun_Factory::log()->log("Failed to create invoice for account " . $aid, Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Created invoice " . $ret . " for account " . $aid, Zend_Log::INFO);
		}
	}

	/**
	 * 
	 * @param type $sid
	 * @return mixed
	 * @todo Needs refactoring
	 */
	protected function getSubRawData($sid) {
		if($this->data) {
			foreach ($this->data['subs'] as $sub_entry) {
				if ($sub_entry['sid'] == $sid) {
					return $sub_entry;
				}
			}
		}
		return false;
	}

	/**
	 * 
	 * @param type $sid
	 * @return mixed
	 * @todo Needs refactoring
	 */
	protected function setSubRawData($sid,$rawData) {
		if($this->data) {
			$data = $this->data->getRawData();
			foreach ($data['subs'] as &$sub_entry) {
				if ($sub_entry['sid'] == $sid) {
					$sub_entry = $rawData;
					$this->data->setRawData($data);
					return true;
				}
			}
			$data['subs'][] = $rawData;
		}
		return false;
	}
	
	/**
	 * get the account's latest open billrun
	 * @param int $aid
	 * @return mixed the billrun object or false if none found
	 * @todo used only in current balance API. Needs refactoring.
	 * 
	 */
	public static function getLastOpenBillrun($aid) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$data = $billrun_coll->query(array(
					'aid' => $aid,
					'invoice_id' => array(
						'$exists' => false,
					),
				))
				->cursor()
				->sort(array('billrun_key' => -1))
				->current();
		if ($data->isEmpty()) { // no open billruns for the account
			$data = $billrun_coll->query('aid', $aid)
					->cursor()
					->sort(array('billrun_key' => -1))
					->current();
			if ($data->isEmpty()) { // no billruns at all for account
				$billrun_key = Billrun_Util::getBillrunKey(time());
			} else {
				$billrun_key = Billrun_Util::getFollowingBillrunKey($data['billrun_key']);
			}
			$billrun = Billrun_Factory::billrun(array('aid' => $aid, 'billrun_key' => $billrun_key, 'autoload' => false));
		} else {
			$billrun = Billrun_Factory::billrun(array('aid' => $aid, 'billrun_key' => $data['billrun_key'], 'autoload' => false, 'data' => $data));
		}
		return $billrun; // return the open billrun found
	}

	/**
	 * 
	 * @return type
	 * @todo used only in current balance API. Needs refactoring.
	 */
	public function getRawData() {
		return $this->data;
	}

	/**
	 * Returns a query that matches the billrun parameters supplied
	 * @param int $aid the account id
	 * @param int $billrun_key the billrun key
	 * @param int $sid the subscriber id
	 * @return array the query
	 */
	public static function getMatchingBillrunQuery($aid, $billrun_key, $sid = null) {
		$query = array(
			'aid' => $aid,
			'billrun_key' => $billrun_key,
		);
		if (!is_null($sid)) {
			$query['subs'] = array(
				'$elemMatch' => array(
					'sid' => $sid,
				)
			);
		}
		return $query;
	}

	/**
	 * Get a query that returns open billrun only
	 * @return array the query
	 */
	protected static function getOpenBillrunQuery() {
		$query = array(
			'invoice_id' => array(
				'$exists' => false,
			),
		);
		return $query;
	}

	/**
	 * Get a query that produces a billrun that does not include the input line
	 * @param type $sid
	 * @param string $usage_type the general usage type of the line (output of getGeneralUsageType function)
	 * @param MongoDBRef $row_ref the reference of the line we wish to insert
	 * @return array the query
	 */
	protected static function getDistinctLinesBillrunQuery($sid, $usage_type, $row_ref) {
		$query['subs'] = array(
			'$elemMatch' => array(
				'sid' => $sid,
				'lines.' . $usage_type . '.refs' => array(
					'$nin' => array(
						$row_ref
					)
				)
			)
		);
		return $query;
	}

	/**
	 * Returns the increment costs update query
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param boolean $vatable is the row vatable
	 * @return array the increment costs query
	 */
	protected static function getUpdateCostsQuery($pricingData, $row, $vatable) {
		$vat_key = ($vatable ? "vatable" : "vat_free");
		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			$update['$inc']['subs.$.costs.over_plan.' . $vat_key] = $pricingData['aprice'];
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			$update['$inc']['subs.$.costs.out_plan.' . $vat_key] = $pricingData['aprice'];
		} else if ($row['type'] == 'flat') {
			$update['$inc']['subs.$.costs.flat.' . $vat_key] = $pricingData['aprice'];
		} else if ($row['type'] == 'credit') {
			$update['$inc']['subs.$.costs.credit.' . $row['credit_type'] . '.' . $vat_key] = $pricingData['aprice'];
		} else {
			$update = array();
		}
		return $update;
	}

	/**
	 * Returns the increment totals update query
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param string $billrun_key the billrun_key to insert into the billrun
	 * @param boolean $vatable is the line vatable or not
	 */
	protected static function getUpdateTotalsQuery($pricingData, $billrun_key, $vatable) {
		if ($vatable) {
			$update['$inc']['subs.$.totals.vatable'] = $pricingData['aprice'];
			$update['$inc']['totals.vatable'] = $pricingData['aprice'];
			$vat = self::getVATByBillrunKey($billrun_key);
			$price_after_vat = $pricingData['aprice'] + $pricingData['aprice'] * $vat;
		} else {
			$price_after_vat = $pricingData['aprice'];
		}

		$update['$inc']['subs.$.totals.before_vat'] = $pricingData['aprice'];
		$update['$inc']['subs.$.totals.after_vat'] = $price_after_vat;
		$update['$inc']['totals.before_vat'] = $pricingData['aprice'];
		$update['$inc']['totals.after_vat'] = $price_after_vat;

		return $update;
	}

	/**
	 * Returns the increment data counters update query
	 * @param string $usage_type the general usage type of the line (output of getGeneralUsageType function)
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @return array the increment data counters query
	 */
	protected static function getUpdateDataCountersQuery($usage_type, $row) {
		if ($usage_type == 'data' && $row['type'] != 'tap3') {
			$date_key = date("Ymd", $row['urt']->sec);
			$update['$inc']['subs.$.lines.data.counters.' . $date_key] = $row['usagev'];
		} else {
			$update = array();
		}
		return $update;
	}

	/**
	 * Returns the subscriber status update query
	 * @param string $subscriber_status the subscriber status
	 * @return array the subscriber status update query
	 */
	protected static function getUpdateSubscriberStatusQuery($subscriber_status) {
		if (!is_null($subscriber_status)) {
			$update['$set']['subs.$.subscriber_status'] = $subscriber_status;
		} else {
			$update = array();
		}
		return $update;
	}

	/**
	 * Returns the push to lines update query
	 * @param string $usage_type the general usage type of the line (output of getGeneralUsageType function)
	 * @param MongoDBRef $row_ref the reference of the line we wish to insert
	 * @return array the push to lines query
	 */
	protected static function getPushLineQuery($usage_type, $row_ref) {
		return array(
			'$push' => array(
				'subs.$.lines.' . $usage_type . '.refs' => $row_ref
			)
		);
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
			$zone_key = $row['reason'];
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

		if ( !isset($zone_key) ) {
			//$zone_key = $row['arate']['key'];
			$zone_key = self::getRowRate($row)['key'];
		}
		return array($plan_key, $category_key, $zone_key);
	}

	/**
	 * Add pricing and usage counters to the billrun breakdown.
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param boolean $vatable is the line vatable or not
	 * @param string $plan_key the plan key to be used under breakdown key
	 * @param string $category_key the category key to be used under plan key
	 * @param string $zone_key the zone key to be used under category key
	 */
	protected static function getUpdateBreakdownQuery($counters, $pricingData, $vatable, $plan_key, $category_key, $zone_key) {
		if ($plan_key != 'credit') {
			if (!empty($counters)) {
				if (!empty($pricingData) && isset($pricingData['over_plan']) && $pricingData['over_plan'] < current($counters)) { // volume is partially priced (in & over plan)
					$volume_priced = $pricingData['over_plan'];
					$update['$inc']['subs.$.breakdown.in_plan.' . $category_key . '.' . $zone_key . '.totals.' . key($counters) . '.usagev'] = current($counters) - $volume_priced; // add partial usage to flat
				} else {
					$volume_priced = current($counters);
				}
				$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.totals.' . key($counters) . '.usagev'] = $volume_priced;
				$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.totals.' . key($counters) . '.cost'] = $pricingData['aprice'];
				if ($plan_key != 'in_plan') {
					$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.cost'] = $pricingData['aprice'];
				}
			} else if ($zone_key == 'service') { // flat
				$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.cost'] = $pricingData['aprice'];
			}
			$update['$set']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.vat'] = ($vatable ? floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18)) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
		} else {
			$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key] = $pricingData['aprice'];
		}
		return $update;
	}

	/**
	 * Updates the billrun costs, lines & breakdown with the input line if the line is not already included in it
	 * @param string $billrun_key the billrun_key to insert into the billrun
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the input line
	 * @param boolean $vatable is the line vatable or not
	 * @param Billrun_Billrun $billrun whether to update to memory (to billrun) or to the db.
	 * @return Mongodloid_Entity the billrun doc of the line, false if no such billrun exists
	 */
	public static function updateBillrun($billrun_key, $counters, $pricingData, $row, $vatable, $billrun = null) {
		$aid = $row['aid'];
		$sid = $row['sid'];
		$billrun_coll = Billrun_Factory::db()->billrunCollection();

		$usage_type = self::getGeneralUsageType($row['usaget']);
		$row_ref = $row->createRef();
		list($plan_key, $category_key, $zone_key) = self::getBreakdownKeys($row, $pricingData, $vatable);

		if (is_null($billrun)) {
			$query = array_merge_recursive(self::getMatchingBillrunQuery($aid, $billrun_key), self::getOpenBillrunQuery(), self::getDistinctLinesBillrunQuery($sid, $usage_type, $row_ref));
			$update = array_merge_recursive(self::getUpdateCostsQuery($pricingData, $row, $vatable), self::getUpdateDataCountersQuery($usage_type, $row), self::getPushLineQuery($usage_type, $row_ref), self::getUpdateBreakdownQuery($counters, $pricingData, $vatable, $plan_key, $category_key, $zone_key), self::getUpdateTotalsQuery($pricingData, $billrun_key, $vatable));
			$fields = array();
			$options = array();

			try {
				$doc = $billrun_coll->findAndModify($query, $update, $fields, $options);
			} catch (Exception $e) {
				Billrun_Factory::log()->log("Billrun " . $billrun_key . " had a problem when updating " . $aid . ". on  Stamp: " . $row['stamp'] . ' with error :' . $e->getMessage(), Zend_Log::ALERT); // a guess
				return false;
			}

			if ($doc->isEmpty()) { // billrun document was not found
				if (($billrun = self::createBillrunIfNotExists($aid, $billrun_key)) && $billrun->isEmpty()) { // means that the billrun was created so we can retry updating it
					Billrun_Factory::log()->log("Account " . $aid . " has been added to billrun " . $billrun_key, Zend_Log::DEBUG);
					self::addSubscriberIfNotExists($aid, $sid, $billrun_key);
					return self::updateBillrun($billrun_key, $counters, $pricingData, $row, $vatable);
				} else if (self::addSubscriberIfNotExists($aid, $sid, $billrun_key)) {
					Billrun_Factory::log()->log("Subscriber " . $sid . " has been added to billrun " . $billrun_key, Zend_Log::DEBUG);
					return self::updateBillrun($billrun_key, $counters, $pricingData, $row, $vatable);
				} else if (($doc = self::getLineBillrun($aid, $sid, $billrun_key, $usage_type, $row_ref)) && !$doc->isEmpty()) {
					Billrun_Factory::log()->log("Line with stamp " . $row['stamp'] . " already exists in billrun " . $billrun_key . " for account " . $aid, Zend_Log::NOTICE);
					return $doc;
				} else if ($row['type'] == 'flat' || $billrun_key == self::$runtime_billrun_key) { // if it's a flat line we don't want to advance the billrun key
					Billrun_Factory::log()->log("Billrun " . $billrun_key . " is closed for account " . $aid . ". Stamp: " . $row['stamp'], Zend_Log::ALERT); // a guess
					return false;
				} else {
					return self::updateBillrun(self::$runtime_billrun_key, $counters, $pricingData, $row, $vatable);
				}
			}
			Billrun_Factory::log()->log("Line with stamp " . $row['stamp'] . " has been added to billrun " . $billrun_key, Zend_Log::DEBUG);
			return $doc;
		} else { // update to memory
			$billrun->addLineToSubscriber($counters, $row, $pricingData, $vatable, $sid, $billrun_key); 
			$billrun->updateCosts($pricingData, $row, $vatable, $sid); // according to self::getUpdateCostsQuery
			$billrun->updateTotals($pricingData, $billrun_key, $vatable);		
		}
	}

	
	/**
	 * @TODO
	 * @param int $aid the account id
	 * @param int $sid the subscriber id
	 * @param string $billrun_key the billrun_key to insert into the billrun
	 * @param string $status the status of the subscriber
	 * @return mixed Mongodloid_Entity when the insert was successful, true when the line already exists in a billrun and false otherwise
	 */
	public static function setSubscriberStatus($aid, $sid, $billrun_key, $status) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();

		$query = array_merge_recursive(self::getMatchingBillrunQuery($aid, $billrun_key, $sid), self::getOpenBillrunQuery());
		$update = self::getUpdateSubscriberStatusQuery($status);
		$fields = array();
		$options = array();

		$doc = $billrun_coll->findAndModify($query, $update, $fields, $options);

// recovery
		if ($doc->isEmpty()) { // billrun document was not found
			$billrun = self::createBillrunIfNotExists($aid, $billrun_key);
			if ($billrun->isEmpty()) { // means that the billrun was created so we can retry updating it
				self::addSubscriberIfNotExists($aid, $sid, $billrun_key);
				return self::setSubscriberStatus($aid, $sid, $billrun_key, $status);
			} else if (self::addSubscriberIfNotExists($aid, $sid, $billrun_key)) {
				return self::setSubscriberStatus($aid, $sid, $billrun_key, $status);
			} else {
				Billrun_Factory::log()->log("Billrun " . $billrun_key . " is closed for account " . $aid, Zend_Log::ALERT);
				return false;
			}
		}
		return $doc;
	}

	/**
	 * Check whether a line exists in the matching billrun
	 * @param int $aid the account id
	 * @param int $sid the subscriber id
	 * @param string $billrun_key the billrun key
	 * @param string $usage_type the general usage type of the line (output of getGeneralUsageType function)
	 * @param MongoDBRef $line_ref the reference of the line
	 * @return Mongodloid_Entity the relevant billrun document
	 */
	protected static function getLineBillrun($aid, $sid, $billrun_key, $usage_type, $line_ref) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'aid' => $aid,
			'billrun_key' => $billrun_key,
			'invoice_id' => array(
				'$exists' => false,
			),
			'subs' => array(
				'$elemMatch' => array(
					'sid' => $sid,
					'lines.' . $usage_type . '.refs' => array(
						'$in' => array(
							$line_ref
						)
					)
				)
			),
		);
		return $billrun_coll->query($query)->cursor()->limit(1)->current();
	}

	/**
	 * Creates a billrun document in billrun collection if it doesn't already exist
	 * @param int $aid the account id
	 * @param int $billrun_key the billrun key
	 * @return mixed Mongodloid_Entity when the matching billrun document exists, false when inserted
	 */
	public static function createBillrunIfNotExists($aid, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'aid' => $aid,
			'billrun_key' => $billrun_key,
		);
		$update = array(
			'$setOnInsert' => self::getAccountEmptyBillrunEntry($aid, $billrun_key),
		);
		$options = array(
			'upsert' => true,
			'new' => false,
		);
		return $billrun_coll->findAndModify($query, $update, array(), $options);
	}

	/**
	 * Adds an empty subscriber billrun entry to the matching billrun if the account's billrun exists but the subscriber entry doesn't
	 * @param int $aid the account id
	 * @param type $sid the subscriber id of the new entry
	 * @param type $billrun_key the billrun key
	 * @return boolean true when a new entry is inserted, false otherwise
	 */
	protected static function addSubscriberIfNotExists($aid, $sid, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'aid' => $aid,
			'billrun_key' => $billrun_key,
			'$or' => array(
				array(
					'subs.sid' => array(
						'$exists' => false,
					),),
				array(
					'subs' => array(
						'$not' => array(
							'$elemMatch' => array(
								'sid' => $sid,
							),
						),
					),
				),
			),
			'invoice_id' => array(
				'$exists' => false,
			),
		);
		$update = array(
			'$push' => array(
				'subs' => self::getEmptySubscriberBillrunEntry($sid),
			),
		);
		$options = array('w' => 1);
		$output = $billrun_coll->update($query, $update, $options);
		if ($output['ok'] && $output['updatedExisting']) {
			Billrun_Factory::log('Added subscriber ' . $sid . ' to billrun ' . $billrun_key, Zend_Log::INFO);
			return true;
		}
		return false;
	}

	/**
	 * Returns a more general usage type to be used as a key for billrun lines
	 * @param string $specific_usage_type specific usage type (usually lines' 'usaget' field) such as 'call', 'incoming_call' etc.
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
	 * initializes the runtime billrun key of the class
	 */
	static public function initRuntimeBillrunKey() {
		self::$runtime_billrun_key = Billrun_Util::getBillrunKey(time());
	}

	/**
	 * Updates the billrun costs
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @param boolean $vatable is the row vatable
	 * @param array $sraw the subscriber raw data
	 */
	protected function updateCosts($pricingData, $row, $vatable, $sid) {
		$sraw = $this->getSubRawData($sid);
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
		
		$this->setSubRawData($sid, $sraw);
	}
	
	/**
	 * Add pricing and usage counters to the subscriber billrun breakdown.
	 * @param array $counters keys - usage type. values - amount of usage. Currently supports only arrays of one element
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param boolean $vatable is the line vatable or not
	 * @param $sid the subscriber id.
	 * @param string $billrun_key the billrun_key of the billrun
	 */
	protected function addLineToSubscriber( $counters, $row, $pricingData, $vatable, $sid , $billrun_key) {
		$sraw = $this->getSubRawData($sid);
		$usage_type = self::getGeneralUsageType($row['usaget']);
		list($plan_key, $category_key, $zone_key) = self::getBreakdownKeys($row, $pricingData, $vatable);
		$zone = &$sraw['breakdown'][$plan_key][$category_key][$zone_key];
		if ($plan_key != 'credit') {
			if (!empty($counters)) {
				if (!empty($pricingData) && isset($pricingData['over_plan']) && $pricingData['over_plan'] < current($counters)) { // volume is partially priced (in & over plan)
					$volume_priced = $pricingData['over_plan'];
					$planZone = &$sraw['breakdown']['in_plan'][$category_key][$zone_key];
					$planZone['totals'][key($counters)]['usagev'] =  $this->getFieldVal( $planZone,array('totals',key($counters),'usagev'),0) + current($counters) - $volume_priced; // add partial usage to flat
				} else {
					$volume_priced = current($counters);
				}
				$zone['totals'][key($counters)]['usagev'] =  $this->getFieldVal($zone,array('totals',key($counters),'usagev'), 0) + $volume_priced;
				$zone['totals'][key($counters)]['cost'] =  $this->getFieldVal($zone,array('totals',key($counters),'cost'), 0) +  $pricingData['aprice'];

			} 
			if ($plan_key != 'in_plan') {
				$zone['cost'] = $this->getFieldVal($zone,array('cost'), 0) + $pricingData['aprice'];
			}
			$zone['vat'] = ($vatable ? floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18)) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
		} else {
				$zone = $pricingData['aprice'];
		}
		if ($usage_type == 'data' && $row['type'] != 'tap3') {
			$date_key = date("Ymd", $row['urt']->sec);
			$sraw['lines'][$usage_type]['counters'][$date_key] =  $this->getFieldVal($sraw,array('lines',$usage_type,'counters',$date_key), 0) + $row['usagev'];
		} 
		$sraw['lines'][$usage_type]['refs'][] = $row->createRef();
		
		if ($vatable) {
			$sraw['totals']['vatable'] =  $this->getFieldVal($sraw,array('totals','vatable'), 0 ) + $pricingData['aprice'];
			$price_after_vat = $pricingData['aprice'] + ($pricingData['aprice'] *  self::getVATByBillrunKey($billrun_key));
		} else {
			$price_after_vat = $pricingData['aprice'];
		}

		$sraw['totals']['before_vat'] = $this->getFieldVal($sraw,array('totals','before_vat'),0 ) + $pricingData['aprice'];
		$sraw['totals']['after_vat'] = $this->getFieldVal($sraw, array('totals','after_vat'), 0 ) + $price_after_vat;
				
		$this->setSubRawData($sid, $sraw);
	}	
	
	/**
	 * Add pricing  data to the account totals.
	 * @param array $pricingData the output array from updateSubscriberBalance function
	 * @param string $billrun_key the billrun_key to insert into the billrun
	 * @param boolean $vatable is the line vatable or not
	 * @param sraw
	 */
	protected function updateTotals($pricingData, $billrun_key, $vatable ) {
		$rawData= $this->data->getRawData();
		
		if ($vatable) {
			$rawData['totals']['vatable'] = $pricingData['aprice'];
			$vat = self::getVATByBillrunKey($billrun_key);
			$price_after_vat = $pricingData['aprice'] + $pricingData['aprice'] * $vat;
		} else {
			$price_after_vat = $pricingData['aprice'];
		}
		$rawData['totals']['before_vat'] =  $this->getFieldVal($rawData,array('totals','before_vat'),0 ) + $pricingData['aprice'];
		$rawData['totals']['after_vat'] =  $this->getFieldVal($rawData,array('totals','after_vat'), 0) + $price_after_vat;
		
		$this->data->setRawData($rawData);
	}

	protected function getFieldVal($arr,$fields,$defVal) {
		$base = $arr;
		foreach ($fields as $field) {
			if(!isset($base[$field])) {
				return $defVal;
			}
			$base = $base[$field];
		}
		return $base;
	}
	
	static protected $rates = array();
	
	/**
	 * HACK TO MAKE THE BILLLRUN FASTER
	 * gets an array which represents a db ref (includes '$ref' & '$id' keys)
	 * @param type $db_ref
	 */
	protected static function getRowRate($row) {
		$raw_rate = $row->get('arate', true);
		$id_str = strval($raw_rate['$id']);
		if (!isset(self::$rates[$id_str])) {
			 self::$rates[$id_str] = $row->get('arate', false);
		}
		return self::$rates[$id_str];
	}

}

Billrun_Billrun::initRuntimeBillrunKey();