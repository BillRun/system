<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * compatible for PHP 5.4
 */
if (!function_exists('array_column')):

	function array_column(array $input, $column_key, $index_key = null) {

		$result = array();
		foreach ($input as $k => $v)
			$result[$index_key ? $v[$index_key] : $k] = $v[$column_key];

		return $result;
	}

endif;

/**
 * Fraud plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class fraudPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'fraud';

	/**
	 * Method to save fraud events on the fraud system once event triggered
	 * 
	 * @param array $row the line from lines collection
	 * @param Mongodloid_Entity $balance 
	 * @param string $usage_type, the usage type could be: call, data, mms, sms
	 * @param array $rate
	 * @param string $volume
	 * @param Billrun_Calculator $calculator
	 * 
	 * @return boolean in case we are on chain return true if all ok and chain can continue, else return false if require to stop the plugin chain
	 * 
	 */
	public function afterUpdateSubscriberBalance($row, $balance, $rowPrice, $calculator) {

		// if not plan to row - cannot do acannotnything
		if (!isset($row['plan'])) {
			Billrun_Factory::log("Fraud plugin - plan not exists for line " . $row['stamp'], Zend_Log::INFO);
			return true;
		}

		// first check we are not on tap3, because we prevent intl fraud on nrtrde
		if ($row['type'] == 'tap3') {
			return true;
		}

		$thresholds = Billrun_Factory::config()->getConfigValue('fraud.thresholds', array());

		foreach ($thresholds as $type => $limits) {
			switch ($type) {
				case 'cost':
					$this->costCheck($limits, $row, $balance, $rowPrice);
					break;
				case 'usage':
					$this->usageCheck($limits, $row, $balance);
					break;
				default:
					Billrun_Factory::log("Fraud plugin - method doesn't exists " . $method, Zend_Log::WARN);
					break;
			}
		}

		return true;
	}

	protected function costCheck($limits, $row, $balance, $rowPrice) {
		if ($rowPrice === 0) {
			return;
		}

		if (isset($limits['usaget'])) {
			$filterTypes = $limits['usaget'];
		} else {
			$filterTypes = false;
		}

		$price_before = $this->calculateCost($balance->balance, $filterTypes);
		$price_after = $price_before + $rowPrice;
		foreach ($limits['rules'] as $rule) {
			$this->checkRule($rule, $row, $price_before, $price_after);
		}
	}

	/**
	 * method to calculate total cost by balance with option to exclude
	 * 
	 * @param Mongodloid_Entity  $balance  the balance details
	 * @param array              $filter   filter the array by keys
	 * 
	 * @return int
	 * 
	 */
	protected function calculateCost($balance, $filter = false) {
		if (empty($balance) || !isset($balance['cost'])) {
			return 0;
		}
		$costFieldName = 'cost';
		$totalCost = $balance[$costFieldName];
		if (!empty($filter)) {
			return $this->sumBalance($balance,$costFieldName, $filter );
		}
		return $totalCost;

	}

	/**
	 * method to sum balance of specific column (usage or cost) with option to filter by usage type
	 * 
	 * @param Mongodloid_Entity  $balance   the balance details
	 * @param string             $sumField  the column field to sum
	 * @param array              $filter    filter the array by keys
	 * @return int
	 */
	protected function sumBalance($balance, $sumField, $filter = array()) {
		if (count($filter) == 1) {
			// if filter only one don't array make the array manipulation
			Billrun_Factory::log(print_r($filter,1), Zend_Log::INFO);
			return $balance['totals'][$filter[0]][$sumField];
		}

		if (!empty($filter)) {
			$costArray = array_intersect_key($balance['totals'], array_combine($filter, $filter));
		} else {
			$costArray = $balance['totals'];
		}

		$totalArray = array_column($costArray, $sumField);
		if (empty($totalArray)) {
			return 0;
		}

		return array_sum($totalArray);
	}

	protected function usageCheck($limits, $row, $balance) {

		if ($row['usagev'] === 0) {
			return false;
		}
		
		$usaget = $row['usaget'];

		if (!isset($balance->balance['totals'][$usaget]['usagev'])) {
			Billrun_Factory::log("Fraud plugin - balance not exists for subscriber " . $row['sid'] . ' usage type ' . $usaget, Zend_Log::WARN);
			return false;
		}
		$balance_before_change = $balance->balance['totals'][$usaget]['usagev'];
		$balance_after_change = $balance_before_change + $row['usagev'];

		foreach ($limits['rules'] as $rule) {
			return $this->checkRule($rule, $row, $balance_before_change, $balance_after_change);
		}

		return false;
	}

	/**
	 * check if fraud rule triggered
	 * 
	 * @param array $rule the array rule of settings
	 * @param array $row the row to check the rule
	 * @param number $before the value before the change
	 * @param number $after the value after the change
	 * 
	 * @return mixed return the rule array if succeed, else false
	 */
	protected function checkRule($rule, $row, $before, $after) {
		// if the limit for specific type
		if (!isset($row['usaget']) || (!empty($rule['usaget']) && !in_array( $row['usaget'],$rule['usaget']))) {
			return false;
		}
		// if the limit for specific plans
		if (isset($rule['limitPlans']) &&
			(is_array($rule['limitPlans']) && !in_array($row['plan'], $rule['limitPlans']))) {
			return false;
		}

		$threshold = $rule['threshold'];
		$recurring = isset($rule['recurring']) && $rule['recurring'];
		if ($this->isThresholdTriggered($before, $after, $threshold, $recurring)) {
			$this->insert_fraud_event($after, $before, $row, $threshold, $rule['unit'], $rule['name']);
			Billrun_Factory::log("Fraud plugin - trigger event " . $row['stamp'] . ' with event name ' .  $rule['name'], Zend_Log::CRIT);
			return $rule;
		}
	}

	/**
	 * method to check if threshold passed between two value
	 * 
	 * @param float $before the value before
	 * @param float $after the value after
	 * @param float $threshold the threshold to pass
	 * @param boolean $recurring if true it will check with iterating of the threshold
	 * 
	 * @return boolean true if the threshold passed the value
	 */
	protected function isThresholdTriggered($before, $after, $threshold, $recurring = false) {
		if ($recurring) {
			return (floor($before / $threshold) < floor($after / $threshold));
//			return ($usage_before % $threshold > $usage_after % $threshold || ($usage_after-$usage_before) > $threshold);
		}
		return ($before < $threshold) && ($threshold < $after);
	}

	protected function insert_fraud_event($value, $value_before, $row, $threshold, $units, $event_type, $fraud_connection = null, $fraud_connection_options = array()) {

		if(!$fraud_connection) {
			$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->eventsCollection();
		}

		$newEvent = new Mongodloid_Entity();
		$newEvent['value'] = $value;
		$newEvent['value_before'] = $value_before;
		$newEvent['creation_time'] = date(Billrun_Base::base_dateformat);
		$newEvent['aid'] = $row['aid'];
		$newEvent['sid'] = $row['sid'];
		$newEvent['source'] = 'billing';
		$newEvent['threshold_usagev'] = $threshold;
		$newEvent['units'] = $units;
		$newEvent['event_type'] = $event_type;
		$newEvent['stamp'] = md5(serialize($newEvent));
		
		try {
			$insertResult = $fraud_connection->insert($newEvent, array('w' => 1));

			if ($insertResult['ok'] == 1) {
				Billrun_Factory::log()->log("line with the stamp: " . $newEvent['stamp'] . " inserted to the fraud events", Zend_Log::INFO);
			} else {
				Billrun_Factory::log()->log("Failed insert line with the stamp: " . $newEvent['stamp'] . " to the fraud events", Zend_Log::WARN);
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Failed insert line with the stamp: " . $newEvent['stamp'] . " to the fraud events, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	protected function over_threshold($value_before, $value, $threshold) {

		$round_threshold = $threshold * ceil((log($value_before, $threshold)));

		if ($value_before < $round_threshold && $round_threshold < $value_before + $value) {
			return TRUE;
		}

		return FALSE;
	}

}
