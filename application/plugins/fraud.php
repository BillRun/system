<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
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
	
	protected $cachedResults;

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
		if ($calculator->getType() == 'pricing' && method_exists($calculator, 'getPricingField') && ($pricingField = $calculator->getPricingField())) {
			$rowPrice = isset($pricingData[$pricingField]) ? $pricingData[$pricingField] : 0; // if the rate wasn't billable then the line won't have a charge
		} else {
			return true;
		}

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
				case 'addon':
					if (!isset($row['addon_balances'])) {
						break;
					}

					$addonBalances = $row['addon_balances'];
					foreach ($addonBalances as $addonBalance) {
						$this->addonUsageCheck($limits, $row, $addonBalance);
					}
					break;
				case 'condition':
					$this->conditionCheck($limits, $row, $balance);
					break;

				default:
					Billrun_Factory::log("Fraud plugin - method doesn't exists " . $type, Zend_Log::WARN);
					break;
			}
		}

		return true;
	}

	protected function addonUsageCheck($limits, $row, $balance) {
		$ret = array();
		if ($row['usagev'] === 0) {
			return false;
		}
		foreach ($limits['rules'] as $rule) {
			if (empty($rule['service_names']) || !in_array($balance['service_name'], $rule['service_names'])) {
				continue;
			}
			$ret[] = $this->checkAddonUsageRule($rule, $row, $balance);
		}
		return $ret;
	}

	protected function checkAddonUsageRule($rule, $row, $balance) {
		if (!isset($row['usaget']) || (!empty($rule['usaget']) && !in_array($row['usaget'], $rule['usaget']))) {
			return false;
		}
		$usaget = $row['usaget'];
		if ($rule['threshold'] == 'from_plan') {
			$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec, 'disableCache' => true));
			$percentage = isset($rule['percentage']) ? $rule['percentage'] : 1;
			if (isset($rule['service_names']) && in_array($balance['service_name'], $rule['service_names'])) {
				$groupName = $balance['service_name'];
				$threshold = (float) floor($plan->get('include.groups.' . $groupName)[$row['usaget']] * $percentage);
			} else {
				Billrun_Log::getInstance()->log("Missing group at rule where threshold is taken from plan group", Zend_log::WARN);
			}
		} else {
			Billrun_Log::getInstance()->log("Threshold need to be taken from plan", Zend_log::ALERT);
		}
		if ($usaget == 'data' && $rule['unit'] == 'BYTE') {
			$before = $balance['usage_before']['data'];
			$after = $before + $row['usagev'];
		} else if (in_array($usaget, array('call', 'sms', 'incoming_call')) && $rule['unit'] == 'SMSEC') {
			$callUsageBefore = $balance['usage_before']['call'] + $balance['usage_before']['incoming_call'];
			$smsUsageBefore = $balance['usage_before']['sms'];
			$before = $callUsageBefore + $smsUsageBefore * 60; // convert sms units to seconds
			$currentUsage = ($usaget == 'sms') ? $row['usagev'] * 60 : $row['usagev'];
			$after = $before + $currentUsage;
		} else if(!empty($rule['usaget']) && is_array($rule['usaget']) && in_array($usaget, $rule['usaget'])) {
			$before = 0;
			foreach($rule['usaget'] as $ruleUsageType) {
				$before += !empty($balance['usage_before'][$ruleUsageType]) ? $balance['usage_before'][$ruleUsageType] : 0;
			}
			$after = $before +  $row['usagev'];
		}
		if (!isset($before)) {
			return;
		}
		$recurring = isset($rule['recurring']) && $rule['recurring'];
		$minimum = (isset($rule['minimum']) && $rule['minimum']) ? (int) $rule['minimum'] : 0;
		$maximum = (isset($rule['maximum']) && $rule['maximum']) ? (int) $rule['maximum'] : -1;
		if ($this->isThresholdTriggered($before, $after, $threshold, $recurring, $minimum, $maximum)) {
			$addonService['service_name'] = $balance['service_name'];
			$addonService['package_id'] = $balance['package_id'];
			$channelAddon = isset($rule['channel']) ? $rule['channel'] : '';
			$addonService['channel'] = "Addon_Service_" . $balance['package_id'] . $channelAddon;
			Billrun_Factory::log("Fraud plugin - line stamp " . $row['stamp'] . ' trigger event ' . $rule['name'], Zend_Log::INFO);
			if (isset($rule['priority'])) {
				$priority = (int) $rule['priority'];
			} else {
				$priority = null;
			}
			$this->insert_fraud_event($after, $before, $row, $threshold, $rule['unit'], $rule['name'], $priority, $recurring, $addonService);
			return $rule;
		}
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

		// on some cases balances is not object - TODO investigate this issue
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

		if ($rule['threshold'] == 'from_plan') {
			$rule['sumFields'][] = 'groups.' . $row['plan'] . '.' . $row['usaget'] . '.usagev';
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
		if (isset($rule['inPlanThreshold']) && $rule['inPlanThreshold']) {
			$overPlan = isset($row['over_plan']) && empty($rule['only_in_plan_threshold']) ? $row['over_plan'] : 0;
			$inPlan = isset($row['in_plan']) ? $row['in_plan'] : 0;
			$adjustAfter = isset($rule['in_plan_after_adjustment']) ? $rule['in_plan_after_adjustment'] : 0;
			$after = $before + $inPlan + $overPlan + $adjustAfter;
		} else {
			$after = $before + $row['usagev'];
		}

		if ($rule['threshold'] == 'from_plan') {
			$plan = Billrun_Factory::plan(array('name' => $row['plan'], 'time' => $row['urt']->sec, 'disableCache' => true));
			$percentage = isset($rule['percentage']) ? $rule['percentage'] : 1;
			$groupName = $row['plan'];
			$threshold = (float) floor($plan->get('include.groups.' . $groupName)[$usaget] * $percentage);
		} else {
			$threshold = $rule['threshold'];
		}

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
		return ($before < $threshold) && ($threshold <= $after);
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
	protected function insert_fraud_event($value, $value_before, $row, $threshold, $units, $event_type, $priority = null, $recurring = false, $addonService = null) {

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

		if (!is_null($addonService)) {
			foreach ($addonService as $key => $value) {
				$newEvent[$key] = $value;
			}
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

	/**
	 * TODO
	 * Save lines to the fraud DB lines collection.
	 * @param type $lines
	 */
	public function insertToFraudLines($lines) {
		try {
			Billrun_Factory::log()->log('Fraud plugin - Inserting ' . count($lines) . ' Lines to fraud lines collection', Zend_Log::INFO);
			foreach ($lines as &$line) {

				$line['unified_record_time'] = $line['urt'];
				if (isset($line['aid'])) {
					$line['account_id'] = $line['aid'];
				}
				if (isset($line['sid'])) {
					$line['subscriber_id'] = $line['sid'];
				}
				unset($line['roaming_balances']);
				$line['insert_process_time'] = new MongoDate();
			}
			$fraud_lines_collection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->linesCollection();
			$fraud_lines_collection->batchInsert($lines, array('w' => 0));
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Fraud plugin - Failed to insert line with the stamp: " . $line['stamp'] . " to the fraud lines collection, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
		}
	}

	/**
	 * Save lines reference to the fraud DB queue to be priced by fruad.
	 * @param type $lines
	 */
	public function insertToFraudQueue($lines, $keepRate = FALSE,$extraFields = []) {
		try {
			Billrun_Factory::log()->log('Fraud plugin - Inserting ' . count($lines) . ' Lines to fraud lines collection', Zend_Log::INFO);
			$queueLines = array();
			foreach ($lines as $line) {
				$queueLine = array('stamp' => $line['stamp'],
					'urt' => $line['urt'],
					'type' => $line['type'],
					'calc_time' => false,
					'calc_name' => false,
				);
				if (isset($line['aid']) && isset($line['sid']) && Billrun_Factory::config()->getConfigValue('fraud.queue.set_calc_name_customer', false)) {
					$queueLine['aid'] = $line['aid'];
					$queueLine['sid'] = $line['sid'];
					$queueLine['calc_name'] = 'customer';
				}
				if($keepRate && $queueLine['calc_name'] === 'customer' && !empty($line['arate'])) {
					$queueLine['calc_name'] = 'rate';
				}
				if(!empty($extraFields)) {
					foreach ($extraFields as $fieldName ) if( isset($line[$fieldName]) && !isset($queueLine[$fieldName])) {
						$queueLine[$fieldName] = $line[$fieldName];
					}
				}
				$queueLine['insert_process_time'] = new MongoDate();
				unset($queueLine['roaming_balances']);
				$queueLines[] = $queueLine;
			}

			$fraud_queue_collection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->queueCollection();
			$fraud_queue_collection->batchInsert($queueLines, array('w' => 0));
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Fraud plugin - Failed to insert line with the stamp: " . $line['stamp'] . " to the fraud queue collection, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
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
// 			if (!preg_match('/^(?=62\.90\.|37\.26\.|85\.64\.|172\.28\.|176\.12\.158\.|80\.246\.131|80\.246\.132|37\.142\.167|91\.135\.96\.|91\.135\.99\.(19[2-9]|2))/', $line['sgsn_address'])) {
			if (!preg_match('/^(?=62\.90\.|37\.26\.|85\.64\.|172\.28\.|172\.16\.24\.|172\.17\.224\.|172\.25\.|176\.12\.158\.|80\.246\.131|80\.246\.132|37\.142\.167|91\.135\.96\.|91\.135\.99\.(19[2-9]|2)|10\.224\.213|10\.192\.213)/', $line['sgsn_address'])) {
				$roamingLines[] = $line;
			}
		}
		if (!empty($roamingLines)) {
			$this->insertToFraudLines($roamingLines);
			$this->insertToFraudQueue($roamingLines);
		}
	}

	protected function insertIntlNsn($lines) {
		$intlLines = array();
		$roamingLines = [];
		$circuit_groups = Billrun_Util::getIntlCircuitGroups();
		$record_types = array('01', '11', '30');
		$rates_ref_list = Billrun_Util::getIntlRateRefs();
		foreach ($lines as $line) {
			if (isset($line['out_circuit_group']) && in_array($line['out_circuit_group'], $circuit_groups) && in_array($line['record_type'], $record_types)) {
				$intlLines[] = $line;
			} else if (!empty($line['arate']) && in_array($line['arate']['$id']->{'$id'}, $rates_ref_list)) {
				$intlLines[] = $line;
			}
			if(isset($line['roaming']) && $line['roaming'] === TRUE) {
				$roamingLines[] = $line;
			}
		}
		if (!empty($intlLines)) {
			$this->insertToFraudLines($intlLines);
		}

		if (!empty($roamingLines)) {
			$this->insertToFraudLines($roamingLines);
			$this->insertToFraudQueue($roamingLines, TRUE,[	'in_circuit_group_name', 'in_circuit_group',
															'out_circuit_group_name','out_circuit_group',
															'in_mgw_name','out_mgw_name','roaming']);
		}
	}

	/**
	 * Inserting sms lines to fruad for monitoring roaming packages usage
	 * detect roaming sms lines
	 * @param type $lines
	 */
	protected function insertRoamingSms($lines) {
		$roamingLines = array();
		foreach ($lines as $line) {
			if (isset($line['roaming'])) {
				$roamingLines[] = $line;
			}
		}
		if (!empty($roamingLines)) {
			$this->insertToFraudLines($roamingLines);
			$this->insertToFraudQueue($roamingLines);
		}
	}

	/**
	 * TODO
	 * @param \Billrun_Processor $processor
	 * @return type
	 */
	public function afterProcessorStore($processor) {
		$type = $processor->getType();
		if ($type != "ggsn" && $type != "nsn" && $type != 'smsc') {
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
				} else if ($type == "smsc") {
					$this->insertRoamingSms($processor->getData()['data']);
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
			} else if ($type == "smsc") {
				$this->insertRoamingSms($processor->getData()['data']);
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
			'w' => 0,
		);

		try {
			Billrun_Factory::log()->log("Fraud plugin - called " . $called_number . " with usagev of " . $line['usagev'] . " upserted to the fraud called collection", Zend_Log::DEBUG);
			$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->calledCollection();
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
			'w' => 0,
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
		return empty($row['roaming']);
	}

	public function beforeCommitSubscriberBalance(&$row, &$pricingData, &$query, &$update, $arate, $calculator) {
		if ($arate['key'] == 'INTERNET_VF') {
			$lineTime = date(Billrun_Base::base_dateformat, $row['urt']->sec);
			$sid = $row['sid'];
			if (isset($pricingData['arategroup']) && $pricingData['arategroup'] == 'VF_INCLUDED' &&
				is_numeric($rowVfDays = $this->queryVFDaysApi($sid, $lineTime)) &&
				$rowVfDays <= Billrun_Factory::config()->getConfigValue('fraud.usageabroad.days')) {
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

	protected function queryVFDaysApi($sid, $lineTime) {
		$url = Billrun_Factory::config()->getConfigValue('fraud.vfdays.url');
		$lineYear = date('Y', strtotime($lineTime));
		$yearDay = date('z', strtotime($lineTime));
		try {
			if (!isset($this->cachedResults[$sid][$lineYear . $yearDay])) {
				Billrun_Factory::log('Quering Fraud server for ' . $sid . ' vfdays count', Zend_Log::DEBUG);
				$result = Billrun_Util::sendRequest($url, array('sid' => $sid, 'max_datetime' => $lineTime), Zend_Http_Client::GET);
			} else {
				return $this->cachedResults[$sid][$lineYear . $yearDay];
			}
		} catch (Exception $e) {
			Billrun_Factory::log('Fraud server not responding', Zend_Log::WARN);
			return 0;
		}
		$resultArray = json_decode($result, true);
		if (!$resultArray['status']) {
			return 0;
		}
		$this->cachedResults[$sid][$lineYear . $yearDay] = $resultArray['details']['days'];
		return $resultArray['details']['days'];
	}

	protected function conditionCheck($limits, $row, $balance) {
		foreach ($limits['rules'] as $rule) {
			if (isset($rule['usaget']) && ($row['usaget'] == $rule['usaget'])) {
				$this->checkConditionRule($rule, $row, $balance->balance);
			}
		}
	}

	protected function checkConditionRule($rule, $row, $balance) {
		if (!isset($row['usaget'])) {
			return false;
		}
		if ((isset($rule['limitPlans']) && is_array($rule['limitPlans']) && !in_array(strtoupper($row['plan']), $rule['limitPlans'])) ||
			(isset($rule['excludePlans']) && is_array($rule['excludePlans']) && in_array(strtoupper($row['plan']), $rule['excludePlans']))) {
			return false;
		}
		$conditionsLogic = $rule['conditions']['logic'];
		switch ($conditionsLogic) {
			case 'or':
				$conditionsValue = $this->isOrConditionSatisfied($rule, $row);
				break;
			case 'and':
				$conditionsValue = $this->isAndConditionSatisfied($rule, $row);
				break;
			default:
				$conditionsValue = false;
				break;
		}

		if ($conditionsValue == false) {
			return;
		}

		$threshold = $rule['threshold'];
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
		} else { // fallback: rule based on general usage
			$before = $balance['totals'][$row['usaget']]['usagev'];
		}
		$after = $before + $row['usagev'];
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

	protected function isOrConditionSatisfied($rule, $row) {
		foreach ($rule['condition_on_fields'] as $index => $field) {
			$condition = $rule['conditions'][$index];
			$func = key($condition);
			$value = $condition[key($condition)];
			if (($func == 'isset') && (isset($row[$field]) == $value)) {
				return true;
			}
			if (($func == 'equal') && ($row[$field] == $value)) {
				return true;
			}
		}

		return false;
	}

	protected function isAndConditionSatisfied($rule, $row) {
		foreach ($rule['condition_on_fields'] as $index => $field) {
			$condition = $rule['conditions'][$index];
			$func = key($condition);
			$value = $condition[key($condition)];
			if ($func == 'equal' && $row[$field] != $value) {
				return false;
			}
			if ($func == 'isset' && isset($row[$field]) != $value) {
				return false;
			}
		}

		return true;
	}

}
