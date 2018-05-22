<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract subscriber class
 *
 * @package  Billing
 * @since    5.3
 */
class Billrun_Balance_Prepaid extends Billrun_Balance {

	protected $connection_type = 'prepaid';

	/**
	 * minimum values for balance usage (on prepaid)
	 * @var array()
	 */
	protected $granted = array();

	/**
	 * Saves the name of the selected balance type cost/usagev/total_cost.
	 * Used to acces remaining balance in the balance object.
	 * 
	 * @var string
	 */
	protected $selectedBalance = '';

	/**
	 * the total key of the balance (on prepaid)
	 * @var string
	 */
	protected $chargingTotalsKey = null;

	
	public function __construct($values = null, $collection = null) {
		parent::__construct($values, $collection);
		$this->selectedBalance = self::getSelectedBalanceKey($this->getRawData());
	}
	
	protected function init() {
		if (isset($this->row['granted_usagev']) && is_numeric($this->row['granted_usagev'])) {
			$this->granted['usagev'] = (-1) * $this->row['granted_usagev'];
		}

		if (isset($this->row['granted_cost']) && is_numeric($this->row['granted_cost'])) {
			$this->granted['cost'] = (-1) * $this->row['granted_cost'];
		}
	}

	protected function loadQuerySort() {
		return array('priority' => -1, 'to' => 1,);
	}

	protected function getBalanceLoadQuery(array $query = array()) {
		$usageType = $this->row['usaget'];
		if (isset($this->granted['usagev'])) {
			$minUsage = $this->granted['usagev'];
		} else {
			$minUsage = (float) Billrun_Factory::config()->getConfigValue('balance.minUsage.' . $usageType, Billrun_Factory::config()->getConfigValue('balance.minUsage', -3, 'float')); // float avoid set type to int
		}

		if (isset($this->granted['cost'])) {
			$minCost = $this->granted['cost'];
		} else {
			$minCost = (float) Billrun_Factory::config()->getConfigValue('balance.minCost' . $usageType, Billrun_Factory::config()->getConfigValue('balance.minCost', -0.1, 'float')); // float avoid set type to int
		}

		$query['$and'] = array();
		$query['$and'][] = array(
			'$or' => array(
				array("balance.totals.$usageType.usagev" => array('$lte' => $minUsage)),
				array("balance.totals.$usageType.cost" => array('$lte' => $minCost)),
				array("balance.cost" => array('$lte' => $minCost)),
			),
		);
		$query['$and'][] = array(
			'$or' => array(
				array('sid' => 0),
				array('sid' => $this->row['sid']),
			),
		);

		$query['aid'] = $this->row['aid'];
		$query['from'] = array('$lte' => $this->row['urt']);
		$query['to'] = array('$gte' => $this->row['urt']);

		$this->applyAllowInRates($query);
		
		Billrun_Factory::dispatcher()->trigger('getBalanceLoadQuery', array(&$query, $this->row, $this));

		return $query;
	}
	
	/**
	 * apply only allowed in rates in pre-paid includes
	 * includes also pre-paid includes entries that support all rates (allowed_in field does not exists)
	 * the filter is using nin operator as it filter out all the entries that does not match
	 * 
	 * @param void
	 */
	protected function applyAllowInRates(&$query) {
		$basePlanName = "BASE";
		$planName = $this->row['plan'];
		$rateName = $this->row['arate_key'];
		
		$disallowedPrepaidIncludesQuery = array('$or' => array(
				array('$and' => array(
						array("allowed_in." . $planName => array('$exists' => 1)),
						array("allowed_in." . $planName => array('$nin' => array($rateName))),
					)),
				array('$and' => array(
						array("allowed_in." . $planName => array('$exists' => 0)),
						array("allowed_in." . $basePlanName => array('$exists' => 1)),
						array("allowed_in." . $basePlanName => array('$nin' => array($rateName))),
					)),
		));
		
		$prepaidIncludesCollection = Billrun_Factory::db()->prepaidincludesCollection();
		$ppIncludes = $prepaidIncludesCollection->query($disallowedPrepaidIncludesQuery)->cursor();
		$disallowedPrepaidIncludes = array();
		if ($ppIncludes->count() > 0) {
			$disallowedPrepaidIncludes = array_map(function($doc) {
				return $doc['external_id'];
			}, iterator_to_array($ppIncludes));
			$query['pp_includes_external_id'] = array('$nin' => array_values($disallowedPrepaidIncludes));
		}
	}

