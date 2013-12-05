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

	const CONNECTED = "connected";
	const NO_ANSWER = "no_answer";
	const CALL_DISCONNECTED = "call_disconnected";
	const BUSY = "busy";
	const UNKNOWN = "unknown";
	const NO_RESPONSE = "";
	const RINGING = 'ringing';
	const HANG_UP = 'hang_up';
	const HANGING_UP = 'hanging_up';

	const COMMAND_RESPONSE_TIME = 30; // the amount of seconds to wait  for a response from the modem to a given command.
	const RESPONSIVE_RESULTS_TIMEOUT = 0.2; 
	
	//--------------------------------------------------------------------------
	
	/**
	 * Hold the modem file descriptor.
	 * @var resource 
	 */
	protected $deviceFD = FALSE;

	
	/**
	 * Hold the modem file descriptor.
	 * @var resource 
	 */
	protected $pathToDevice = FALSE;
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
	
	public function __construct($pathToDevice, $stateMapper = false) {
		$this->pathToDevice= $pathToDevice;
		if(isset($pathToDevice)) {
			$this->deviceFD = @fopen($this->pathToDevice, 'r+');	
			$this->state = $stateMapper ? new Gsmodem_ModemState( Gsmodem_StateMapping::IDLE_STATE ,$stateMapper) : new Gsmodem_ModemState();			
			if($this->isValid()) {
				$this->initModem();
			}
		}		
	}
	
	/**
	 * 
	 * @param type $number
	 * @return type
	 */
	public function call($number) {		
		$this->hangUp();
		$ret =  $this->doCmd($this->getATcmd('call', array($number)), true, true, true, self::COMMAND_RESPONSE_TIME);		

		return $ret;
	}
	
	/**
	 * Hang up an active call 
	 * @return TRUE if the command  was sent OK false otherwise.
	 */
	public function hangUp() {
		return $this->doCmd($this->getATcmd('hangup'), true, true, false, self::COMMAND_RESPONSE_TIME) != FALSE;
	}
	
	/**
	 * Wait for the current call to end
	 * @param type $waitTime (optional) the maximum amount of time to wait  before returning.
	 * @return boolean|string FALSE  if the waiting timedout  or the call result if  the call was ended
	 */
	public function waitForCallToEnd($waitTime = PHP_INT_MAX) {
		Billrun_Factory::log("Waiting for call to end for $waitTime seconds");
		$lastResult= FALSE;
		$startTime = microtime(true);
		 while (($waitTime > microtime(true) - $startTime) && 
				($this->state->getState() == Gsmodem_StateMapping::IN_CALL_STATE || $this->state->getState() == Gsmodem_StateMapping::OUT_CALL_STATE)) {
			 
			$lastResult = $this->getResult(static::RESPONSIVE_RESULTS_TIMEOUT);
			
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
		$startTime = microtime(true);
		while(	($waitTime > microtime(true) - $startTime) && 
				$this->state->getState() == Gsmodem_StateMapping::RINGING_STATE ) {
			
				 $lastResult = $this->getResult(static::RESPONSIVE_RESULTS_TIMEOUT);
				 
		}
		
		return $lastResult != FALSE;
	}

	/**
	 * Register to the GSM  network
	 */
	public function registerToNet() {
		Billrun_Factory::log("Registering to network");
		$ret = FALSE;
		$res = $this->doCmd($this->getATcmd('register',array(0)), true, false, false ,self::COMMAND_RESPONSE_TIME);	
		$startTime = microtime(true);
		do {
			$res = $this->getResult(self::RESPONSIVE_RESULTS_TIMEOUT,false);
			$ret = Billrun_Util::getFieldVal($this->getValueFromResult('CREG', $res)[0][0],false);
			if($ret == 5) {
				Billrun_Factory::log("Registaered on  a roaming  network  trying to register  to golan...");
				$this->unregisterFromNet();
				$this->doCmd($this->getATcmd('register_extended',array(1,2,42508)), true, false, false ,self::COMMAND_RESPONSE_TIME);//@TODO	Move this  behavior to configuration.
			}
		} while ((self::COMMAND_RESPONSE_TIME > microtime(true) - $startTime) && $ret != 1);
		$this->state->setState(Gsmodem_StateMapping::IDLE_STATE);
		return $ret;
		
	}
	
	
	/**
	 * Check if the modem is registered correctly to the GSM  network.
	 * @return true  if it is  false otherwise.
	 */
	public function isRegisteredToNet() {	
		$res = $this->doCmd($this->getATcmd('register_status'), true, false,false,self::COMMAND_RESPONSE_TIME);	
		$ret = Billrun_Util::getFieldVal($this->getValueFromResult('CREG', $res)[0][1],false) == 1;	
		return $ret;
		
	}
	
	/**
	 * unregistyer from the GSM network (go off line)
	 */
	public function unregisterFromNet() {
		Billrun_Factory::log("Unregistering to network");
		$this->doCmd($this->getATcmd('register',array(2)), true);
		$this->state->setState(Gsmodem_StateMapping::IDLE_STATE);
	}	
	
	/**
	 * Wait for the phone to ring (recieve incoming call).
	 * @param type $waitTime (optional) the maximum amount of time to wait before returning.
	 * @return  boolean|string FALSE if the waiting timed out TRUE if the phone is ringing.
	 */
	public function waitForCall($waitTime = PHP_INT_MAX) {		
		$startTime = time();
		while($waitTime > time() - $startTime ) {
			 $lastResult = $this->getResult(static::RESPONSIVE_RESULTS_TIMEOUT*2);
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
		return $this->doCmd($this->getATcmd('answer'), true, true, true, self::COMMAND_RESPONSE_TIME);
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
	/**
	 * Set the modem number.
	 * @param type $number
	 */
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
		//$this->doCmd($this->getATcmd('reset',array()), false, false);		
		//sleep(2);
		//$this->doCmd($this->getATcmd('echo_mode',array(0)), true, false);
		//$this->doCmd('AT+CRESET; \r', true, false,false,  static::COMMAND_RESPONSE_TIME);
		//$this->doCmd("AT+CFUN=0 ;\r", true,true,false,  static::COMMAND_RESPONSE_TIME);
		//$this->doCmd("AT+CFUN=1 ;\r", true,true,false,  static::COMMAND_RESPONSE_TIME);
		//$this->doCmd($this->getATcmd('register_reporting',array(2)), true ,true,false, static::COMMAND_RESPONSE_TIME);
		//$this->doCmd($this->getATcmd('incoming_call_id',array(1)), true ,true,false, static::COMMAND_RESPONSE_TIME);
		$this->doCmd($this->state->getCmdMapping()['init_commands']);
		$this->state->setState(Gsmodem_StateMapping::IDLE_STATE);
		$this->resetModem();
	}
	
	
	/**
	 * Initialize the modem settings.
	 */
	public function resetModem() {
		$ret = $this->doCmd($this->state->getCmdMapping()['reset_commands']);
		//$ret &= $this->doCmd("AT+CVHU=0 ;\r", true,true,false,  static::COMMAND_RESPONSE_TIME) != FALSE;
		//$ret &= $this->doCmd("AT+CVHUP ;\r", true,true,false,  static::COMMAND_RESPONSE_TIME) != FALSE;
		//$ret &= $this->hangUp() != FALSE;
		$ret &= $this->registerToNet() != FALSE;
		return $ret;
	}
	
	/**
	 * Get the current modem state.
	 * @return \ModemState the  modem state object
	 */
	public function getState() {
		return $this->state->getState();
	}

	//----------------------------- PROTECTED ----------------------------------
	
	/**
	 * Get the command string to pass to the modem
	 * @param type $command the  command we  want to issue.
	 * @param type $params the parameters  we should passto the command (phone number,baud value, etc..)
	 * @return string the AT string to pass to the modem inorder to execute the command.
	 */
	protected function getATcmd($command,$params =array()) {		
		$cmdStr = 'AT' . $this->state->getCmdMapping()[$command];
		foreach ($params as $key => $value) {
			$cmdStr = preg_replace('/%'.$key.'%/', $value, $cmdStr);
		}
		return  $cmdStr . ";\r";
	}
	
	/**
	 * Get result/message from the modem
	 * @param type $waitTime thwe amount of time to wait for the result.
	 * @return string|boolean the result/message string we got  from the modem or false if the waiting timed out.
	 */
	protected function getResult($waitTime = PHP_INT_MAX, $translate = true, $waitForStateChange = false) {
		$res =  FALSE;
		$callResult = "";	
		$beginningState = $this->getState();
		stream_set_blocking($this->deviceFD,FALSE);
		$startTime = microtime(true);
		usleep(100);
		while (( $newData = fread($this->deviceFD,4096)) || $waitTime > microtime(true) - $startTime ) {	
			$callResult .= $newData ;
			//Billrun_Factory::log()->log(trim($callResult),  Zend_Log::DEBUG);
			if( preg_match("/\n/",$callResult) ) {
				foreach (split("\n",$callResult) as $value) {
					$this->state->gotResult($value);
				}
				if( $translate ) {
					foreach (split("\n",$callResult) as $value) {
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
			usleep(100);
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
	protected function doCmd($cmd,$getResult = true, $translate = true, $stateChange = false,$waitTime = PHP_INT_MAX) {
		$this->clearBuffer();
		
		if(is_array($cmd)) {
			$res = TRUE;
			foreach($cmd as $command => $getResult) {
				//Billrun_Factory::log("$command");
				$res &= $this->doCmd($command, $getResult, $getResult, false,  static::COMMAND_RESPONSE_TIME) != FALSE || !$getResult;
				if(!$getResult) {sleep(5);}
			}
			return $res;
		} else {

			$res = fwrite($this->deviceFD, $cmd) > 0 && !$getResult;
			fflush($this->deviceFD);	
			$this->state->issuedCommand($cmd);

			return $getResult ?  trim($this->getResult($waitTime, $translate, $stateChange)) :  $res > 0  ;
		}
	}
	
	/**
	 * Clear the modem buffer.
	 */
	protected function clearBuffer() {
		stream_set_blocking($this->deviceFD,FALSE);
		fread($this->deviceFD,4096);
		stream_set_blocking($this->deviceFD,TRUE);		

	}
	
	/**
	 * Retrive an array containing the values that ws returned in the result.
	 * @param type $resultKey
	 * @param type $result
	 * @return type
	 */
	protected function getValueFromResult($resultKey,$result) {
		$matches = array();
		$values = array();
		//Billrun_Factory::log()->log($result,  Zend_Log::DEBUG);
		foreach (split("\n",$result) as $value) {
			if(($match = (preg_match("/^\s*\+{0,1}$resultKey:\s*(.+)$/", $value, $matches ) > 0  ? split(",", $matches[1]) : false ) )) {
				$values[] = $match;
			}
		}
		//Billrun_Factory::log()->log(print_r($values,1),  Zend_Log::DEBUG);
		return !empty($values) ? $values : false;
	}
	
}
