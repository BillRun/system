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
 * @since    0.5
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
	 * Write all the threshold that were broken as events to the db events collection 
	 * @param type $items the broken  thresholds
	 * @param type $pluginName the plugin that identified the threshold breakage
	 * @return type
	 */
	public function handlerAlert(&$items,$pluginName) {
		if($pluginName != $this->getName()) {return;}
		
		$events = Billrun_Factory::db()->eventsCollection();
		//Billrun_Factory::log()->log("New Alert For {$item['imsi']}",Zend_Log::DEBUG);
		$ret = array();
		foreach($items as &$item) { 
			$event = new Mongodloid_Entity($item);
			unset($event['lines_stamps']);
			
			$newEvent = $this->addAlertData($event);
			$newEvent['source']	= $this->getName();
			$newEvent['stamp'] = md5(serialize($newEvent));
			$newEvent['creation_time'] = date(Billrun_Base::base_dateformat);
			$item['event_stamp'] = $newEvent['stamp'];
			
			$ret[] = $events->save($newEvent);
		}
		return $ret; 
	}

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
		$lines = Billrun_Factory::db()->linesCollection();
		foreach ($items as &$item) {
			$ret[] = $lines->update(	array('stamp' => array('$in' => $item['lines_stamps'])),
									array('$set' => array('event_stamp' => $item['event_stamp'])),
									array('multiple' => 1));
		}
		return $ret;
	}

	/**
	 * Add data that is needed to use the event object/DB document later
	 * @param Array|Object $event the event to add fields to.
	 * @return Array|Object the event object with added fields
	 */
	abstract protected function addAlertData(&$event);
}