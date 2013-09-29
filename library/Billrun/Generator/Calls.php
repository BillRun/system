<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing call generator class
 * Make and  receive call  base on several  parameters
  *
 * @package  Billing
 * @since    0.5
 */
class Billrun_Generator_Calls extends Billrun_Generator {
	
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'calls';

	
	protected $calls = array();

	/**
	 * The calling device.
	 * @var Gsmodem_Gsmodem
	 */
	protected $modemDevices = array();

	public function __construct($options) {
		parent::__construct($options);
		if(isset($options['path_to_calling_devices'])) {
			foreach ($options['path_to_calling_devices'] as $value) {
				$this->modemDevices[] = new Gsmodem_Gsmodem($value);	
			}
		}
	}
	
	/**
	 * Generate the calls as defined in the configuration.
	 */
	public function generate() {	
		$isCalling= $this->getConfig('direction') == 'calling';
		$callsMade = array();			
		$callsBehavior= Billrun_Factory::config()->getConfigValue(($isCalling ? 'calls.calling_behavior' :'calls.answering_behavior' ) ,array($this->options));
		$callsIdx = 0;

		if($this->modemDevice && $this->modemDevice->isValid()) {
			//make the calls and remember their results
			for($i=0; $i < $this->getConfig('times'); $i++) {
				$behavior = $callsBehavior[$callsIdx];
				Billrun_Factory::log()->log(print_r($behavior,1),  Zend_Log::DEBUG);
				$call = $this->getEmptyCall($isCalling ? 'calling' : 'answering');
				if( $isCalling ) {
					$this->makeACall($call, $this->getConfig('number_to_call', $behavior));
				} else {
					$this->waitForCall(	$call, $this->getConfigValue('should_answer', $behavior), $this->getConfig('ignore_call' ,$behavior) );
				}

				if($call['calling_result'] == Gsmodem_Gsmodem::CONNECTED ) {
					$this->HandleCall($call, $this->getConfig('call_wait_time',$behavior));
				}
				$call['execution_end_time'] = date("YmdTHis");
				$callsMade[] = $call;
				
				sleep($this->getConfig('interval'));
				if(isset($callsBehavior[$callsIdx+1])) {
					$callsIdx++;
				}
			}	
			
		Billrun_Factory::log()->log(print_r($callsMade,1),  Zend_Log::DEBUG);
		$this->save($callsMade);

		} else {
			Billrun_Factory::log()->log("couldn't use specified device : {$this->options['path_to_calling_device']}",  Zend_Log::INFO);
		}
	}
	
	/**
	 * Load the script
	 */	
	public function load() {
		//@TODO load the script from the DB
		$this->testScript = array(
			array('time'=> "00:00:00",'from' => '0580000001', 'to' => '0580000002' ,'duration' => 10 , 'type'=> 'regular'),
			array('time'=> "00:01:00",'from' => '0580000003', 'to' => '0580000004' ,'duration' => 15, 'type'=> 'busy'),
			array('time'=> "00:02:00",'from' => '0580000002', 'to' => '0580000001' ,'duration' => 35, 'type'=> 'voice_mail'),
			array('time'=> "00:03:00",'from' => '0580000004', 'to' => '0580000003' ,'duration' => 80, 'type'=> 'no_answer'),
		);
	}
	/**
	 * 
	 * @param type $script
	 */
	protected function actOnScript($script) {
		while(1) {
			$action = $this->waitForNextAction($script);
			if($action) {
				$this->scriptAction($action);
			}
		}
	}


	/**
	 * 
	 * @param type $script
	 */
	protected function waitForNextAction($script) {
		$actionCount =  count($script);
		$idx = 0;
		while(	$script[$idx % $actionCount] <=  date("H:i:s") || 
				(	!$this->isConnectedModemNumber($script[$idx % $actionCount]['from']) && 
					!$this->isConnectedModemNumber($script[$idx % $actionCount]['to']) )) {
			$idx++;
		} 
		$action = $script[$idx % $actionCount];
		while($script[$idx % $actionCount] > date("H:i:s")) {};
		return $action;
	}
	
	/**
	 * TODO
	 * @param type $action
	 */
	protected function scriptAction($action) {
		$isCalling= $this->isConnectedModemNumber($action['from']) != FALSE;
		$device = $this->isConnectedModemNumber( $action[$isCalling ? 'from' : 'to'] );
		//make the calls and remember their results
		$call = $this->getEmptyCall();

		if( $isCalling ) {
			$this->makeACall($device, $call, $action['to']);
		} else {
			if($action['type'] != 'busy') {
				$this->waitForCall($device,	$call, $action['type'] );
			} else {
				$this->callToBusyNumber($device , $duration);
			}
		}

		if($call['calling_result'] == Gsmodem_Gsmodem::CONNECTED ) {
			$this->HandleCall($device , $call, $this->$action['duration']);
		}
		//$call['execution_end_time'] = date("YmdTHis");
		$this->save($action, $call, $isCalling);
	}	

