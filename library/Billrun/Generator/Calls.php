<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract generator ilds class
 * require to generate xml for each account
 * require to generate csv contain how much to credit each account
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
	protected $callingDevice = null;

	/**
	 * the answering device.
	 * @var Gsmodem_Gsmodem 
	 */
	protected $answeringDevice = null;

	public function __construct($options) {
		parent::__construct($options);
		if(isset($options['path_to_calling_device'])) {
			$this->callingDevice = new Gsmodem_Gsmodem($options['path_to_calling_device']);
		}
		if(isset($options['path_to_answering_device'])) {
			$this->answeringDevice = new Gsmodem_Gsmodem($options['path_to_answering_device']);
		}		
	}
	
	/**
	 * Generate the calls as defined in the configuration.
	 */
	public function generate() {	
		$callsMade = array();			
		if($this->callingDevice && $this->callingDevice->isValid()) {
			//make the calls and remember their results
			for($i=0; $i < $this->options['times']; $i++) {
				$call = $this->getEmptyCall($this->options['direction'] ? 'calling' : 'answering');
				if($this->options['direction'] == 'calling') {
					$this->makeACall($call);
				} else {
					$this->waitForCall($call);
				}

				if($call['calling_result'] == Gsmodem_Gsmodem::CONNECTED ) {
					$this->HandleCall($call);
				}
				$call['execution_end_time'] = date("YmdTHis");
				$callsMade[] = $call;
				
				sleep($this->options['interval']);
			}	
		}
		Billrun_Factory::log()->log(print_r($callsMade,1),  Zend_Log::DEBUG);
		$this->save($callsMade);
	}
	/**
	 * dummy function of Generator
	 */	
	public function load() {
		
	}
	
	/**
	 * make a call as defiend  by the configuration.
	 * @param type $callRecord the  call record to save to the DB.
	 * @return mixed the call record with the data regarding making the call.
	 */
	protected function makeACall(&$callRecord) {
		$callRecord['calling_result'] = $this->callingDevice->call($this->options['number_to_call']);
		$callRecord['called_number'] = $this->options['number_to_call'];

		return $callRecord['calling_result'];
	}
	
	/**
	 *  wait for a call.
	 * @param mixed $callRecord  the call record to save to the DB.
	 * @return mixed the call record with the data regarding the incoming call.
	 */
	protected function waitForCall(&$callRecord) {
		$this->answeringDevice->waitForCall();
		if(isset($this->options['should_answer']) && $this->options['should_answer']) {
			$callRecord['calling_result'] = $this->answeringDevice->answer();
		} elseif(isset($this->options['ignore_call']) && $this->options['ignore_call']) {
			 $this->answeringDevice->waitForRingToEnd();
			 $callRecord['calling_result'] = 'ignored';
		} else {
			$callRecord['calling_result'] =  $this->answeringDevice->hangUp() ;
		}	
		return $callRecord['calling_result']; 
	}
	
	/**
	 * handle an active call.
	 * @param type $callRecord the call record to save to the DB.
	 */
	protected  function HandleCall(&$callRecord) {
		$callRecord['call_start_time'] = date("YmdTHis");
		$callRecord['end_result'] =  $this->callingDevice->waitForCallToEnd($this->options['call_wait_time']);
		if($callRecord['end_result'] == Gsmodem_Gsmodem::NO_RESPONSE) {
			$this->callingDevice->hangUp();
			$callRecord['end_result'] = 'hang_up';						
		}
		$callRecord['call_end_time'] = date("YmdTHis");
		$callRecord['duration'] = strtotime($callRecord['call_end_time'] ) - strtotime($callRecord['call_start_time']);		
	}

	/**
	 * Get  an empty  call record to be save to the DB.
	 * @return Array representing the call record with initailized values. 
	 */
	protected function getEmptyCall($direction) {
		return array(	'execution_start_time' => date("YmdTHis"),
				'calling_result' => 'no_call',
				'call_start_time' => null,
				'end_result' => 'no_call',
				'call_end_time' => null,
				'duration' => 0,
				'execution_end_time' => null,
				'direction' => $direction,
				 );
	}
	
	/**
	 * save  calls made/received to DB.
	 * @param Array $calls containing the call recrods of the calls  that where made/received
	 * @return boolean
	 */
	protected function save($calls) {
		
		$lines = Billrun_Factory::db()->linesCollection();
		
		foreach ($calls as $row) {
			$row['stamp'] = md5(serialize($row));
			$row['source'] = 'generator';
			$row['unified_record_time'] = new MongoDate(strtotime($row['call_start_time']));
			$row['type'] = static::$type;
			if(!($lines->query(array('stamp'=> $row['stamp'] ) )->cursor()->hasNext() ) )  {				
				$entity = new Mongodloid_Entity($row);
				$entity->save($lines, true);
			} else {
				Billrun_Factory::log()->log("Calls Generator save failed on stamp : {$row['stamp']}", Zend_Log::NOTICE);
				continue;
			}
		}

		return true;
	}
	
}

