<?php

/**
 * @package         Gsmodem
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Modem state logic class
 * This  class is using the StateMapping or any class hinerited for it inordedr to map  the different states.
 * 
 * @package  Gsmodem
 * @since    0.1
 */
class Gsmodem_ModemState {
	
	const CONNECTED = "connected";
	const NO_ANSWER = "no_answer";
	const CALL_DISCONNECTED = "call_disconnected";
	const BUSY = "busy";
	const UNKNOWN = "unknown";
	const NO_RESPONSE = "";
	const RINGING = 'ringing';
	const HANG_UP = 'hang_up';
	const HANGING_UP = 'hanging_up';
	
	
	 protected $atCmdMap = array(
							'call' => 'D%0%',
							'answer' => 'A',
							'hangup' => 'H',	
							'reset' => 'Z',
							'register' => '+COPS=%0%',
							'register_reporting' => '+CREG=%0%',
							'register_status' => '+CREG?',
							'incoming_call_id' => '+CLIP=%0%',		
							'get_error' => '+CEER',
							'get_number' => '+CNUM',
							'echo_mode' => 'E%0%',
							'reset' => 'Z'
						);

	protected $resultsMap = array( 
							'NO ANSWER' => self::NO_ANSWER,
							'BUSY' => self::BUSY,
							'ERROR' => self::UNKNOWN,
							'NO CARRIER' => self::CALL_DISCONNECTED,	
							'OK' => self::CONNECTED,	
							"VOICE CALL: BEGIN" => self::CONNECTED,
							'RING' => self::RINGING,					
					);
	
	/**
	 * Hold the current state fo the modem  as it mapped be the mapping class
	 * @var mixed
	 */
	protected $state;
	
	/**
	 * The state  transition mapping.
	 * @var type 
	 */
	protected $mapping;
	
	public function __construct($startingState = Gsmodem_StateMapping::IDLE_STATE, $mapping = false) {
		$this->mapping = $mapping ? $mapping : new Gsmodem_StateMapping();
		$this->state = $startingState;
	}
	
	/**
	 * Update the state upon results.
	 * @param string $result contains the results that rwas received from the modem.
	 * @return string the reuslt that was recieved from the modem.
	 */
	public function gotResult($result) {
		$this->state = $this->mapping->getStateForResult($this->state,$result);
		//Billrun_Factory::log()->log("Switched to state : {$this->state}",Zend_Log::DEBUG);
		return $result;
	}
	
	/**
	 * Update the state upon a newly issued command.
	 * @param string $cmd the command that was issued to the modem.
	 * @return string the command that was issued to the modem.
	 */
	public function issuedCommand($cmd) {
		$this->state = $this->mapping->getStateForCommand($this->state,$cmd);
		//Billrun_Factory::log()->log("Switched to state : {$this->state}",Zend_Log::DEBUG);
		return $cmd;
	}
	
	/**
	 * Get the current dtate
	 * @return mixed the  current modem state.
	 */
	public function getState() {
		return $this->state;
	}
		
	public function getCmdMapping() {
		return $this ->atCmdMap;
	}
	
	public function getResultMapping() {
		return $this->resultsMap;
	}
}
