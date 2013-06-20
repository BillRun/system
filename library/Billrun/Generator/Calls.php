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
	 * TODO
	 * @var type 
	 */
	protected $callingDevice = null;

	/**
	 * TODO
	 * @var type 
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
		
	public function load() {
		
	}
	
	/**
	 * 
	 * @param type $callRecord
	 * @return type
	 */
	protected function makeACall(&$callRecord) {
		$callRecord['calling_result'] = $this->callingDevice->call($this->options['number_to_call']);
		$callRecord['called_number'] = $this->options['number_to_call'];

		return $callRecord['calling_result'];
	}
	
	/**
	 * 
	 * @param type $callRecord
	 * @return type
	 */
	protected function waitForCall(&$callRecord) {
		$this->answeringDevice->waitForCall();
		if(isset($this->options['should_answer']) && $this->options['should_answer']) {
			$callRecord['calling_result'] = $this->answeringDevice->answer();
		} elseif(isset($this->options['ignore_call']) && $this->options['ignore_call']) {
			 $this->answeringDevice->waitForRingToEnd();
			 $callRecord['calling_result'] = 'ignored';
		} else {
			$callRecord['calling_result'] =  $this->answeringDevice->hangUp();
		}	
		return $callRecord['calling_result']; 
	}
	
	/**
	 * 
	 * @param type $callRecord
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
	 * TODO
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
	 * TODO
	 * @param type $calls
	 * @return boolean
	 */
	protected function save($calls) {
		
		$lines = Billrun_Factory::db()->linesCollection();
		
		foreach ($calls as $row) {
			$row['stamp'] = md5(serialize($row));
			$row['source'] = 'generator';
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

