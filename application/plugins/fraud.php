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
	 * Rows before this time won't be considered as fraud
	 * @var int
	 */
	protected $min_time;

	public function __construct() {
		$this->min_time = Billrun_Util::getStartTime(Billrun_Util::getBillrunKey(time()));
	}

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
		if (!$this->isLineLegitimate($row, $calculator)) {
			return true;
		}
		if (is_null($balance)) {
			Billrun_Factory::log("Fraud plugin - balance is empty or not transfer to the plugin" . $row['stamp'] . ' | calculator ' . $calculator->getType(), Zend_Log::WARN);
			return true;
		}
		// if not plan to row - cannot do anything
		if (!isset($row['plan'])) {
			Billrun_Factory::log("Fraud plugin - plan not exists for line " . $row['stamp'], Zend_Log::ERR);
			return true;
		}

		// first check we are not on tap3, because we prevent intl fraud on nrtrde
		if ($row['type'] == 'tap3') {
			return true;
		}

		// check if row is too "old" to be considered as a fraud. TODO: consider lowering min_time in 1-2 days.
		if ($row['urt']->sec <= $this->min_time) {
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
					Billrun_Factory::log("Fraud plugin - method doesn't exists " . $type, Zend_Log::WARN);
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
			return $this->sumBalance($balance, $costFieldName, $filter);
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
		$ret = array();
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
			$ret[] = $this->checkRule($rule, $row, $balance_before_change, $balance_after_change);
		}

		return $ret;
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
		if (!isset($row['usaget']) || (!empty($rule['usaget']) && !in_array($row['usaget'], $rule['usaget']))) {
			return false;
		}
		// if the limit for specific plans
		if (isset($rule['limitPlans']) &&
				(is_array($rule['limitPlans']) && !in_array(strtoupper($row['plan']), $rule['limitPlans']))) {
			return false;
		}
		// ignore subscribers :)
		if (isset($rule['ignoreSubscribers']) &&
				(is_array($rule['ignoreSubscribers']) && in_array($row['sid'], $rule['ignoreSubscribers']))) {
			return false;
		}

		$threshold = $rule['threshold'];
		$recurring = isset($rule['recurring']) && $rule['recurring'];
		$minimum = (isset($rule['minimum']) && $rule['minimum']) ? (int) $rule['minimum'] : 0;
		$maximum = (isset($rule['maximum']) && $rule['maximum']) ? (int) $rule['maximum'] : -1;
		if ($this->isThresholdTriggered($before, $after, $threshold, $recurring, $minimum, $maximum)) {
			Billrun_Factory::log("Fraud plugin - line stamp " . $row['stamp'] . ' trigger event ' . $rule['name'], Zend_Log::INFO);
			if (isset($rule['priority'])) {
				$priority = (int) $rule['priority'];
			} else {
				$priority = null;
			}
			$this->insert_fraud_event($after, $before, $row, $threshold, $rule['unit'], $rule['name'], $priority, $recurring);
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
	 * @param int $minimum the minimum to trigger from (actual only for recurring)
	 * 
	 * @return boolean true if the threshold passed the value
	 */
	protected function isThresholdTriggered($before, $after, $threshold, $recurring = false, $minimum = 0, $maximum = -1) {
		if ($before < 0 || $after < 0) {
			return FALSE;
		}
		if ($recurring) {
			return ($minimum < $after) && ($maximum < 0 || $maximum > $before) && (floor($before / $threshold) < floor($after / $threshold));
		}
		return ($before < $threshold) && ($threshold < $after);
	}

	/**
	 * method to add the event to the events system
	 * 
	 * @param int $value value that trigger the event (after)
	 * @param int $value_before the value before the event
	 * @param Array $row the line row the trigger the event
	 * @param int $threshold threshold
	 * @param string $units the unit of the threshold
	 * @param string $event_type the event type
	 * @param bool $recurring is the event is recurring
	 */
	protected function insert_fraud_event($value, $value_before, $row, $threshold, $units, $event_type, $priority = null, $recurring = false) {

		$newEvent = new Mongodloid_Entity();
		$newEvent['value'] = (float) $value;
		$newEvent['value_before'] = (float) $value_before;
		$newEvent['aid'] = $row['aid'];
		$newEvent['sid'] = $row['sid'];
		// backward compatibility
		$newEvent['subscriber_id'] = $row['sid'];
		if (isset($row['imsi'])) {
			$newEvent['imsi'] = $row['imsi'];
		}
		$newEvent['source'] = 'billing';
		if ($recurring) {
			// if it's recurring passed the current threshold
			$newEvent['threshold'] = floor($value / $threshold) * $threshold;
		} else {
			$newEvent['threshold'] = $threshold;
		}
		$newEvent['units'] = $units;
		$newEvent['event_type'] = $event_type;
		$newEvent['plan'] = $row['plan'];
		$newEvent['recurring'] = $recurring;
		$newEvent['line_stamp'] = $row['stamp'];
		$newEvent['line_urt'] = $row['urt'];
		if (!is_null($priority)) {
			$newEvent['priority'] = (int) $priority;
		} else if ($recurring) {
			// as long as the value is greater the event priority should be high (the highest priority is 0)
			$newEvent['priority'] = (int) 15 - floor($value / $threshold);
		} else {
			$newEvent['priority'] = 10;
		}

		$newEvent['stamp'] = md5(serialize($newEvent));
		$newEvent['creation_time'] = date(Billrun_Base::base_dateformat);

		try {
			Billrun_Factory::log()->log("Fraud plugin - Event stamp: " . $newEvent['stamp'] . " inserted to the fraud events", Zend_Log::INFO);
			$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->eventsCollection();
			$fraud_connection->insert($newEvent, array('w' => 0));
		} catch (Exception $e) {
			// @TODO: dump to file for durability
			Billrun_Factory::log()->log("Fraud plugin - Failed insert line with the stamp: " . $newEvent['stamp'] . " to the fraud events, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	protected function over_threshold($value_before, $value, $threshold) {

		$round_threshold = $threshold * ceil((log($value_before, $threshold)));

		if ($value_before < $round_threshold && $round_threshold < $value_before + $value) {
			return TRUE;
		}

		return FALSE;
	}
	
	public function afterCalculatorUpdateRow($line, $calculator) {
		
		if (!$this->isLineLegitimate($line, $calculator)) {
			return true;
		}

		if (!$calculator->getCalculatorQueueType() == 'rate' || $line['type'] != 'nsn') {
			return true;
		}
		$rateKey = isset($line['arate']['key']) ? $line['arate']['key'] : null;
		if (!empty($rateKey) && ($rateKey == 'IL_MOBILE' || substr($rateKey, 0, 3) == 'KT_') && isset($line['called_number'])) {
			// fire  event to increased called_number usagev
			$this->triggerCalledNumber($line);
			
		}
	}
	
	protected function triggerCalledNumber($line) {
		$called_number = Billrun_Util::msisdn($line['called_number']);
		$query = array(
			'called_number' => $called_number,
			'out_circuit_group' => isset($line['out_circuit_group']) ? $line['out_circuit_group'] : '',
			'date' => (int) date('Ymd', $line['urt']->sec),
		);
		
		$update = array(
			'$inc' => array(
				'usagev' => $line['usagev'],
				'eventsCount' => 1
			),
		);
		
		$options = array(
			'upsert' => true,
			'w' => 0,
		);
		
		try {
			Billrun_Factory::log()->log("Fraud plugin - called " . $called_number . " with usagev of " . $line['usagev'] . " upserted to the fraud called collection", Zend_Log::DEBUG);
			$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->calledCollection();
			$fraud_connection->update($query, $update, $options);
		} catch (Exception $e) {
			// @TODO: dump to file for durability
			Billrun_Factory::log()->log("Fraud plugin - Failed insert line with the stamp: " . $newEvent['stamp'] . " to the fraud events, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
		}
	}
	
	protected function isLineLegitimate($row, $calculator) {
		$queue_line = $calculator->getQueueLine($row['stamp']);
		if (isset($queue_line['skip_fraud']) && $queue_line['skip_fraud']) {
			return false;
		}
		return true;
	}

}
