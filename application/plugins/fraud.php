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
	 * Method to send balances to the fraud system.
	 * 
	 * @param array $row
	 * @param array $balance
	 * @param string $usage_type
	 * @param array $rate
	 * @param string $volume
	 * @param array $calculator
	 * 
	 * return array subscriber balance
	 */
	public function afterUpdateSubscriberBalance($row, $balance, $usage_type, $rate, $volume, $price, $calculator) {
		
		$usage_types = array('usagev' => $usage_type, 'cost' => NULL);
		
		$sub_balance = $balance->getRawData();
		$sub_row = $row->getRawData();
		$events = Billrun_Factory::db()->eventsCollection();
		
		$properties = array('call', 'data', /* 'incoming_call', 'incoming_sms', */ 'mms', 'out_plan_call', 'out_plan_sms', 'sms');

		$sum = NULL;
		
		foreach ($usage_types as $filter_by => $usage_type) {
			
			foreach ($properties as $value) {

				if (empty($usage_type) || preg_match("/$usage_type/", $value) !== 0) {
					$sum += $sub_balance['balance']['totals'][$value][$filter_by];
				}
			}

			$newEvent = new Mongodloid_Entity();
			$newEvent['creation_time'] = date(Billrun_Base::base_dateformat);
			$newEvent['aid'] = $row['aid'];
			$newEvent['sid'] = $row['sid'];
			$newEvent['source'] = 'billing';
			$newEvent['event_type'] = 'FP_NATIONAL2';
			$newEvent['value_before'] = $sum;			
			
			if($filter_by == 'usagev') {
				$newEvent['value'] = $row['usagev'];
			} else {
				$newEvent['value'] = $price;
			}

			switch ($usage_type) {
				case 'call':
					$newEvent['threshold'] = Billrun_Factory::config()->getConfigValue('fraud.limits.call', 600000);
					$newEvent['units'] = 'SEC';
					break;

				case 'sms':
					$newEvent['threshold'] = Billrun_Factory::config()->getConfigValue('fraud.limits.sms', 6000);
					$newEvent['units'] = 'SMS';
					break;

				case 'mms':
					$newEvent['threshold'] = Billrun_Factory::config()->getConfigValue('fraud.limits.sms', 1000);
					$newEvent['units'] = 'NUMBER';
					break;

				case 'data':
					$newEvent['threshold'] = Billrun_Factory::config()->getConfigValue('fraud.limits.sms', 7516192768);
					$newEvent['units'] = 'BYTES';
					break;
				
				case NULL:
				default:
					if($filter_by != 'cost') {
						continue;
					}
					
					$current_plan_name = $row['plan'];

					if(empty($current_plan_name)) {
						$current_plan_name = 'NO_GIFT';
					}

					$newEvent['threshold'] = Billrun_Factory::config()->getConfigValue('fraud.limits.cost.'.$current_plan_name);
					$newEvent['event_type'] = 'FP_NATIONAL1';
					$newEvent['units'] = 'NIS';
					break;
			}

//$current_plan_name = isset($current_plan_name)? $current_plan_name : '';
//echo "\n current_plan_name: ".$current_plan_name."\n";
//echo "\n price: ".$price."\n";
//echo "\n filter_by: ".$filter_by. "\n usage_type: ".$usage_type."\n";
//echo "\n VALUE: ". ($newEvent['value'] + $newEvent['value_before'] - $newEvent['value_before']). "\n LIMIT: ".$newEvent['threshold'];
//echo "\n ----------------------------------------------------------------------------------------------------------------- \n";

			if(($newEvent['value'] + $newEvent['value_before'] - $newEvent['value_before']) >= $newEvent['threshold']) {

				$newEvent['stamp'] = md5(serialize($newEvent));
				$events->save($newEvent);
			}
		}
		
	}

}
