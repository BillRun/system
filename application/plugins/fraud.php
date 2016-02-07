<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
/**
 * compatible for PHP 5.4
 */
if (!function_exists('array_column')):

	function array_column(array $input, $column_key, $index_key = null) {

		$result = array();
		foreach ($input as $k => $v) {
			$result[$index_key ? $v[$index_key] : $k] = $v[$column_key];
		}

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
		$this->min_time = Billrun_Util::getStartTime(Billrun_Util::getBillrunKey(time() + Billrun_Factory::config()->getConfigValue('fraud.minTimeOffset', 5400))); // minus 1.5 hours
	}

	/**
	 * Method to save fraud events on the fraud system once event triggered
	 * afterUpdateSubscriberBalance trigger after subscriber balance is updated
	 * 
	 * @param array $row the line from lines collection
	 * @param Mongodloid_Entity $balance 
	 * @param array $pricingData reference to the pricing data that will update the row after exiting the plugin method
	 * @param Billrun_Calculator $calculator
	 * 
	 * @return boolean in case we are on chain return true if all ok and chain can continue, else return false if require to stop the plugin chain
	 * 
	 */
	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		// TODO: Explain this condition.
		if (!($calculator->getType() == 'pricing' && 
			  method_exists($calculator, 'getPricingField') && 
			  ($pricingField = $calculator->getPricingField()))) {
			return true;
		}
		
		$rowPrice = 
			isset($pricingData[$pricingField]) ? 
			$pricingData[$pricingField] : 
			0; // if the rate wasn't billable then the line won't have a charge

		if (!$this->isLineLegitimate($row, $calculator)) {
			// TODO: Why return true?
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

		// first check we are not on tap3, because we prevent intl roaming fraud on nrtrde
		if ($row['type'] == 'tap3') {
			return true;
		}

		// Check if row is too "old" to be considered as a fraud. Currently done by decrease X hours (default: 1.5 hours) from min_time variable
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
			$this->checkCostRule($rule, $row, $price_before, $price_after);
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

		// on some cases balances is not object - 
		// This happens because customer pricing sends the balance by a reference to its data member.
		// It can be that customer pricing is sent to the garbage collector and being deserialized.
		// This means that even though $balance will NOT be null, it will be an unitialized deserialized
		// object so is_object will return false.
		// The solution is sending a copy of the balance and not a reference to it.
		if (!is_object($balance) || !isset($balance->balance['totals'][$usaget]['usagev'])) {
			Billrun_Factory::log("Fraud plugin - balance not exists for subscriber " . $row['sid'] . ' usage type ' . $usaget, Zend_Log::WARN);
			return false;
		}

		foreach ($limits['rules'] as $rule) {
			$ret[] = $this->checkUsageRule($rule, $row, $balance->balance);
		}

		return $ret;
	}

	/**
	 * check if fraud rule triggered
	 * 
	 * @param array $rule the array rule of settings
	 * @param array $row the row to check the rule
	 * @param array $balance the balance array contain all subscriber balance
	 * 
	 * @return mixed return the rule array if succeed, else false
	 */
	protected function checkUsageRule($rule, $row, $balance) {
		// if the limit for specific type
		if (!isset($row['usaget']) || (!empty($rule['usaget']) && !in_array($row['usaget'], $rule['usaget']))) {
			return false;
		}
		// ignore subscribers :)
		if (isset($rule['ignoreSubscribers']) && is_array($rule['ignoreSubscribers'] && in_array($row['sid'], $rule['ignoreSubscribers']))) {
			return false;
		}

		$usaget = $row['usaget'];

		// if the limit for specific plans
		// @todo: make the first if-condition as override (means be able to apply limit & exclude together)
		if (
			(isset($rule['limitPlans']) && is_array($rule['limitPlans']) && !in_array(strtoupper($row['plan']), $rule['limitPlans'])) ||
			(isset($rule['excludePlans']) && is_array($rule['excludePlans']) && in_array(strtoupper($row['plan']), $rule['excludePlans']))
		) {
			return false;
		} else if (isset($rule['limitGroups'])) { // if limit by specific groups
			if ((is_array($rule['limitGroups']) && isset($row['arategroup']) && !in_array(strtoupper($row['arategroup']), $rule['limitGroups'])) || !isset($row['arategroup'])) {
				return false;
			}
		}

		// calculate before and after usage
		// first check if the rule is based on groups usage
		if (!empty($rule['sumFields']) && is_array($rule['sumFields'])) {
			$before = 0;
			foreach ($rule['sumFields'] as $dottedField) {
				$value = $balance;
				$field_arr = explode('.', $dottedField);
				foreach ($field_arr as $field) {
					if (isset($value[$field])) {
						$value = $value[$field];
					} else {
						$value = 0;
						break;
					}
				}
				$before+=$value;
			}
		} else if (isset($rule['limitGroups'])) {
			$before = isset($balance['groups'][$row['arategroup']][$usaget]['usagev']) ? $balance['groups'][$row['arategroup']][$usaget]['usagev'] : 0;
		} else { // fallback: rule based on general usage
			$before = $balance['totals'][$usaget]['usagev'];
		}
		$after = $before + $row['usagev'];

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
	 * check if fraud rule triggered
	 * 
	 * @param array $rule the array rule of settings
	 * @param array $row the row to check the rule
	 * @param number $before the value before the change
	 * @param number $after the value after the change
	 * 
	 * @return mixed return the rule array if succeed, else false
	 */
	protected function checkCostRule($rule, $row, $before, $after) {
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
		$newEvent['line_usagev'] = $row['usagev'];

		if (is_null($priority) || !is_numeric($priority)) {
			$priority = 15;
		}

		if ($recurring) {
			// we will use the priority as offset
			$newEvent['priority'] = $priority - floor($value / $threshold);
		} else {
			$newEvent['priority'] = (int) $priority;
		}

		$newEvent['stamp'] = md5(serialize($newEvent));
		$newEvent['creation_time'] = date(Billrun_Base::base_dateformat);

		try {
			Billrun_Factory::log()->log("Fraud plugin - Event stamp: " . $newEvent['stamp'] . " inserted to the fraud events", Zend_Log::INFO);
			$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->eventsCollection();
			$fraud_connection->insert($newEvent);
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

	/**
	 * TODO
	 * Save lines to the fraud DB lines collection.
	 * @param type $lines
	 */
	public function insertToFraudLines($lines) {
		try {
			Billrun_Factory::log()->log('Fraud plugin - Inserting ' . count($lines) . ' Lines to fraud lines collection', Zend_Log::INFO);
			$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->linesCollection();
			foreach ($lines as $line) {

				$line['unified_record_time'] = $line['urt'];
				if (isset($line['aid'])) {
					$line['account_id'] = $line['aid'];
				}
				if (isset($line['sid'])) {
					$line['subscriber_id'] = $line['sid'];
				}

				$fraud_connection->insert(new Mongodloid_Entity($line));
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Fraud plugin - Failed to insert line with the stamp: " . $line['stamp'] . " to the fraud lines collection, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	/**
	 * TODO document
	 * detect roaming ggsn lines
	 * @param type $lines
	 */
	protected function insertRoamingGgsn($lines) {
		$roamingLines = array();
		foreach ($lines as $line) {
			if (!preg_match('/^(?=62\.90\.|37\.26\.|176\.12\.158\.(\d$|[1]\d$|2[10]$))/', $line['sgsn_address'])) {
				$roamingLines[] = $line;
			}
		}
		if (!empty($roamingLines)) {
			$this->insertToFraudLines($roamingLines);
		}
	}

	protected function insertIntlNsn($lines) {
		$roamingLines = array();
		$circuit_groups = Billrun_Util::getIntlCircuitGroups();
		$record_types = array('01', '11');
		$rates_ref_list = Billrun_Util::getIntlRateRefs();
		foreach ($lines as $line) {
			if (isset($line['out_circuit_group']) && in_array($line['out_circuit_group'], $circuit_groups) && in_array($line['record_type'], $record_types)) {
				$roamingLines[] = $line;
			} else if (!empty($line['arate']) && in_array($line['arate']['$id']->{'$id'}, $rates_ref_list)) {
				$roamingLines[] = $line;
			}
		}
		if (!empty($roamingLines)) {
			$this->insertToFraudLines($roamingLines);
		}
	}

	/**
	 * TODO
	 * @param \Billrun_Processor $processor
	 * @return type
	 */
	public function afterProcessorStore($processor) {
		$type = $processor->getType();
		if ($type != "ggsn" && $type != "nsn") {
			return;
		}
		Billrun_Factory::log('Plugin fraud afterProcessorStore', Zend_Log::INFO);
		$runAsync = Billrun_Factory::config()->getConfigValue('fraud.runAsync', 1);
		if (function_exists("pcntl_fork") && $runAsync && -1 !== ($pid = pcntl_fork())) {
			if ($pid == 0) {
				Billrun_Util::resetForkProcess();
				Billrun_Factory::log('Plugin fraud::afterProcessorStore run it in async mode', Zend_Log::INFO);
				if ($type == "ggsn") {
					$this->insertRoamingGgsn($processor->getData()['data']);
				} else if ($type == "nsn") {
					$this->insertIntlNsn($processor->getData()['data']);
				}
				Billrun_Factory::log('Plugin fraud::afterProcessorStore async mode done.', Zend_Log::INFO);
				exit(); // exit from child process after finish
			}
		} else {
			Billrun_Factory::log('Plugin fraud::afterProcessorStore runing in sync mode', Zend_Log::INFO);
			if ($type == "ggsn") {
				$this->insertRoamingGgsn($processor->getData()['data']);
			} else if ($type == "nsn") {
				$this->insertIntlNsn($processor->getData()['data']);
			}
		}
		Billrun_Factory::log('Plugin fraud afterProcessorStore was ended', Zend_Log::INFO);
	}

	public function afterCalculatorUpdateRow($line, $calculator) {
		return;

		if (!$this->isLineLegitimate($line, $calculator)) {
			return true;
		}

		if (!$calculator->getCalculatorQueueType() == 'rate' || $line['type'] != 'nsn') {
			return true;
		}

		if (isset($line['called_number'])) {
			// fire  event to increased called_number usagev
			$this->triggerCalledNumber($line);
		}

		if (isset($line['sid']) && isset($line['usaget']) && $line['usaget'] == 'incoming_call') {
			$this->triggerCallingNumber($line);
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
		);

		try {
			Billrun_Factory::log()->log("Fraud plugin - called " . $called_number . " with usagev of " . $line['usagev'] . " upserted to the fraud called collection", Zend_Log::DEBUG);
			$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud2.db'))->calledCollection();
			$fraud_connection->update($query, $update, $options);
		} catch (Exception $e) {
			// @TODO: dump to file for durability
			Billrun_Factory::log()->log("Fraud plugin - Failed insert line with the stamp: " . $line['stamp'] . " to the fraud called, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	protected function triggerCallingNumber($line) {
		$calling_number = Billrun_Util::msisdn($line['calling_number']);
		$query = array(
			'calling_number' => $calling_number,
			'in_circuit_group' => isset($line['in_circuit_group']) ? $line['in_circuit_group'] : '',
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
		);

		try {
			Billrun_Factory::log()->log("Fraud plugin - calling " . $calling_number . " with usagev of " . $line['usagev'] . " upserted to the fraud calling collection", Zend_Log::DEBUG);
			$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud2.db'))->callingCollection();
			$fraud_connection->update($query, $update, $options);
		} catch (Exception $e) {
			// @TODO: dump to file for durability
			Billrun_Factory::log()->log("Fraud plugin - Failed insert line with the stamp: " . $line['stamp'] . " to the fraud calling, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	protected function isLineLegitimate($row, $calculator) {
		$queue_line = $calculator->getQueueLine($row['stamp']);
		if (isset($queue_line['skip_fraud']) && $queue_line['skip_fraud']) {
			return false;
		}
		return true;
	}

	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $arate, $calculator) {
		if ($arate['key'] == 'INTERNET_VF') {
			if (isset($pricingData['arategroup']) && $pricingData['arategroup'] == 'VF_INCLUDED') {
				$query = array('sid' => $query['sid'], 'billrun_month' => $query['billrun_month']);
				$pricingData = array('arategroup' => $pricingData['arategroup'], 'usagesb' => $pricingData['usagesb']);
				$update['$set'] = array('tx.' . $row['stamp'] => $pricingData);
				foreach (array_keys($update['$inc']) as $key) {
					if (!Billrun_Util::startsWith($key, 'balance.groups')) {
						unset($update['$inc'][$key]);
					}
				}
			} else {
				$pricingData = $update = array();
			}
		}
	}

}
