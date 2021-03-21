<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
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
	 * @deprecated since version 0.4 use Billrun_Util::getLastChargeTime instead
	 */
	protected function get_last_charge_time($return_timestamp = false) {
		Billrun_Factory::log("Billrun_Plugin_BillrunPluginFraud::get_last_charge_time is deprecated; please use Billrun_Util::getLastChargeTime()", Zend_Log::DEBUG);
		return Billrun_Util::getLastChargeTime($return_timestamp);
	}

	/**
	 * method to collect data which need to be handle by event
	 */
	abstract public function handlerCollect($options);

	/**
	 * Write all the threshold that were broken as events to the db events collection 
	 * @param type $items the broken  thresholds
	 * @param type $pluginName the plugin that identified the threshold breakage
	 * @return type
	 */
	public function handlerAlert(&$items, $pluginName) {
		if ($pluginName != $this->getName() || !$items) {
			return;
		}

		$events = Billrun_Factory::db()->eventsCollection();
		//Billrun_Factory::log("New Alert For {$item['imsi']}",Zend_Log::DEBUG);
		$ret = array();
		foreach ($items as &$item) {
			$event = new Mongodloid_Entity($item);
			unset($event['lines_stamps']);

			$newEvent = $this->addAlertData($event);
			$newEvent['source'] = $this->getName();
			$newEvent['stamp'] = md5(serialize($newEvent));
			$newEvent['creation_time'] = date(Billrun_Base::base_datetimeformat);
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
			$query = array('stamp' => array('$in' => $item['lines_stamps']));
			$values = array('$set' => array('event_stamp' => $item['event_stamp']));
			$options = array('multiple' => true);
			$ret[] = $lines->update($query, $values, $options);
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
