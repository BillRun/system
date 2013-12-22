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
class RetrivecallsAction extends Action_Base {
	/**
	 *
	 */
	public function execute() {
		Billrun_Factory::log()->log("Executing  call retriving from generator Action", Zend_Log::INFO);
		$configCol = Billrun_Factory::db()->configCollection();
		
		$mangement = $configCol->query(array('key'=> 'call_generator_management'))->cursor()->current();
		if($mangement->isEmpty()) {
			Billrun_Factory::log()->log("ALERT! : No generator registered yet..",Zend_Log::ALERT);					
		}
		foreach (Billrun_Util::getFieldVal($mangement['generators'],array()) as $ip => $generatorData) {
			$ip = preg_replace("/_/", ".", $ip);
			switch($this->options['action']) {
				case 'sync_db' :
					$this->syncThroughDb($ip);
					break;
				case 'sync_http' :
					$generatorLines = $this->getGenetatedCalls($ip);
					$savedCalls = $this->saveGenetatedCalls($generatorLines);
					$this->removeSavedCalls($savedCalls);
					break;
			}
		}
		Billrun_Factory::log()->log("Finished Executeing call retriving from generator Action", Zend_Log::INFO);
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
	 * 
	 * @param type $savedCalls
	 * @return boolean
	 */
	protected function removeSavedCalls($savedCalls) {
		$url = "http://$ip/api/operations/?action=gremove_calls";
		$ret = json_decode($this->delayedHTTP($url,array( 'remote_db'=> Billrun_Factory::config()->getConfigValue('db'), 'calls_to_remove' => $savedCalls ), 0));

		return $ret;
	}
	
	/**
	 * TODO
	 * @return array
	 */
	protected function getGenetatedCalls() {		
		
		$url = "http://$ip/api/operations/?action=get_calls";
		$lastCall = $this->lastRecordedCall();
		$calls = json_decode($this->delayedHTTP($url,array( 'from'=> Billrun_Util::getFieldVal($lastCall['urt']->sec,0), 'to' => time()), 0));//TODO  load the last  call in the DB

		return $calls;
	}
	
	/**
	 * TODO
	 * @param type $calls
	 * @return type
	 */
	protected function saveGenetatedCalls($calls) {
		$savedCalls = array();
		foreach ($calls as $call) {
			unset($call['_id']);
			try {
				if( Billrun_Factory::db()->linesCollection()->save($call)) {
					$savedCalls[] = $call;
				}
				
			} catch( Exception $e) {
				//TODO  catch duplicate and handle them.
			}
		}
		return $saveCalls;
	}
	
	/**
	 * Get the last call that  was recorded in the DB.
	 */
	protected function lastRecordedCall() {
		return Billrun_Factory::db()->linesCollection()->query(array('type'=> 'generated_call'))->cursor()->sort(array('urt' => -1))->limit(1)->current();
	}
	
	/**
	 * TODO
	 * @param type $calls
	 * @return type
	 */
	protected function syncThroughDb($ip) {		
		$url = "http://$ip/api/operations/?action=sync_calls";
		$lastCall = $this->lastRecordedCall();
		return $this->delayedHTTP($url,array(
										'remote_db'=> Billrun_Factory::config()->getConfigValue('db'),
										'from'=> Billrun_Util::getFieldVal($lastCall['urt']->sec,0),'to' => time(),
									), 0);
	}

	protected function delayedHTTP($url, $data = array(), $delay = 0) {		
			$gen = Billrun_Generator::getInstance(array('type'=>'state'));
			sleep($delay);			
			$client = curl_init($url);
			$post_fields = array('data' => json_encode($data));
			curl_setopt($client, CURLOPT_POST, TRUE);
			curl_setopt($client, CURLOPT_POSTFIELDS, $post_fields);
			curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
			return curl_exec($client);						
	}
}