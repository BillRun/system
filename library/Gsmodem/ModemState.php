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
class Gsmodem_ModemState {
	
	/**
	 * TODO
	 * @var type 
	 */
	protected $state;
	
	protected $mapping;
	
	public function __construct($startingState = Gsmodem_StateMapping::IDLE_STATE, $mapping = false) {
		$this->mapping = $mapping ? $mapping : new Gsmodem_StateMapping();
		$this->state = $startingState;
	}
	
	/**
	 * 
	 * @param type $result
	 * @return type
	 */
	public function gotResult($result) {
		$this->state = isset($this->mapping->resultToStateMapping[$this->state][$result])	? 
								$this->mapping->resultToStateMapping[$this->state][$result]	: 
								$this->state;	
		Billrun_Factory::log()->log("Switched to state : {$this->state}",Zend_Log::DEBUG);
		return $result;
	}
	
	/**
	 * 
	 * @param type $cmd
	 * @return type
	 */
	public function issuedCommabd($cmd) {
		$stateMap = $this->mapping->commandToStateMapping[$this->state];
		foreach($stateMap as $key => $val) {

			if(preg_match("/".$key."/i",trim($cmd))) {
				$this->state = $val;
			}
		}
		Billrun_Factory::log()->log("Switched to state : {$this->state}",Zend_Log::DEBUG);
		return $cmd;
	}
	
	
	public function getState() {
		return $this->state;
	}
	
	//=================== PROTECTED =================
		
}
