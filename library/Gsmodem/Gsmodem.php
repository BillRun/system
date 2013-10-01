<?php

/**
 * @package         Gsmodem
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Gsmodem AT command interface class
 *
 * @package  Gsmodem
 * @since    0.1
 */
class Gsmodem_Gsmodem  {

	//-------------------------- CONSTANTS / STATICS ---------------------------
	
	const CONNECTED = "connected";
	const NO_ANSWER = "no_answer";
	const CALL_DISCONNECTED = "call_disconnected";
	const BUSY = "busy";
	const UNKNOWN = "unknown";
	const NO_RESPONSE = "";
	const RINGING = 'ringing';
	const HANG_UP = 'hang_up';
	const HANGING_UP = 'hanging_up';
	
	
	static protected $atCmdMap = array(
							'call' => 'D%0%',
							'answer' => 'A',
							'hangup' => 'H',	
							'reset' => 'Z',
							'register' => '+COPS=%0%',
							'register_reporting' => '+CREG=%0%',
							'incoming_call_id' => '+CLIP=%0%',		
							'get_error' => '+CEER',
							'get_number' => '+CNUM'
						);

	static protected $resultsMap = array( 
							'NO ANSWER' => self::NO_ANSWER,
							'BUSY' => self::BUSY,
							'ERROR' => self::UNKNOWN,
							'NO CARRIER' => self::CALL_DISCONNECTED,	
							'OK' => self::CONNECTED,
							'RING' => self::RINGING,					
					);
	
	//--------------------------------------------------------------------------
	
	/**
	 * Hold the modem file descriptor.
	 * @var resource 
	 */
	protected $deviceFD = FALSE;

	/**
	 * Hold the state that thmodem is at (@see Gsmodem_ModemState)
	 * @var \Gsmodem_ModemState 
	 */
	protected $state = null;
	
	/**
	 * the current phone number.
	 * @var string 
	 */
	protected $number = false;
	