	/**
	 * Gets the key of the current balance
	 * 
	 * @param type $balance
	 * @return string balance key
	 * @todo use only on prepaid; require refactoring
	 */
	public static function getSelectedBalanceKey($balance) {
		$selectedBalance = false;

		if (isset($balance['balance']['totals'])) {
			foreach ($balance['balance']['totals'] as $usageType => $value) {
				foreach (array_keys($value) as $usageBy) {
					$selectedBalance = 'balance.totals.' . $usageType . '.' . $usageBy;
				}
			}
		} else if (isset($balance['balance']['cost'])) {
			$selectedBalance = 'balance.cost';
		}

		return $selectedBalance;
	}

	/**
	 * get the totals key in the balance object 
	 * (in order to support additional types)
	 * For example: we can use "call" balance in "video_call" records
	 * 
	 * @param type $usaget
	 * @return usage type in balance
	 */
	public function getBalanceChargingTotalsKey($usaget) {
		if (is_null($this->chargingTotalsKey)) {
			$query = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(), array("external_id" => $this->get("pp_includes_external_id")));
			$ppincludes = Billrun_Factory::db()->prepaidincludesCollection()->query($query)->cursor()->current();
			if (isset($ppincludes['additional_charging_usaget']) && is_array($ppincludes['additional_charging_usaget']) && in_array($usaget, $ppincludes['additional_charging_usaget'])) {
				$this->chargingTotalsKey = $ppincludes['charging_by_usaget'];
			} else {
				$this->chargingTotalsKey = $usaget;
			}
		}
		return $this->chargingTotalsKey;
	}
	
	/**
	 * method to build update query of the balance
	 * 
	 * @param array $pricingData pricing data array
	 * @param Mongodloid_Entity $row the input line
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * 
	 * @return array update query array (mongo style)
	 */
	public function buildBalanceUpdateQuery(&$pricingData, $row, $volume) {
		list($query, $update) = parent::buildBalanceUpdateQuery($pricingData, $row, $volume);
		$balance_totals_key = $this->getBalanceTotalsKey($pricingData);
		$currentUsage = $this->getCurrentUsage($balance_totals_key);
		$cost = $pricingData[$this->pricingField];
		if (!is_null($this->get('balance.totals.' . $balance_totals_key . '.usagev'))) {
			if ($cost > 0) { // If it's a free of charge, no need to reduce usagev
				$update['$inc']['balance.totals.' . $balance_totals_key . '.usagev'] = $volume;
			}
		} else if (!is_null($this->get('balance.totals.' . $balance_totals_key . '.cost'))) {
			$update['$inc']['balance.totals.' . $balance_totals_key . '.cost'] = $cost;
		} else {
			$update['$inc']['balance.cost'] = $cost;
		}
		$pricingData['usagesb'] = floatval($currentUsage);
		return array($query, $update);
	}
	
	/**
	 * method to get balance totals key
	 * 
	 * @param array $row
	 * @param array $pricingData rate handle
	 * 
	 * @return string
	 */
	public function getBalanceTotalsKey($pricingData) {
		return $this->getBalanceChargingTotalsKey($this->row['usaget']);
	}
	
	/**
	 * method to get the instance of the class (singleton)
	 * 
	 * @param type $params
	 * 
	 * @return Billrun_Balance
	 */
	public static function getInstance($params = null) {
		$stamp = Billrun_Util::generateArrayStamp($params);
		if (empty(self::$instance[$stamp])) {
			if (empty($params)) {
				$params = Yaf_Application::app()->getConfig();
			}
			self::$instance[$stamp] = new Billrun_Balance_Prepaid($params);
		} else {
			if (isset($params['balance_db_refresh']) && $params['balance_db_refresh']) {
				self::$instance[$stamp]->reload();
			}
		}

		return self::$instance[$stamp];
	}

}
