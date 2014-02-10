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
	 * Method to save balances events on the fraud system.
	 * 
	 * @param array $row
	 * @param array $balance
	 * @param string $usage_type, can be - call, data, mms, sms
	 * @param array $rate
	 * @param string $volume
	 * @param array $calculator
	 * 
	 */
	public function afterUpdateSubscriberBalance($row, $balance, $usage_type, $rate, $volume, $price, $calculator) {
		
		$sub_balance = $balance->getRawData();
		$sub_row = $row->getRawData();
		$events = Billrun_Factory::db()->eventsCollection();
		
		$properties = array();
		$properties = Billrun_Factory::config()->getConfigValue('fraud.usage_types');
		
		foreach ($properties as $value) {

			if (preg_match("/$usage_type/", $value) !== 0) {
				$sum_usagev += $sub_balance['balance']['totals'][$value]['usagev'];
			}

			$sum_cost += $sub_balance['balance']['totals'][$value]['cost'];
		}
			
		$usage_type_properties = array('call' => array('threshold_usagev' => Billrun_Factory::config()->getConfigValue('fraud.thresholds.call'), 'units' => 'SEC'),
										'sms' => array('threshold_usagev' => Billrun_Factory::config()->getConfigValue('fraud.thresholds.sms'), 'units' => 'SMS'),
										'mms' => array('threshold_usagev' => Billrun_Factory::config()->getConfigValue('fraud.thresholds.mms'), 'units' => 'NUMBER'),
										'data' => array('threshold_usagev' => Billrun_Factory::config()->getConfigValue('fraud.thresholds.data'), 'units' => 'BYTES'));

		$fraud_connection = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('fraud.db'))->eventsCollection();
		$fraud_connection_options = array();
		$fraud_connection_options['w'] = 1;
		
		$value = $row['usagev'];
		$value_before = $sum_usagev;
		// TODO !! check if small
		if($usage_type == 'data') {
			foreach ($usage_type_properties[$usage_type]['threshold_usagev'] as $threshold_plan => $threshold_name) {
				
				if($threshold_plan == 'small' && $row['plan'] != 'small') {
					continue;
				}
				
				$threshold_value = $usage_type_properties[$usage_type]['threshold_usagev'][$threshold_plan][$threshold_name];
					
				if(over_threshold($value_before, $value, $threshold_value)) {
					$this->insert_fraud_event('usagev', $value, $value_before, $row, $threshold_value, $usage_type_properties[$usage_type]['units'], $threshold_name, $fraud_connection, $fraud_connection_options);
				}
			}
		}
		elseif(over_threshold($value_before, $value, $threshold)){
			$this->insert_fraud_event('usagev', $value, $value_before, $row, $threshold, $usage_type_properties[$usage_type]['units'], 'FP_NATIONAL2', $fraud_connection, $fraud_connection_options);
		}
			
		$current_plan_name = $row['plan'];

		if(empty($current_plan_name)) {
			$current_plan_name = 'NO_GIFT';
		}

		$threshold = Billrun_Factory::config()->getConfigValue('fraud.thresholds.cost.'.$current_plan_name);
		$value_before = $sum_cost;
		$value = $price;
		
		if(over_threshold($value_before, $value, $threshold)) {
			$this->insert_fraud_event('cost', $value, $value_before, $row, $threshold, 'NIS', 'FP_NATIONAL1', $fraud_connection, $fraud_connection_options);
		}
	}
	
	public function insert_fraud_event($filter_by, $value, $value_before, $row, $threshold, $units, $event_type, $fraud_connection, $fraud_connection_options) {
		
		Billrun_Factory::log()->log('Marking down'. $event_type .'lines For fraud plugin', Zend_Log::INFO);
		
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
			$insertResult = $fraud_connection->insert($newEvent, $fraud_connection_options);

			if ($insertResult['ok'] == 1) {
				Billrun_Factory::log()->log("line with the stamp: " . $newEvent['stamp'] . " inserted to the fraud events", Zend_Log::INFO);
			} else {
				Billrun_Factory::log()->log("Failed insert line with the stamp: " . $newEvent['stamp'] . " to the fraud events", Zend_Log::WARN);
			}
		} catch (Exception $e) {
			Billrun_Factory::log()->log("Failed insert line with the stamp: " . $newEvent['stamp'] . " to the fraud events, got Exception : " . $e->getCode() . " : " . $e->getMessage(), Zend_Log::ERR);
		}
	}
	
	public function over_threshold($value_before, $value, $threshold) {
		
		$round_threshold = $threshold * ceil((log($value_before, $threshold)));
		
		if($value_before < $round_threshold && $round_threshold < $value_before + $value) {
			return TRUE;
		}
		
		return FALSE;
	}
	
}