	public function __construct($pathToDevice) {
		if(isset($pathToDevice)) {
			$this->deviceFD = @fopen($pathToDevice, 'r+');	
			$this->state = new Gsmodem_ModemState();
			$this->initModem();
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
	 * Hang up an active call 
	 * @return TRUE if the command  was sent OK false otherwise.
	 */
	public function hangUp() {
		return  $this->state->getState() != Gsmodem_StateMapping::IDLE_STATE && 
				$this->doCmd($this->getATcmd('hangup')) ? self::HANGING_UP : self::UNKNOWN;						
	}
	
	/**
	 * Wait for the current call to end
	 * @param type $waitTime (optional) the maximum amount of time to wait  before returning.
	 * @return boolean|string FALSE  if the waiting timedout  or the call result if  the call was ended
	 */
	public function waitForCallToEnd($waitTime = PHP_INT_MAX) {
		$lastResult= FALSE;
		 while ( ($this->state->getState() == Gsmodem_StateMapping::IN_CALL_STATE || $this->state->getState() == Gsmodem_StateMapping::OUT_CALL_STATE)) {
			if( ($lastResult = $this->getResult($waitTime) ) == FALSE) {
				break;				
			}
		}
		return $lastResult;
	}
	
	/**
	 * Wait for the phone to stop ringing (incoming call has stopped).
	 * @param type $waitTime (optional) the maximum amount of time to wait before returning.
	 * @return boolean|string FALSE if the waiting timed out TRUE if the phone has stopped ringing.
	 */
	public function waitForRingToEnd($waitTime = PHP_INT_MAX) {
		$lastResult= FALSE;
		while($this->state->getState() == Gsmodem_StateMapping::RINGING_STATE ) {
			if( ($lastResult = $this->getResult($waitTime) ) == FALSE) {
				break;				
			}
		}
		
		return $lastResult != FALSE;
	}

	/**
	 * Register to the GSM  network
	 */
	public function registerToNet() {
		Billrun_Factory::log("Registering to network");
		$res = $this->doCmd($this->getATcmd('register',array(0)), true, false);		
		
	}
	
	/**
	 * unregistyer from the GSM network (go off line)
	 */
	public function unregisterFromNet() {
		Billrun_Factory::log("Unregistering to network");
		$this->doCmd($this->getATcmd('register',array(2)), true);
	}	
	
	/**
	 * Wait for the phone to ring (recieve incoming call).
	 * @param type $waitTime (optional) the maximum amount of time to wait before returning.
	 * @return  boolean|string FALSE if the waiting timed out TRUE if the phone is ringing.
	 */
	public function waitForCall($waitTime = PHP_INT_MAX) {		
		$startTime = time();
		while($waitTime > time() - $startTime) {
			 $lastResult = $this->getResult($waitTime);
			if($this->state->getState() == Gsmodem_StateMapping::RINGING_STATE) {
				return $lastResult;
			}
		}
		return FALSE;
	}
	
	/**
	 * Answer an incoming call
	 * @return 
	 */
	public function answer() {
		return $this->doCmd($this->getATcmd('answer'),true);
	}
	
	/**
	 * Retrive the modem phone number
	 */
	public function getModemNumber() {		
		if(!$this->number) {
			$matches = array();
			$this->number = preg_match('/.+\"(\d+)\".+/', $this->doCmd($this->getATcmd('get_number'), true, false), $matches ) > 0  ? $matches[1] : false;
		}
		
		return $this->number;
	}
	
	public function setNumber($number) {
		$this->number =$number;
	}
	
	/**
	 * Check if the current instance is  valid  and  can be used.
	 * @return	TRUE if  you can use this instance ,
	 *			FALSE if some problem occured and  you better of using  another one (if this  happens too much yell at you sysadmin... :P)
	 */
	public function isValid() {
		return $this->deviceFD != FALSE;
	}
	
	/**
	 * Initialize the modem settings.
	 */
	public function initModem() {
		$this->hangUp();
		$this->doCmd($this->getATcmd('incoming_call_id',array(1)), true, false);
		$this->doCmd($this->getATcmd('register_reporting',array(2)), true, false);
	}


	//----------------------------- PROTECTED ----------------------------------
	
	/**
	 * Get the command string to pass to the modem
	 * @param type $command the  command we  want to issue.
	 * @param type $params the parameters  we should passto the command (phone number,baud value, etc..)
	 * @return string the AT string to pass to the modem inorder to execute the command.
	 */
	protected function getATcmd($command,$params =array()) {
		$cmdStr = 'AT' . self::$atCmdMap[$command];
		foreach ($params as $key => $value) {
			$cmdStr = preg_replace('/%'.$key.'%/', $value, $cmdStr);
		}
		return  $cmdStr . ";\n";
	}
	
	/**
	 * Get result/message from the modem
	 * @param type $waitTime thwe amount of time to wait for the result.
	 * @return string|boolean the result/message string we got  from the modem or false if the waiting timed out.
	 */
	protected function getResult($waitTime = PHP_INT_MAX, $translate = true) {
		$res =  FALSE;
		$callResult = "";
		stream_set_blocking($this->deviceFD,FALSE);
		while (( $newData = fread($this->deviceFD,4096)) || --$waitTime  > 0) {	
			$callResult .= $newData ;
			//Billrun_Factory::log()->log(trim($callResult),  Zend_Log::DEBUG);
			if( $translate && isset(self::$resultsMap[trim($callResult)])) {
					
					$this->state->gotResult(trim($callResult));
					$res = self::$resultsMap[trim($callResult)];
					break;
			} else if(!$translate && $callResult && !$newData ){
				$res = $callResult;
				break;
			}
			sleep(1);
		}

		return $res;
	}
	
	/**
	 * Issue an AT command to the modem (use this  and  getAtCmd)
	 * @param type	$cmd the AT  command string to issue.
	 * @param type	$getResult (optional) should we wait for a result?  ( default to FALSE )
	 * @return mixed	FALSE if the there was a problem to issue the command 
	 *					or if the $getReesult flag is true  the  value that was returned by the modem.
	 */
	protected function doCmd($cmd,$getResult = true, $translate = true) {
		$this->clearBuffer();
		$res = fwrite($this->deviceFD, $cmd) > 0 && !$getResult;
		fflush($this->deviceFD);	
		$this->state->issuedCommand($cmd);
		
		return $getResult ?  trim($this->getResult(PHP_INT_MAX, $translate)) :  $res > 0  ;
	}
	
	/**
	 * clear the modem buffer.
	 */
	protected function clearBuffer() {
		stream_set_blocking($this->deviceFD,FALSE);
		fread($this->deviceFD,4096);
		stream_set_blocking($this->deviceFD,TRUE);		

	}
	
	protected function getValueFromResult($resultKey,$result) {
		$matches = array();
		return preg_match("/.+\+$resultKey:\s*(.+)$/", $result, $matches ) > 0  ? split(",", $matches[1]) : false;
	}
	
}
