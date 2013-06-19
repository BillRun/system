<?php

/**
 * @package         Gsmodem
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Gsmodem AT command  interface class
 *
 * @package  Gsmodem
 * @since    0.1
 */
class Gsmodem_Gsmodem  {

	//-----------------------------------------------------
	const CONNECTED = "connected";
	const NO_ANSWER = "no_answer";
	const BUSY = "busy";
	const UNKNOWN = "unknown";
	const NO_RESPONSE = "";
	const RINGING = 'ringing';
	const HANG_UP = 'hang_up';
	const HANGING_UP = 'hanging_up';
	
	
	static protected $atCmdMap = array(
							'call' => 'ATD%0%',
							'answer' => 'ATA',
							'hangup' => 'ATH',	
							'reset' => 'ATZ',
						);

	static protected $resultsMap = array( 
							'NO ANSWER' => self::NO_ANSWER,
							'BUSY' => self::BUSY,
							'ERROR' => self::UNKNOWN,
							'NO CARRIER' => self::NO_ANSWER,	
							'OK' => self::CONNECTED,
							'RING' => self::RINGING,					
				);
	//-----------------------------------------------------
	
	/**
	 * TODO
	 * @var type 
	 */
	protected $deviceFD = FALSE;

	/**
	 * TODO
	 * @var type 
	 */
	protected $state = null;
	
	public function __construct($pathToDevice) {
		if(isset($pathToDevice)) {
			$this->deviceFD = @fopen($pathToDevice, 'r+');	
			$this->state = new Gsmodem_ModemState();
		}
		
	}
	
	/**
	 * 
	 * @param type $number
	 * @return type
	 */
	public function call($number) {		
		
		return $this->doCmd($this->getATcmd('call', array($number)));		
	}
	
	/**
	 * 
	 * @return type
	 */
	public function hangUp() {
		return $this->doCmd($this->getATcmd('hangup'));				
	}
	
	/**
	 * 
	 * @param type $waitTime
	 * @return type
	 */
	public function waitForCallToEnd($waitTime = PHP_INT_MAX) {
		//TODO  find out  if this  need to have some kind  of match condition		
		return $this->getResult($waitTime);
	}
	
	/**
	 * 
	 * @param type $waitTime
	 * @return boolean
	 */
	public function waitForCall($waitTime = PHP_INT_MAX) {		
		$startTime = time();
		while($waitTime > time() - $startTime) {
			if(self::RINGING != $this->getResult($waitTime)) {
				return TRUE;
			}
		}
		return FALSE;
	}
	
	/**
	 * 
	 */
	public function answer() {
		$this->doCmd($this->getATcmd('answer'));
	}
	
	/**
	 * 
	 * @return type
	 */
	public function isValid() {
		return $this->deviceFD != FALSE;
	}
	
	//-------------------- PROTECTED -------------------------------
	/**
	 * 
	 * @param type $command
	 * @param type $params
	 * @return type
	 */
	protected function getATcmd($command,$params =array()) {
		$cmdStr = self::$atCmdMap[$command];
		foreach ($params as $key => $value) {
			$cmdStr = preg_replace('/%'.$key.'%/', $value, $cmdStr);
		}
		return $cmdStr . ";\n";
	}
	
	/**
	 * 
	 * @param type $waitTime
	 * @return type
	 */
	protected function getResult($waitTime = PHP_INT_MAX) {
		$res =  FALSE;
		$callResult = "";
		stream_set_blocking($this->deviceFD,FALSE);
		while (($callResult .=  fread($this->deviceFD,4096)) || $waitTime--) {	
		
			if( isset(self::$resultsMap[trim($callResult)])) {
					Billrun_Factory::log()->log(trim($callResult),  Zend_Log::DEBUG);
					$this->state->gotResult(trim($callResult));
					$res = self::$resultsMap[trim($callResult)];
					break;
			}
			sleep(1);
		}

		return $res;
	}
	
	/**
	 * 
	 * @param type $cmd
	 * @param type $getResult
	 * @return type
	 */
	protected function doCmd($cmd,$getResult = true) {
		$this->clearBuffer();
		$res = fwrite($this->deviceFD, $cmd) > 0 && !$getResult;
		fflush($this->deviceFD);	
		$this->state->issuedCommabd($cmd);
		
		return $getResult ?  $this->getResult() :  $res ;
	}
	
	/**
	 * 
	 */
	protected function clearBuffer() {
		stream_set_blocking($this->deviceFD,FALSE);
		fread($this->deviceFD,4096);
		stream_set_blocking($this->deviceFD,TRUE);		

	}
	
}
