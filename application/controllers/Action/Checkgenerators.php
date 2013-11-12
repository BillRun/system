<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Verify that the generators are running ok.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       1.0
 */
class CheckgeneratorsAction extends Action_Base {

	const TIME_OFFEST_WARRNING =5;
	const TIME_OFFEST_ALERT =7;
	const RECEIVED_TIME_OFFSET_WARNING = 300;
	const RECEIVED_TIME_OFFSET_ALERT = 600;
	
	/**
	 *
	 */
	public function execute() {
		$configCol = Billrun_Factory::db()->configCollection();
		
		$mangement = $configCol->query(array('key'=> 'call_generator_management'))->cursor()->current();
		foreach ($mangement['generators'] as $ip => $generatorData) {
			if($this->isInActivePeriod()) {
				if( time() - $generatorData['recieved_timestamp'] > static::RECEIVED_TIME_OFFSET_WARNING ) {
					Billrun_Factory::log()->log("Warning :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_WARNING." seconds",Zend_Log::WARN);
				}
				if( time() - $generatorData['recieved_timestamp'] > static::RECEIVED_TIME_OFFSET_ALERT ) {
					Billrun_Factory::log()->log("ALERT! :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_ALERT." seconds",Zend_Log::ALERT);
				}
				if(date("Y:m:00") > $generatorData['next_action']['time']) {
				
				}
			}
			if(abs($generatorData['timestamp'] - $generatorData['recieved_timestamp']) > static::TIME_OFFEST_WARRNING ) {
				Billrun_Factory::log()->log("Warning :  $ip clock  seem to be out of sync  by: ". $generatorData['timestamp'] - $generatorData['recieved_timestamp']. " seconds",Zend_Log::WARN);
			}
			if(abs($generatorData['timestamp'] - $generatorData['recieved_timestamp']) > static::TIME_OFFEST_ALERT ) {
				Billrun_Factory::log()->log("ALERT! :  $ip clock seem to be out of sync  by: ". $generatorData['timestamp'] - $generatorData['recieved_timestamp']. " seconds",Zend_Log::ALERT);
			}
		}
		return true;

	}

	protected function isInActivePeriod() {
		$config = $configCol->query(array('key'=> 'call_generator'))->cursor()->sort(array('urt'=> -1))->limit(1)->current();
		usort($script, function($a,$b) { return strcmp($a['time'], $b['time']);});
		$script = $config['test_script'];
		foreach ($script as $scriptAction) {
			if ($scriptAction['time'] > date("H:i:s") &&
				$this->isConnectedModemNumber(array($scriptAction['from'], $scriptAction['to']))) {
					return true;
			}
		}
		return false;
	}
}