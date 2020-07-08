<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Billrun class
 *
 * @package  Billrun
 * @since    0.5
 */
class Billrun_Billrun {

	static public $accountsLines = array();
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
		$this->lines = Billrun_Factory::db()->linesCollection();
		$this->billrun_coll = Billrun_Factory::db(array('name' => 'billrun'))->billrunCollection();

		if (isset($options['aid']) && isset($options['billrun_key'])) {
			$this->aid = $options['aid'];
			$this->billrun_key = $options['billrun_key'];
			$this->vat = self::getVATByBillrunKey($this->billrun_key);
			if (isset($options['autoload']) && !$options['autoload']) {
				if (isset($options['data']) && !$options['data']->isEmpty()) {
					$this->data = $options['data'];
				} else {
					$this->resetBillrun();
				}
			} else {
				$this->load();
				if ($this->data->isEmpty()) {
					$this->resetBillrun();
				}
			}
			$this->data->collection($this->billrun_coll);
		} else {
			Billrun_Factory::log()->log("Returning an empty billrun!", Zend_Log::NOTICE);
		}
		if (isset($options['filter_fields'])) {
			$this->filter_fields = array_map("intval", $options['filter_fields']);
		}
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
                $this->data['creation_time'] = new MongoDate();
				$this->data->save(NULL, 1);
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
		$plans = $subscriber->getPlans();
		if (is_null($plans)) {
			Billrun_Factory::log()->log("Null current plan for subscriber $subscriber->sid", Zend_Log::INFO);
			$plans = array();
		}
		$subscribers = $this->data['subs'];
		$subscriber_entry = $this->getEmptySubscriberBillrunEntry($subscriber->sid);
		$subscriber_entry['subscriber_status'] = $status;
		$subscriber_entry['activation_start'] = $subscriber->getActivationStartDay();
		$subscriber_entry['activation_end'] = $subscriber->getActivationEndDay();
		$subscriber_entry['fraction'] = $subscriber->calcFractionOfMonth($subscriber_entry['activation_start'], $subscriber_entry['activation_end'], $subscriber->sid);
		if ($subscriber->isFreezeExists()) {
			$subscriber_entry['freeze_start_date'] = $subscriber->getFreezeStartDay();
			$subscriber_entry['freeze_end_date'] = $subscriber->getFreezeEndDay();
		}
		foreach ($subscriber->getExtraFieldsForBillrun() as $field => $save) {
			if ($field == !$save) {
				continue;
			}
			$subscriber_entry[$field] = $subscriber->{$field};
		}
		foreach ($plans as $key => $plan) {
			$subscriber->setPlanName($plan['plan']);
			$subscriber_entry['plans'][$key]['current_plan'] = $subscriber->getPlan()->createRef();
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
	 * filter out subscribers that have no plans and no lines
	 * @param int $sid the  subscriber id to check.
	 * @return boolean TRUE if the subscriber exists in the current billrun entry, FALSE otherwise.
	 */
	public function filter_disconected_subscribers($deactivated_subscribers) {
		$subscribers = $this->data['subs'];
		foreach ($subscribers as $key => $sub) {
			foreach ($deactivated_subscribers as $ds) {
				if ($ds['sid'] == $sub['sid']) {
					unset($subscribers[$key]);
				}
			}
		}
		$this->data['subs'] = array_values($subscribers);
	}

	/**
	 * Checks if a billrun document exists in the db
	 * @param int $aid the account id
	 * @param string $billrun_key the billrun key
	 * @return boolean true if yes, false otherwise
	 */
	public static function exists($aid, $billrun_key) {
		$billrun_coll = Billrun_Factory::db(array('name' => 'billrun'))->billrunCollection();
		$data = $billrun_coll->query(array(
					'aid' => (int) $aid,
					'billrun_key' => (string) $billrun_key,
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
		$billrunEntity = $this->getRawData();
		$ret = $this->getInvoiceId($billrunEntity['totals']['after_vat']);
		if (is_null($ret)) {
			Billrun_Factory::log()->log("Failed to create invoice for account " . $this->aid, Zend_Log::INFO);
		} else {
			Billrun_Factory::log()->log("Created invoice " . $ret . " for account " . $this->aid, Zend_Log::INFO);
			$billrunEntity->set('invoice_id', $ret);
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
		} else if ($row['type'] == 'service') {
			$plan_key = 'service';
			$zone_key = $row['key'];
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
						$zone_key = $rate['key'];
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
			case 'service':
				return 'service';
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
			if (isset($pricingData['aprice_no_vat']) && $pricingData['aprice_no_vat'] > 0) {
				if (!isset($sraw['costs']['flat']['vat_free'])) {
					$sraw['costs']['flat']['vat_free'] = $pricingData['aprice_no_vat'];
				} else {
					$sraw['costs']['flat']['vat_free'] += $pricingData['aprice_no_vat'];
				}
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
		$uniquePlanId = null;
		$usage_type = self::getGeneralUsageType($row['usaget']);
		list($plan_key, $category_key, $zone_key) = self::getBreakdownKeys($row, $pricingData, $vatable);
		if (isset($row['unique_plan_id'])) {
			$uniquePlanId = $row['unique_plan_id'];
			$zone = &$sraw['breakdown'][$row['plan']][$uniquePlanId][$plan_key][$category_key][$zone_key];
		} else {
			$zone = &$sraw['breakdown'][$row['plan']][$plan_key][$category_key][$zone_key];
		}
		if ($plan_key != 'credit') {
			if (!empty($counters)) {
				if (isset($pricingData['over_plan']) && $pricingData['over_plan'] < current($counters)) { // volume is partially priced (in & over plan)
					$volume_priced = $pricingData['over_plan'];
					if (!empty($uniquePlanId)) {
						$planZone = &$sraw['breakdown'][$row['plan']][$uniquePlanId]['in_plan'][$category_key][$zone_key];
					} else {
						$planZone = &$sraw['breakdown'][$row['plan']]['in_plan'][$category_key][$zone_key];
					}
					$planZone['totals'][key($counters)]['usagev'] = $this->getFieldVal($planZone['totals'][key($counters)]['usagev'], 0) + current($counters) - $volume_priced; // add partial usage to flat
					$planZone['totals'][key($counters)]['cost'] = $this->getFieldVal($planZone['totals'][key($counters)]['cost'], 0);
					$planZone['totals'][key($counters)]['count'] = $this->getFieldVal($planZone['totals'][key($counters)]['count'], 0) + 1;
					$planZone['vat'] = ($vatable ? floatval($this->vat) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
					if (($row['in_plan'] && (!empty($row['arategroup']) && ($row['plan'] == $row['arategroup']) || !empty($row['plan_usage'])))) {
						$rowPlanUsage = !empty($row['plan_usage']) ? $row['plan_usage'] : $row['in_plan'];
						$planZone['totals'][key($counters)]['plan_usagev'] = $this->getFieldVal($planZone['totals'][key($counters)]['plan_usagev'], 0) + $rowPlanUsage; 
					}
				} else {
					$volume_priced = current($counters);
				}
				if (($row['in_plan'] && !isset($pricingData['over_plan']) && (!empty($row['arategroup']) && ($row['plan'] == $row['arategroup']) || !empty($row['plan_usage'])))) {
					$rowPlanUsage = !empty($row['plan_usage']) ? $row['plan_usage'] : $row['in_plan'];
					$zone['totals'][key($counters)]['plan_usagev'] = $this->getFieldVal($zone['totals'][key($counters)]['plan_usagev'], 0) + $rowPlanUsage; 
				}
				$zone['totals'][key($counters)]['usagev'] = $this->getFieldVal($zone['totals'][key($counters)]['usagev'], 0) + $volume_priced;
				$zone['totals'][key($counters)]['cost'] = $this->getFieldVal($zone['totals'][key($counters)]['cost'], 0) + $pricingData['aprice'];
				$zone['totals'][key($counters)]['count'] = $this->getFieldVal($zone['totals'][key($counters)]['count'], 0) + 1;
				if($row['type'] == 'ggsn') {
					if(isset($row['rat_type']) && $row['rat_type'] == '06') {
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
				if (isset($pricingData['aprice_no_vat'])) {
					$zone['cost_without_vat'] = $this->getFieldVal($zone['cost_without_vat'], 0) + $pricingData['aprice_no_vat'];
				}
			}
			$zone['vat'] = ($vatable ? floatval($this->vat) : 0); //@TODO we assume here that all the lines would be vatable or all vat-free
		} else {
			$zone += $pricingData['aprice'];
		}
		if (isset($row['arategroup'])) {
			if (isset($row['in_plan'])) {
				$sraw['groups'][$row['arategroup']]['in_plan']['totals'][key($counters)]['usagev'] = $this->getFieldVal($sraw['groups'][$row['arategroup']]['in_plan']['totals'][key($counters)]['usagev'], 0) + $row['in_plan'];
				if(!empty($row['vf_count_days'])) {
				$sraw['groups'][$row['arategroup']]['in_plan']['totals']['vf_count_days'] = @max($sraw['groups'][$row['arategroup']]['in_plan']['totals']['vf_count_days'],$row['vf_count_days']);
			}
			}
			if (isset($row['over_plan'])) {
				$sraw['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['usagev'] = $this->getFieldVal($sraw['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['usagev'], 0) + $row['over_plan'];
				$sraw['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['cost'] = $this->getFieldVal($sraw['groups'][$row['arategroup']]['over_plan']['totals'][key($counters)]['cost'], 0) + $row['aprice'];
			}
		}
		if ($usage_type == 'data' && $row['type'] != 'tap3') {
			$date_key = date("Ymd", $row['urt']->sec);
			$sraw['lines'][$usage_type]['counters'][$date_key]['usagev'] = $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key]['usagev'], 0) + $row['usagev'];
			$sraw['lines'][$usage_type]['counters'][$date_key]['aprice'] = $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key]['aprice'], 0) + $row['aprice'];
			$sraw['lines'][$usage_type]['counters'][$date_key]['plan_flag'] = $this->getDayPlanFlagByDataRow($row, $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key]['plan_flag'], 'in'));
			if($row['type'] == 'ggsn') {
				if (isset($row['rat_type']) && $row['rat_type'] == '06') {
					$data_generation = 'usage_4g';
				} else {
					$data_generation = 'usage_3g';
				}
				$sraw['lines'][$usage_type]['counters'][$date_key][$data_generation]['usagev'] = $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key][$data_generation]['usagev'], 0) + $row['usagev'];
				$sraw['lines'][$usage_type]['counters'][$date_key][$data_generation]['aprice'] = $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key][$data_generation]['aprice'], 0) + $row['aprice'];
				$sraw['lines'][$usage_type]['counters'][$date_key][$data_generation]['plan_flag'] = $this->getDayPlanFlagByDataRow($row, $this->getFieldVal($sraw['lines'][$usage_type]['counters'][$date_key][$data_generation]['plan_flag'], 'in'));

			}
		}