	/**
	 * Make a call as defiend  by the configuration.
	 * @param type $callRecord the  call record to save to the DB.
	 * @return mixed the call record with the data regarding making the call.
	 */
	protected function makeACall($device , &$callRecord, $numberToCall) {
		$callRecord['calling_result'] = $device->call($numberToCall);
		$callRecord['called_number'] =$numberToCall;

		return $callRecord['calling_result'];
	}

	/**
	 * Wait for a call.
	 * @param mixed $callRecord  the call record to save to the DB.
	 * @return mixed the call record with the data regarding the incoming call.
	 */
	protected function waitForCall($device, &$callRecord , $callType) {
		
		if($device->waitForCall() !== FALSE) {
			if($callType == '') {				
				$callRecord['calling_result'] = $device->answer();
				
			} elseif($callType == '') {				
				 $device->waitForRingToEnd();
				 $callRecord['calling_result'] = 'ignored';
				 
			} else {				
				$callRecord['calling_result'] =  $device->hangUp() ;	
			}	
		}
		
		return $callRecord['calling_result']; 
	}
	
	/**
	 * TODO 
	 */
	protected function callToBusyNumber($device, $duration) {
		$call = array();
		$ret = $this->makeACall($device, $call, $this->getConfig('busy_number'));

		if($ret == Gsmodem_Gsmodem::CONNECTED ) {
			$this->HandleCall($device, $call, $duration);
		}
	}
	
	/**
	 * Handle an active call.
	 * @param type $callRecord the call record to save to the DB.
	 */
	protected  function HandleCall($device, &$callRecord, $waitTime) {
		$callRecord['call_start_time'] = date("YmdTHis");
		$callRecord['end_result'] = $device->waitForCallToEnd($waitTime);
		if($callRecord['end_result'] == Gsmodem_Gsmodem::NO_RESPONSE) {
			$device->hangUp();
			$callRecord['end_result'] = 'hang_up';						
		}
		$callRecord['call_end_time'] = date("YmdTHis");
		$callRecord['duration'] = strtotime($callRecord['call_end_time'] ) - strtotime($callRecord['call_start_time']);		
	}

	/**
	 * Get an empty call record to be save to the DB.
	 * @return Array representing the call record with initailized values. 
	 */
	protected function getEmptyCall() {
		return array(	
						'execution_start_time' => date("YmdTHis"),
						'calling_result' => 'no_call',
						'call_start_time' => null,
						'end_result' => 'no_call',
						'call_end_time' => null,
						'duration' => 0,
						'execution_end_time' => null,
					 );
	}
	
	/**
	 * Save calls made/received to DB.
	 * @param Array $calls containing the call recrods of the calls  that where made/received
	 * @return boolean
	 */
	protected function save($action, $call , $isCalling) {
		
		$lines = Billrun_Factory::db()->generatedCallsCollection();
		$row['source'] = 'generator';			
		$row['unified_record_time'] = new MongoDate(strtotime($row['call_start_time']  ? $row['call_start_time'] : $row['execution_start_time']));
		$row['type'] = static::$type;
		if(!($lines->query(array('stamp'=> $row['stamp'] ) )->cursor()->hasNext() ) )  {				
			$entity = new Mongodloid_Entity($row);
			$entity->save($lines, true);
		} else {
			Billrun_Factory::log()->log("Calls Generator save failed on stamp : {$row['stamp']}", Zend_Log::NOTICE);
		}

		return true;
	}
	
	/**
	 * Get config value for a given call instance and allow  for configuration overide.
	 * @param string $name the config  key  to look for
	 * @param array $callConfig the configuration override.
	 * @return mixed the config value in the override  or if it not found the config value in the general configuration 
	 *			or FALSE if no value  was found to the config key.
	 */
	protected function getConfig($configKey, $callInstanceConfig = array()) {
		 return isset($callInstanceConfig[$configKey]) ? $callInstanceConfig[$configKey] :
				Billrun_Factory::config()->getConfigValue($this->getType().'.'.$configKey, FALSE, 'string');		
	}
	
	/**
	 * Check if a number is one of the connected modem numbers.
	 * @param type $number the number to check.
	 * @return boolean|mixed the modem instace that has the number or false if none of the modem matches. 
	 */
	protected function isConnectedModemNumber($number) {
		foreach ($this->modemDevices as $value) {
			if($value->getModemNumber() == $number) {
				return $value;
			}
		}
		return FALSE;
	}
	
}

