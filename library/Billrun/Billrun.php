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

	protected $account_id;
	protected $billrun_key;
	protected $data;
	protected static $runtime_billrun_key;

	/**
	 * 
	 * @param type $options
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function __construct($options = array()) {
		if (isset($options['account_id']) && isset($options['billrun_key'])) {
			$this->account_id = $options['account_id'];
			$this->billrun_key = $options['billrun_key'];
			if (isset($options['autoload']) && !$options['autoload']) {
				if (isset($options['data']) && !$options['data']->isEmpty()) {
					$this->data = $options['data'];
				} else {
					$this->data = new Mongodloid_Entity($this->getAccountEmptyBillrunEntry($this->account_id, $this->billrun_key));
				}
			} else {
				$this->load();
			}
			$this->data->collection(Billrun_Factory::db()->billrunCollection());
		}
	}

	/**
	 * 
	 * @param type $account_id
	 * @param type $billrun_key
	 * @return \Billrun_Billrun
	 * @todo used only in current balance API. Needs refactoring
	 */
	protected function load() {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$this->data = $billrun_coll->query(array(
					'account_id' => $this->account_id,
					'billrun_key' => $this->billrun_key,
				))
				->cursor()->current();
		$this->data->collection($billrun_coll);
		return $this;
	}

	/**
	 * Add a subscriber to the current billrun entry.
	 * @param type $subscriber_id the  subscriber id  to add.
	 * @return \Billrun_Billrun the current instance  of the billrun entry.
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function addSubscriber($subscriber_id) {
		$subscribers = $this->data['subs'];
		$subscribers[] = $this->getEmptySubscriberBillrunEntry($subscriber_id);
		$this->data['subs'] = $subscribers;
		return $this;
	}

	/**
	 * check if a given subscriber exists in the current billrun.
	 * @param type $subscriber_id the  subscriber id to check.
	 * @return boolean TRUE  is  the subscriber  exists in the current billrun entry FALSE otherwise.
	 * @todo used only in current balance API. Needs refactoring
	 */
	public function exists($subscriber_id) {
		return $this->getSubRawData($subscriber_id) != false;
	}

	/**
	 * Get an empty billrun account  entry structure.
	 * @param type $account_id the account id that the enery belongs to.
	 * @return Array tan empty billrun account  structure.
	 */
	public static function getAccountEmptyBillrunEntry($account_id, $billrun_key) {
		$vat = Billrun_Util::getVAT(Billrun_Util::getEndTime($billrun_key));
		return array(
			'account_id' => $account_id,
			'subs' => array(
			),
			'vat' => $vat,
			'billrun_key' => $billrun_key,
		);
	}

	/**
	 * Get an empty billrun subscriber entry
	 * @return Array an empty billrun subscriber entry
	 */
	public static function getEmptySubscriberBillrunEntry($subscriber_id) {
		return array(
			'sub_id' => $subscriber_id,
			'costs' => array(
				'flat' => self::getVATTypes(),
				'over_plan' => self::getVATTypes(),
				'out_plan' => self::getVATTypes(),
				'credit' => array(
					'charge' => self::getVATTypes(),
					'refund' => self::getVATTypes()
				),
			),
			'lines' => array(
				'call' => array(
					'refs' => array(),
				),
				'sms' => array(
					'refs' => array(),
				),
				'data' => array(
					'counters' => new stdclass,
					'refs' => array(),
				),
				'flat' => array(
					'refs' => array(),
				),
				'mms' => array(
					'refs' => array(),
				),
				'credit' => array(
					'refs' => array(),
				),
			),
			'breakdown' => array(
				'in_plan' => self::getCategories(),
				'over_plan' => self::getCategories(),
				'out_plan' => self::getCategories(),
				'credit' => array(
					'charge_vatable' => new stdclass,
					'charge_vat_free' => new stdclass,
					'refund_vatable' => new stdclass,
					'refund_vat_free' => new stdclass,
				),
			),
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
	public static function close($account_id, $billrun_key, $min_id) {
		$billrun = self::createBillrunIfNotExists($account_id, $billrun_key);
		if (is_null($ret = $billrun->createAutoInc("invoice_id", $min_id))) {
			Billrun_Factory::log()->log("Created invoice " . $ret . " for account " . $account_id, Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Failed to create invoice for account " . $account_id, Zend_Log::INFO);
		}
	}

	/**
	 * 
	 * @param type $subscriber_id
	 * @return mixed
	 * @todo used only in current balance API. Needs refactoring
	 */
	protected function getSubRawData($subscriber_id) {
		foreach ($this->data->get('subs') as $sub_entry) {
			if ($sub_entry['sub_id'] == $subscriber_id) {
				return $sub_entry;
			}
		}
		return false;
	}

	/**
	 * get the account's latest open billrun
	 * @param int $account_id
	 * @return mixed the billrun object or false if none found
	 * @todo used only in current balance API. Needs refactoring.
	 * 
	 */
	public static function getLastOpenBillrun($account_id) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$data = $billrun_coll->query(array(
				'account_id' => $account_id,
				'invoice_id' => array(
					'$exists' => false,
				),
			))
			->cursor()
			->sort(array('billrun_key' => -1))
			->current();
		if ($data->isEmpty()) { // no open billruns for the account
			$data = $billrun_coll->query('account_id', $account_id)
				->cursor()
				->sort(array('billrun_key' => -1))
				->current();
			if ($data->isEmpty()) { // no billruns at all for account
				$billrun_key = Billrun_Util::getBillrunKey(time());
			} else {
				$billrun_key = Billrun_Util::getFollowingBillrunKey($data['billrun_key']);
			}
			$billrun = Billrun_Factory::billrun(array('account_id' => $account_id, 'billrun_key' => $billrun_key, 'autoload' => false));
		} else {
			$billrun = Billrun_Factory::billrun(array('account_id' => $account_id, 'billrun_key' => $data['billrun_key'], 'autoload' => false, 'data' => $data));
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
	 * @param int $account_id the account id
	 * @param int $billrun_key the billrun key
	 * @param int $subscriber_id the subscriber id
	 * @return array the query
	 */
	protected static function getMatchingBillrunQuery($account_id, $billrun_key, $subscriber_id = null) {
		$query = array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
		);
		if (!is_null($subscriber_id)) {
			$query['subs'] = array(
				'$elemMatch' => array(
					'sub_id' => $subscriber_id,
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
	 * @param type $subscriber_id
	 * @param string $usage_type the general usage type of the line (output of getGeneralUsageType function)
	 * @param MongoDBRef $row_ref the reference of the line we wish to insert
	 * @return array the query
	 */
	protected static function getDistinctLinesBillrunQuery($subscriber_id, $usage_type, $row_ref) {
		$query['subs'] = array(
			'$elemMatch' => array(
				'sub_id' => $subscriber_id,
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
	 * @return array the increment costs query
	 */
	protected static function getUpdateCostsQuery($pricingData, $row, $vatable) {
		$vat_key = ($vatable ? "vatable" : "vat_free");
		if (isset($pricingData['over_plan']) && $pricingData['over_plan']) {
			$update['$inc']['subs.$.costs.over_plan.' . $vat_key] = $pricingData['price_customer'];
		} else if (isset($pricingData['out_plan']) && $pricingData['out_plan']) {
			$update['$inc']['subs.$.costs.out_plan.' . $vat_key] = $pricingData['price_customer'];
		} else if ($row['type'] == 'flat') {
			$update['$inc']['subs.$.costs.flat.' . $vat_key] = $pricingData['price_customer'];
		} else if ($row['type'] == 'credit') {
			$update['$inc']['subs.$.costs.credit.' . $row['credit_type'] . '.' . $vat_key] = $pricingData['price_customer'];
		} else {
			$update = array();
		}
		return $update;
	}

	/**
	 * Returns the increment data counters update query
	 * @param string $usage_type the general usage type of the line (output of getGeneralUsageType function)
	 * @param Mongodloid_Entity $row the row to insert to the billrun
	 * @return array the increment data counters query
	 */
	protected static function getUpdateDataCountersQuery($usage_type, $row) {
		if ($usage_type == 'data') {
			$date_key = date("Ymd", $row['unified_record_time']->sec);
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
			$rate = $row['customer_rate'];
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

		if (!isset($zone_key)) {
			$zone_key = $row['customer_rate']['key'];
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
				$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.totals.' . key($counters) . '.cost'] = $pricingData['price_customer'];
				if ($plan_key != 'in_plan') {
					$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.cost'] = $pricingData['price_customer'];
				}
			} else if ($zone_key == 'service') { // flat
				$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.cost'] = $pricingData['price_customer'];
			}
			$update['$set']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key . '.vat'] = ($vatable ? floatval(Billrun_Factory::config()->getConfigValue('pricing.vat', 0.18)) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
		} else {
			$update['$inc']['subs.$.breakdown.' . $plan_key . '.' . $category_key . '.' . $zone_key] = $pricingData['price_customer'];
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
	 * @return Mongodloid_Entity the billrun doc of the line, false if no such billrun exists
	 */
	public static function updateBillrun($billrun_key, $counters, $pricingData, $row, $vatable) {
		$account_id = $row['account_id'];
		$subscriber_id = $row['subscriber_id'];
		$billrun_coll = Billrun_Factory::db()->billrunCollection();

		$usage_type = self::getGeneralUsageType($row['usaget']);
		$row_ref = $row->createRef();
		list($plan_key, $category_key, $zone_key) = self::getBreakdownKeys($row, $pricingData, $vatable);

		$query = array_merge_recursive(self::getMatchingBillrunQuery($account_id, $billrun_key), self::getOpenBillrunQuery(), self::getDistinctLinesBillrunQuery($subscriber_id, $usage_type, $row_ref));
		$update = array_merge_recursive(self::getUpdateCostsQuery($pricingData, $row, $vatable), self::getUpdateDataCountersQuery($usage_type, $row), self::getPushLineQuery($usage_type, $row_ref), self::getUpdateBreakdownQuery($counters, $pricingData, $vatable, $plan_key, $category_key, $zone_key));
		$fields = array();
		$options = array();

		$doc = $billrun_coll->findAndModify($query, $update, $fields, $options);

		// recovery
		if ($doc->isEmpty()) { // billrun document was not found
			if (($billrun = self::createBillrunIfNotExists($account_id, $billrun_key)) && $billrun->isEmpty()) { // means that the billrun was created so we can retry updating it
				return self::updateBillrun($billrun_key, $counters, $pricingData, $row, $vatable);
			} else if (self::addSubscriberIfNotExists($account_id, $subscriber_id, $billrun_key)) {
				return self::updateBillrun($billrun_key, $counters, $pricingData, $row, $vatable);
			} else if (($doc = self::getLineBillrun($account_id, $subscriber_id, $billrun_key, $usage_type, $row_ref)) && !$doc->isEmpty()) {
				Billrun_Factory::log()->log("Line with stamp " . $row['stamp'] . " already exists in billrun " . $billrun_key . " for account " . $account_id, Zend_Log::NOTICE);
				return $doc;
			} else if ($row['type'] == 'flat' || $billrun_key == self::$runtime_billrun_key) { // if it's a flat line we don't want to advance the billrun key
				Billrun_Factory::log()->log("Billrun " . $billrun_key . " is closed for account " . $account_id, Zend_Log::ALERT);
				return false;
			} else {
				return self::updateBillrun(self::$runtime_billrun_key, $counters, $pricingData, $row, $vatable);
			}
		}
		return $doc;
	}

	/**
	 * Updates the billrun costs, lines & breakdown with the input line if the line is not already included in it
	 * @param int $account_id the account id
	 * @param int $subscriber_id the subscriber id
	 * @param string $billrun_key the billrun_key to insert into the billrun
	 * @param string $status the status of the subscriber
	 * @return mixed Mongodloid_Entity when the insert was successful, true when the line already exists in a billrun and false otherwise
	 */
	public static function setSubscriberStatus($account_id, $subscriber_id, $billrun_key, $status) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();

		$query = array_merge_recursive(self::getMatchingBillrunQuery($account_id, $billrun_key, $subscriber_id), self::getOpenBillrunQuery());
		$update = self::getUpdateSubscriberStatusQuery($status);
		$fields = array();
		$options = array();

		$doc = $billrun_coll->findAndModify($query, $update, $fields, $options);

		// recovery
		if ($doc->isEmpty()) { // billrun document was not found
			$billrun = self::createBillrunIfNotExists($account_id, $billrun_key);
			if ($billrun->isEmpty()) { // means that the billrun was created so we can retry updating it
				return self::setSubscriberStatus($account_id, $subscriber_id, $billrun_key, $status);
			} else if (self::addSubscriberIfNotExists($account_id, $subscriber_id, $billrun_key)) {
				return self::setSubscriberStatus($account_id, $subscriber_id, $billrun_key, $status);
			} else {
				Billrun_Factory::log()->log("Billrun " . $billrun_key . " is closed for account " . $account_id, Zend_Log::ALERT);
				return false;
			}
		}
		return $doc;
	}

	/**
	 * Check whether a line exists in the matching billrun
	 * @param int $account_id the account id
	 * @param int $subscriber_id the subscriber id
	 * @param string $billrun_key the billrun key
	 * @param string $usage_type the general usage type of the line (output of getGeneralUsageType function)
	 * @param MongoDBRef $line_ref the reference of the line
	 * @return Mongodloid_Entity the relevant billrun document
	 */
	protected static function getLineBillrun($account_id, $subscriber_id, $billrun_key, $usage_type, $line_ref) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
			'invoice_id' => array(
				'$exists' => false,
			),
			'subs' => array(
				'$elemMatch' => array(
					'sub_id' => $subscriber_id,
					'lines.' . $usage_type . '.refs' => array(
						'$in' => array(
							$line_ref
						)
					)
				)
			),
		);
		return $billrun_coll->query($query)->cursor()->current();
	}

	/**
	 * Creates a billrun document in billrun collection if it doesn't already exist
	 * @param int $account_id the account id
	 * @param int $billrun_key the billrun key
	 * @return Mongodloid_Entity the matching billrun document (new or existing)
	 */
	public static function createBillrunIfNotExists($account_id, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
		);
		$update = array(
			'$setOnInsert' => self::getAccountEmptyBillrunEntry($account_id, $billrun_key),
		);
		$options = array(
			'upsert' => true,
			'new' => false,
		);
		return $billrun_coll->findAndModify($query, $update, array(), $options);
	}

	/**
	 * Adds an empty subscriber billrun entry to the matching billrun if the account's billrun exists but the subscriber entry doesn't
	 * @param int $account_id the account id
	 * @param type $subscriber_id the subscriber id of the new entry
	 * @param type $billrun_key the billrun key
	 * @return boolean true when a new entry is inserted, false otherwise
	 */
	protected static function addSubscriberIfNotExists($account_id, $subscriber_id, $billrun_key) {
		$billrun_coll = Billrun_Factory::db()->billrunCollection();
		$query = array(
			'account_id' => $account_id,
			'billrun_key' => $billrun_key,
			'$or' => array(
				array(
					'subs.sub_id' => array(
						'$exists' => false,
					),),
				array(
					'subs' => array(
						'$not' => array(
							'$elemMatch' => array(
								'sub_id' => $subscriber_id,
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
				'subs' => self::getEmptySubscriberBillrunEntry($subscriber_id),
			),
		);
		$options = array(
			'w' => 1,
		);
		$output = $billrun_coll->update($query, $update, $options);
		if ($output['ok'] && $output['updatedExisting']) {
			Billrun_Factory::log('Added subscriber ' . $subscriber_id . ' to billrun ' . $billrun_key, Zend_Log::INFO);
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

}

Billrun_Billrun::initRuntimeBillrunKey();