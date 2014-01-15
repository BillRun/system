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
	const RECEIVED_TIME_OFFSET_WARNING = 320;
	const RECEIVED_TIME_OFFSET_ALERT = 380;	
	const RECEIVED_TIME_OFFSET_REBOOT = 500;
	const RECEIVED_TIME_OFFSET_RESET = 1200;
	
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
		foreach (Billrun_Util::getFieldVal($mangement->getRawData()['generators'],array()) as $ip => $generatorData) {
			$ip = preg_replace("/_/", ".", $ip);
			$activeTime = $this->isInActivePeriod();
			
			if($activeTime) {
				$failedConnectionTime = min(array(time() - $generatorData['last_failed_connection'],$activeTime));
				$inactiveTime = min(array(time() - $generatorData['receieved_timestamp'],$activeTime));
				$lastReset = min( array( 
									time() - Billrun_Util::getFieldVal ($generatorData['last_reset'], 0), 
									$inactiveTime,
									$failedConnectionTime,
									$activeTime
							) );
				$lastReboot = min( array( 
									time() - Billrun_Util::getFieldVal ($generatorData['last_reboot'], 0), 
									$inactiveTime,
									$failedConnectionTime,
									$activeTime
								) );
				
				if( $lastReset > static::RECEIVED_TIME_OFFSET_RESET ) {
					Billrun_Factory::log()->log("ALERT! :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_RESET." seconds Reseting the modems",Zend_Log::ALERT);
					$this->handleFailures($ip, $generatorData);
				} else 
				if( $lastReboot > static::RECEIVED_TIME_OFFSET_REBOOT ) {
					Billrun_Factory::log()->log("ALERT! :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_REBOOT." seconds, Rebooting...",Zend_Log::ALERT);
					$this->rebootGenerator($ip, $generatorData);
				} else 					
				if( $inactiveTime > static::RECEIVED_TIME_OFFSET_ALERT ) {
					Billrun_Factory::log()->log("ALERT! :  $ip didn't reported for more then an ".static::RECEIVED_TIME_OFFSET_ALERT." seconds",Zend_Log::ALERT);
				} else 
				if( $inactiveTime > static::RECEIVED_TIME_OFFSET_WARNING ) {					
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
		usort($script, function($a,$b) { return intval($a['call_id']) - intval($b['call_id']);});
		$start = strtotime(reset($script)['time']);
		$end = strtotime(end($script)['time']) ;
		$endOffset = $end > $start  ? $end - $start : $end + 86400 - $start;
		$currentOffset = time() - $start;
		
		if ($endOffset > $currentOffset && $currentOffset > 0) {
			return $currentOffset;
		}		
		return false;
	}
	
	/**
	 * stop all generators  and  restart  a failing  one.
	 * @param type $ip the IP  of the  failed  generator
	 * @param type $generatorData the data of the failed  generator.
	 */
	protected function handleFailures($ip,$generatorData) {		
	//	if(Billrun_Util::getFieldVal($generatorData['state'],'start') == 'failed') {
			Billrun_Factory::db()->configCollection()->findAndModify(array('key'=> 'call_generator_management'),array('$set'=>array('generators.'.preg_replace("/\./","_",$ip).'.last_reset' => time())),array(),array('upsert'=>1));		
			Billrun_Factory::log()->log("Reseting  generator at : $ip .", Zend_Log::WARN);
			$url = "http://$ip/api/operations/?action=restartModems";
			if(!$this->delayedHTTP($url) ) {
				Billrun_Factory::db()->configCollection()->findAndModify(array('key'=> 'call_generator_management'),array('$set'=>array('generators.'.preg_replace("/\./","_",$ip).'.last_failed_connection' => time())),array(),array('upsert'=>1));		
			}	
			
			$url = "http://$ip/api/operations/?action=start";
			$this->delayedHTTP($url,20);
			
			Billrun_Factory::log()->log("Finished Reseting  generator at : $ip .", Zend_Log::WARN);
	//	}
	}
	
	protected function rebootGenerator($ip,$generatorData) {
		Billrun_Factory::db()->configCollection()->findAndModify(array('key'=> 'call_generator_management'),array('$set'=>array('generators.'.preg_replace("/\./","_",$ip).'.last_reboot' => time())),array(),array('upsert'=>1));		
		Billrun_Factory::log()->log("Rebooting  generator at : $ip .", Zend_Log::WARN);
		$url = "http://$ip/api/operations/?action=reboot";
		if(!$this->delayedHTTP($url) ) {
			Billrun_Factory::db()->configCollection()->findAndModify(array('key'=> 'call_generator_management'),array('$set'=>array('generators.'.preg_replace("/\./","_",$ip).'.last_failed_connection' => time())),array(),array('upsert'=>1));		
		}	
		
		$url = "http://$ip/api/operations/?action=start";
		$this->delayedHTTP($url,20);
		
		Billrun_Factory::log()->log("Finished Rebooting  generator at : $ip .", Zend_Log::WARN);
	}
		


	protected function delayedHTTP($url,$delay = 30,$data = array()) {		
		//if(!pcntl_fork()) {			
			sleep($delay);			
						try {
				$client = new Zend_Http_Client($url);
				//$client->setConfig(array('timeout'=>120));
				stream_set_timeout($client->getStream(),120);
				$client->setParameterPost( array( 'data'  => json_encode($data) ) );
				$response = $client->request('POST');

				return $response;
			} catch ( Exception $e ) {
				Billrun_Factory::log()->log("When trying to send request to {$url} , Got exception : ".$e->getMessage(), Zend_Log::ERR);		
				$mailer = Billrun_Factory::mailer();
				$mailer->setSubject("Server isn't connected to the network");
				$mailer->setBodyText("Failed to connect with server at url $url");
				foreach (Billrun_Factory::config()->getConfigValue('log.email.writerParams.to') as $email) {
					$mailer->addTo($email);
				}
				
				return false;
			}
		//	die();
		///}
	}
	
}