		if ($vatable) {
			$sraw['totals']['vatable'] = $this->getFieldVal($sraw['totals']['vatable'], 0) + $pricingData['aprice'];
			$price_after_vat = $pricingData['aprice'] + ($pricingData['aprice'] * self::getVATByBillrunKey($billrun_key)) + (isset($pricingData['aprice_no_vat']) ? $pricingData['aprice_no_vat'] : 0);
		} else {
			$price_after_vat = $pricingData['aprice'] + (isset($pricingData['aprice_no_vat']) ? $pricingData['aprice_no_vat'] : 0);
		}
		$sraw['totals']['before_vat'] = $this->getFieldVal($sraw['totals']['before_vat'], 0) + $pricingData['aprice'] + (isset($pricingData['aprice_no_vat']) ? $pricingData['aprice_no_vat'] : 0);
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
		if (empty($id)) {
			return;
		}
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
		if (empty($id)) {
			return false;
		}
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
		$rates = $rates_coll->query()->cursor();
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
		$plans = $plans_coll->query()->cursor();
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
	public function addLines($manual_lines = array(), &$deactivated_subscribers = array()) {
		Billrun_Factory::log()->log("Querying account " . $this->aid . " for lines...", Zend_Log::INFO);
		$account_lines = $this->getAccountLines($this->aid);

		$lines = array_merge($account_lines, $manual_lines);
		$this->filterSubscribers($lines, $deactivated_subscribers);
		Billrun_Factory::log("Processing account Lines $this->aid", Zend_Log::INFO);

		$updatedLines = $this->processLines(array_values($lines));
		Billrun_Factory::log("Finished processing account $this->aid lines. Total: " . count($updatedLines), Zend_log::INFO);
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
			$line->collection($this->lines);
			$pricingData = array('aprice' => $line['aprice']);
			if (isset($line['over_plan'])) {
				$pricingData['over_plan'] = $line['over_plan'];
			} else if (isset($line['out_plan'])) {
				$pricingData['out_plan'] = $line['out_plan'];
			}
			
			if (isset($line['aprice_no_vat'])) {
				$pricingData['aprice_no_vat'] = $line['aprice_no_vat'];
			}
			if ($line['total_aprice']) {
				$pricingData['total_aprice'] = $line['total_aprice'];
			}

			if ($line['type'] != 'flat') {
				$rate = $this->getRowRate($line);
				$vatable = (!(isset($rate['vatable']) && !$rate['vatable']) || (!isset($rate['vatable']) && !$this->vatable));
				$this->updateBillrun($this->billrun_key, array($line['usaget'] => $line['usagev']), $pricingData, $line, $vatable);
			} else {
				$plan_ref = $line->get('plan_ref', true);
				if (!empty($plan_ref)) {
					$plan = self::getPlanById(strval($plan_ref['$id']));
					$this->updateBillrun($this->billrun_key, array(), $pricingData, $line, $plan->get('vatable'));
				} else {
					Billrun_Factory::log()->log("No plan or unrecognized plan for row " . $line['stamp'] . " Subscriber " . $line['sid'], Zend_Log::ALERT);
				}
			}
			//Billrun_Factory::log("Done Processing account Line for $sid : ".  microtime(true));
			$updatedLines[$line['stamp']] = $line;
		}
		return $updatedLines;
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
					Billrun_Factory::log()->log("Subscriber " . $ds['sid'] . " has current plan null and next plan null, yet has lines", Zend_Log::NOTICE);
					unset($deactivated_subscribers[$key]);
				}
			}
		}
	}

	/**
	 * Gets all the account lines for this billrun from the db
	 * @param int $aid the account id
	 * @param array $excluded_stamps exclude lines with these stamps from the search
	 * @return Mongodloid_Cursor the mongo cursor used to iterate over the lines
	 * @todo remove aid parameter
	 */
	protected function getAccountLines($aid) {
		if (!isset(static::$accountsLines[$aid])) {
			$ret = $this->loadAccountsLines(array($aid), $this->billrun_key, $this->filter_fields);
		} else {
			$ret = &static::$accountsLines;
		}

		return isset($ret[$aid]) ? $ret[$aid] : array();
	}

	/**
	 *  preload  and saves accounts lines to a static structure.
	 * @param type $aids the account to get the lines for.
	 * @param type $billrun_key the billrun key  that the lines should be in.
	 * @param type $filter_fields the fileds that the returned lines need to have.
	 * @param array $excluded_stamps stamps excluded from the search
	 */
	static public function preloadAccountsLines($aids, $billrun_key, $filter_fields = FALSE) {
		static::$accountsLines = static::$accountsLines + static::loadAccountsLines($aids, $billrun_key, $filter_fields);
	}

	/**
	 * Gets all the account lines for this billrun from the db
	 * @param type $aids the account to get the lines for.
	 * @param type $billrun_key the billrun key  that the lines should be in.
	 * @param type $filter_fields the fileds that the returned lines need to have.
	 * @param array $excluded_stamps stamps excluded from the search
	 * @return an array containing all the  accounts with thier lines.
	 */
	static public function loadAccountsLines($aids, $billrun_key, $filter_fields = FALSE) {
		if (empty($aids)) {
			return;
		}

		$ret = array();
		$query = array(
			'aid' => array('$in' => $aids),
			'billrun' => $billrun_key
		);

		$requiredFields = array('aid' => 1);
		$filter_fields = empty($filter_fields) ? Billrun_Factory::config()->getConfigValue('billrun.filter_fields', array()) : $filter_fields;

		$sort = array(
			'urt' => 1,
		);

		Billrun_Factory::log()->log('Querying for accounts ' . implode(',', $aids) . ' lines', Zend_Log::INFO);
		$addCount = $bufferCount = 0;
		do {
			$bufferCount += $addCount;
			$cursor = Billrun_Factory::db()->linesCollection()
//			$cursor = Billrun_Factory::db(array('host'=>'172.28.202.111','port'=>27017,'user'=>'reading','password'=>'guprgri','name'=>'billing','options'=>array('connect'=>1,'readPreference'=>MongoClient::RP_SECONDARY_PREFERRED)))->linesCollection()
				->query($query)->cursor()->fields(array_merge($filter_fields, $requiredFields))
				->sort($sort)->skip($bufferCount)->limit(Billrun_Factory::config()->getConfigValue('billrun.linesLimit', 10000))->timeout(-1);
			foreach ($cursor as $line) {
				$ret[$line['aid']][$line['stamp']] = $line;
			}
		} while (($addCount = $cursor->count(true)) > 0);
		Billrun_Factory::log()->log('Finished querying for accounts ' . implode(',', $aids) . ' lines', Zend_Log::INFO);
		foreach ($aids as $aid) {
			if (!isset($ret[$aid])) {
				$ret[$aid] = array();
			}
		}
		return $ret;
	}

	/**
	 * Remove account lines from the preload cache.
	 * @param $aids a list of  aids to remove  of FALSE to remove all the  cached account lines. 	 
	 */
	static public function clearPreLoadedLines($aids = FALSE) {
		if ($aids === FALSE) {
			static::$accountsLines = array();
		} else {
			foreach ($aids as $aid) {
				unset(static::$accountsLines[$aid]);
			}
		}
	}

	/**
	 * Resets the billrun data. If an invoice id exists, it will be kept.
	 */
	public function resetBillrun() {
		$empty_billrun_entry = $this->getAccountEmptyBillrunEntry($this->aid, $this->billrun_key);
		$invoice_id_field = (isset($this->data['invoice_id']) ? array('invoice_id' => $this->data['invoice_id']) : array());
		$id_field = (isset($this->data['_id']) ? array('_id' => $this->data['_id']->getMongoID()) : array());
		$this->data = new Mongodloid_Entity(array_merge($empty_billrun_entry, $invoice_id_field, $id_field), $this->billrun_coll);
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
		$last = Billrun_Factory::db(array('name' => 'billrun'))->billrunCollection()->query()->cursor()->limit(1)->fields($fields)->sort($sort)->current();
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

	/**
	 * returns true if account has no active subscribers and no relevant lines for next billrun
	 * @return true if account is deactivated (causes no xml to be produced for this account)
	 */
	public function is_deactivated() {
		$deactivated = $this->data['totals']['after_vat'] === 0;
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
	
	protected function getTranslationValue($paramObj, $invoiceGroup, $formerTranslations) {
		if (!isset($paramObj['type']) || !isset($paramObj['value'])) {
			Billrun_Factory::log("Missing invoice_id params definitions when generating invoice id", Zend_Log::DEBUG);
		}
		switch ($paramObj['type']) {
			case 'date':
				$dateValue = ($paramObj['value'] == 'cycle') ? $this->getBillrunKey() : (($paramObj['value'] == 'now') ?  time() : strtotime($paramObj['value']));
				return substr($dateValue, 0, 4);	
			case 'autoinc':
				if (!isset($paramObj['min_value']) && !isset($paramObj['max_value'])) {
					Billrun_Factory::log("Missing range when generating invoice id", Zend_Log::DEBUG);
					return;
				}
				if (!isset($formerTranslations['param1_date']) && !isset($formerTranslations['param2_id'])) {
					Billrun_Factory::log("Missing group value params when generating invoice id", Zend_Log::DEBUG);
					return;
				}
				$minValue = intval($paramObj['min_value']);
				$maxValue = intval($paramObj['max_value']);		
				$group = $formerTranslations['param1_date'] . $formerTranslations['param2_id'];
				$fakeCollectionName = '$invoice_id_generation_' . $group;
				$oid = $group . $this->getAccountId() . $this->getBillrunKey();
				$seq = Billrun_Factory::db(array('name' => 'billrun'))->countersCollection()->createAutoInc($oid, $minValue, $fakeCollectionName);
				if ($seq > $maxValue) {
					throw new Exception("Sequence exceeded max value when generating invoice id");
				}		
				return str_pad($seq, $paramObj['length'], '0', STR_PAD_LEFT);
			case 'identifier':
				if ($paramObj['value'] == 'by_type') {
					$padLength = $paramObj['length'];
					return str_pad($invoiceGroup, $padLength, '0', STR_PAD_LEFT);
				}
			default:
				Billrun_Factory::log("Unsupported invoice_id params", Zend_Log::DEBUG);
				break;
		}
	}
	
	protected function getInvoiceId($totalAmount) {
		$invoiceIdStructure = Billrun_Config::getInstance()->getConfigValue('customer.aggregator.invoice_id_pattern', '');
		if (empty($invoiceIdStructure)) {
			Billrun_Factory::log()->log("Missing invoice id structure", Zend_Log::ALERT);
		}
		$invoiceIdParams = Billrun_Config::getInstance()->getConfigValue('customer.aggregator.invoice_id_params', array());
		$translations = array();
		$invoiceGroup = $totalAmount < 0 ? '29' : '31';
		foreach ($invoiceIdParams as $paramObj) {
			$translations[$paramObj['param']] = $this->getTranslationValue($paramObj, $invoiceGroup, $translations);
		}
		return Billrun_Util::translateTemplateValue($invoiceIdStructure, $translations, null, true);
	}
	
	protected function getAccountId() {
		return $this->aid;
	}
}

Billrun_Billrun::loadRates();
Billrun_Billrun::loadPlans();
