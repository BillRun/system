<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

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
	 */
	public function afterUpdateSubscriberBalance($row, $balance, $rowPrice, $calculator) {
		
		// if not plan to row - cannot do anything
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
			$method = $type . 'Check';
			if (method_exists($this, $method)) {
				call_user_func_array(array($this, $method), array($limits, $row, $balance, $rowPrice));
			} else {
				Billrun_Factory::log("Fraud plugin - method doesn't exists " . $method, Zend_Log::WARN);
			}
		}

	}

	protected function costCheck($limits, $row, $balance, $rowPrice) {
		$price_before = 0;// TODO sum all except TAP3
		$price_after = $price_before + $price;
		$this->checkLimits($limits, $row, $price_before, $price_after);
	}

	protected function usageCheck($limits, $row, $balance, $rowPrice) {
		// if the limit is not for the line type
		if (!isset($row['usaget']) || !isset($limits[$row['usaget']])) {
			return true;
		}

		$usaget = $row['usaget'];

		if (!isset($balance->balance['totals'][$usaget]['usagev'])) {
			Billrun_Factory::log("Fraud plugin - balance not exists for subscriber " . $row['sid'], Zend_Log::INFO);
			return true;
		}
		$balance_before_change = $balance->balance['totals'][$usaget]['usagev'];
		$balance_after_change = $balance_before_change + $row['usagev'];

		$this->checkLimits($limits, $row, $balance_before_change, $balance_after_change);
		
		return true;
	}
	
	protected function checkLimits($limits, $row, $before, $after) {
		foreach ($limits as $limit) {
			// if the limit for specific plans
			if (isset($limit['limitPlans']) &&
				(is_array($limit['limitPlans']) && !in_array($row['plan'], $limit['limitPlans']))) {
				continue;
			}
			
			$threshold = $limit['threshold'];
			$recurring = isset($limit['recurring']) && $limit['recurring'];
			if ($this->isThresholdTriggered($before, $after, $threshold, $recurring)) {
				// insert event
				$name = $limit['name'];
				Billrun_Factory::log("Fraud plugin - trigger event " . $row['stamp'] . ' with event name ' . $name, Zend_Log::INFO);
			}
		}

	}

	/**
	 * method to check if threshold passed between two value
	 * 
	 * @param float $usage_before the usage before
	 * @param float $usage_after the usage after
	 * @param float $threshold the threshold to pass
	 * @param boolean $recurring if true it will check with iterating of the threshold
	 * 
	 * @return boolean true if the threshold passed the value
	 */
	protected function isThresholdTriggered($usage_before, $usage_after, $threshold, $recurring = false) {
		if ($recurring) {
//			return (floor($usage_before / $threshold) < floor($usage_after / $threshold));
			return ($usage_before % $threshold > $usage_after % $threshold || ($usage_after-$usage_before) > $threshold);
		}
		return ($usage_before < $threshold) && ($threshold < $usage_after);
	}

	protected function insert_fraud_event($value, $value_before, $row, $threshold, $units, $event_type, $fraud_connection, $fraud_connection_options) {

		$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->eventsCollection();

		$newEvent = new Mongodloid_Entity();
		$newEvent['value_usagev'] = $row['usagev'];
		$newEvent['value_usagev_before'] = $value_before;
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
