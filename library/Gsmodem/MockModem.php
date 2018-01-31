<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Gsmodem_MockModem extends Gsmodem_Gsmodem  {
	static $modems = array();
	
	public function __construct($pathToDevice, $stateMapper = false) {
		$this->pathToDevice = $pathToDevice;
		static::$modems[$this->pathToDevice] = '';			
		$this->state = $stateMapper ? new Gsmodem_ModemState( Gsmodem_StateMapping::IDLE_STATE ,$stateMapper) : new Gsmodem_ModemState();		
	}
	
	protected function doCmd($cmd, $getResult = true, $translate = true, $stateChange = false, $waitTime = PHP_INT_MAX) {
		if(preg_match('/ATD/',$cmd)) {
			$number = preg_replace('/[^\d]/','',$cmd);
			static::$modems[$number] = "RING\n";
		}
		return "OK\n";
	}
	
	protected function getResult($waitTime = 1000, $translate = true, $waitForStateChange = false) {
		$res =  FALSE;
		$callResult = "";	
		$beginningState = $this->getState();		
		$startTime = microtime(true);
		usleep(10);
		while (( $newData = static::$modems[$this->pathToDevice] ) || $waitTime > microtime(true) - $startTime ) {
			$callResult .= $newData ;
			//Billrun_Factory::log()->log(trim($callResult),  Zend_Log::DEBUG);
			if( preg_match("/\n/",$callResult) ) {
				foreach (explode("\n",$callResult) as $value) {
					$this->state->gotResult($value);
				}
				if( $translate ) {
					foreach (explode("\n",$callResult) as $value) {
						//Billrun_Factory::log()->log(trim($value),  Zend_Log::DEBUG);
						if(isset($this->state->getResultMapping()[trim($value)])) {
							$res = $this->state->getResultMapping()[trim($value)];
							if(!$waitForStateChange || $this->getState() != $beginningState) {								
								break 2;
							}
						}
					}
					$callResult = "";
				} else {
					$res = $callResult;
					break;
				}
			}
			//wait  for additional input from the device.
			usleep(5);
		}
		return $res;
	}
	
	public function registerToNet() {
		return true;
	}
	
	public function isRegisteredToNet() {
		return true;
	}
	
	public function getModemNumber() {
		return $this->pathToDevice;
	}
	
	public function getImei() {
		return '0'.$this->pathToDevice;
	}
	
	public function isValid() {
		return true;
	}
	
}