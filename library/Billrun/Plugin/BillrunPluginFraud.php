<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract fraud plugin class
 *
 * @package  plugin
 * @since    1.0
 */
abstract class Billrun_Plugin_BillrunPluginFraud extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'Fraud';

	/**
	 * helper method to receive the last time of the monthly charge
	 * 
	 * @param boolean $return_timestamp if set to true return time stamp else full format of yyyymmddhhmmss
	 * 
	 * @return mixed timestamp or full format of time
	 */
	protected function get_last_charge_time($return_timestamp = false) {
		$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25);
		$format = "Ym" . $dayofmonth . "000000";
		if (date("d") >= $dayofmonth) {
			$time = date($format);
		} else {
			$time = date($format, strtotime('-1 month'));
		}
		if ($return_timestamp) {
			return strtotime($time);
		}
		return $time;
	}
	
	/**
	 * method to collect data which need to be handle by event
	 */
	abstract public function handlerCollect();

	/**
	 * 
	 * @param type $items
	 * @param type $pluginName
	 */
	public function handlerAlert(&$items, $pluginName);

	/**
	 * method to markdown all the lines that triggered the event
	 * 
	 * @param array $items the lines
	 * @param string $pluginName the plugin name which triggered the event
	 * 
	 * @return array affected lines
	 */
	public function handlerMarkDown(&$items, $pluginName) {
		if ($pluginName != $this->getName() || !$items) {
			return;
		}

		$ret = array();
		$db = Billrun_Factory::db();
		$lines = $db->getCollection($db::lines_table);
		foreach ($items as &$item) {
			$ret[] = $lines->update(array('stamp' => array('$in' => $item['lines_stamps'])), array('$set' => array('event_stamp' => $item['event_stamp'])), array('multiple' => 1));
		}
		return $ret;
	}

}