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
	const RECEIVED_TIME_OFFSET_ALERT = 500;
	const RECEIVED_TIME_OFFSET_RESET = 600;
	const RECEIVED_TIME_OFFSET_REBOOT = 900;
	
	/**
	 *
	 */
	public function execute() {
		Billrun_Factory::log()->log("Executing check generator Action", Zend_Log::INFO);
		$configCol = Billrun_Factory::db()->configCollection();
		
		$mangement = $configCol->query(array('key'=> 'call_generator_management'))->cursor()->current();
		if($mangement->isEmpty()) {
			Billrun_Factory::log()->log("ALERT! : No generator registered yet..",Zend_Log::ALERT);					
		}
		foreach (Billrun_Util::getFieldVal($mangement['generators'],array()) as $ip => $generatorData) {
			$ip = preg_replace("/_/", ".", $ip);
			if(!$this->isInActivePeriod()) {
				if(time() - $generatorData['receieved_timestamp'] > static::RECEIVED_TIME_OFFSET_REBOOT ) {
					Billrun_Factory::log()->log("ALERT! :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_REBOOT." seconds, Rebooting...",Zend_Log::ALERT);
					$this->rebootGenerator($ip, $generatorData);
				} else 	if(time() - $generatorData['receieved_timestamp'] > static::RECEIVED_TIME_OFFSET_RESET ) {
					Billrun_Factory::log()->log("ALERT! :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_RESET." seconds Reseting the modems",Zend_Log::ALERT);
					$this->handleFailures($ip, $generatorData);
				} else if( time() - $generatorData['receieved_timestamp'] > static::RECEIVED_TIME_OFFSET_ALERT ) {
					Billrun_Factory::log()->log("ALERT! :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_ALERT." seconds",Zend_Log::ALERT);
				} else 	if( time() - $generatorData['receieved_timestamp'] > static::RECEIVED_TIME_OFFSET_WARNING ) {
					Billrun_Factory::log()->log("Warning :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_WARNING." seconds",Zend_Log::WARN);
				}
				
			}
			if(abs($generatorData['timestamp'] - $generatorData['receieved_timestamp']) > static::TIME_OFFEST_WARRNING ) {
				Billrun_Factory::log()->log("Warning :  $ip clock  seem to be out of sync  by: ". $generatorData['timestamp'] - $generatorData['receieved_timestamp']. " seconds",Zend_Log::WARN);
			}
			if(abs($generatorData['timestamp'] - $generatorData['receieved_timestamp']) > static::TIME_OFFEST_ALERT ) {
				Billrun_Factory::log()->log("ALERT! :  $ip clock seem to be out of sync  by: ". $generatorData['timestamp'] - $generatorData['receieved_timestamp']. " seconds",Zend_Log::ALERT);
			}
			if(Billrun_Util::getFieldVal($generatorData['state'],'start') == 'failed') {
				//$this->handleFailures($ip, $generatorData);
			}
		}
		Billrun_Factory::log()->log("Finished Executeing check generator Action", Zend_Log::INFO);
		return true;

	}

	protected function isInActivePeriod() {
		$config = Billrun_Factory::db()->configCollection()->query(array('key'=> 'call_generator'))->cursor()->sort(array('urt'=> -1))->limit(1)->current();
		$script = $config['test_script'];
		usort($script, function($a,$b) { return strcmp($a['call_id'], $b['call_id']);});
		$start = strtotime(reset($script));
		$end = strtotime(end($script)) - $start;
		$current = time() - $start;
		if ($end > $current ) {
			return true;
		}		
		return false;
	}
	
	/**
	 * stop all generators  and  restart  a failing  one.
	 * @param type $ip the IP  of the  failed  generator
	 * @param type $generatorData the data of the failed  generator.
	 */
	protected function handleFailures($ip,$generatorData) {
		if(Billrun_Util::getFieldVal($generatorData['state'],'start') == 'failed') {
			Billrun_Factory::log()->log("Reseting  generator at : $ip .", Zend_Log::WARN);
			$gen = Billrun_Generator::getInstance(array('type'=>'state'));
			$gen->stop();
			$url = "http://$ip/api/operations/?action=restartModems";
			$this->delayedHTTP($url);
			sleep(20);
			$gen->start();
			Billrun_Factory::log()->log("Finished Reseting  generator at : $ip .", Zend_Log::WARN);
		}
	}
	
	protected function rebootGenerator($ip,$generatorData) {
		Billrun_Factory::log()->log("Rebooting  generator at : $ip .", Zend_Log::WARN);
		$gen = Billrun_Generator::getInstance(array('type'=>'state'));
		$gen->stop();
		$url = "http://$ip/api/operations/?action=reboot";
		$this->delayedHTTP($url);
		sleep(20);
		$gen->start();
		Billrun_Factory::log()->log("Finished Rebooting  generator at : $ip .", Zend_Log::WARN);
	}
		


	protected function delayedHTTP($url) {		
		//if(!pcntl_fork()) {
			$gen = Billrun_Generator::getInstance(array('type'=>'state'));
			sleep(30);			
			$client = curl_init($url);
			$post_fields = array('data' => json_encode(array('action' => 'restartModems')));
			curl_setopt($client, CURLOPT_POST, TRUE);
			curl_setopt($client, CURLOPT_POSTFIELDS, $post_fields);
			curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
			return curl_exec($client);				
		//	die();
		///}
	}
}