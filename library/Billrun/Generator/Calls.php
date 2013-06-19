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
		Billrun_Factory::log()->log(print_r($options,1),  Zend_Log::DEBUG);
	}
	
	public function generate() {		
		if($this->callingDevice->isValid()) {
			$callsMade = array();
			$callsFailed = array();
			//make the calls and remember their results
			for($i=0; $i < $this->options['times']; $i++) {
				$call = array('dialing_start_time' => date("YmdTHis"));
				$call['result'] = $this->callingDevice->call($this->options['number_to_call']);
				$call['called_number'] = $this->options['number_to_call'];
				$call['dialing_end_time'] = date("YmdTHis");
				if($call['result'] == Gsmodem_Gsmodem::CONNECTED ) {
					$call['call_start_time'] = date("YmdTHis");
					$call['end_result'] =  $this->callingDevice->waitForCallToEnd($this->options['call_wait_time']);
					if($call['end_result'] == Gsmodem_Gsmodem::NO_RESPONSE) {
						$this->callingDevice->hangUp();
						$call['end_result'] = 'hang_up';						
					}
					$call['call_end_time'] = date("YmdTHis");
					$call['duration'] = strtotime($call['call_end_time'] ) - strtotime($call['call_start_time']);
				}
				Billrun_Factory::log()->log(print_r($call,1),  Zend_Log::DEBUG);
				if($call['result'] == Gsmodem_Gsmodem::CONNECTED) {
					$callsMade[] = $call;
				} else {
					$callsFailed[] = $call;
				}
			}	
			sleep($this->options['interval']);
		}
		
		//wait predetermind time for the lines to be processed..
		$callsReceived = array();
		$waitUntil= strtotime("+".$this->options['wait_for_results']);
		Billrun_Factory::log()->log("Waiting for  results...",  Zend_Log::DEBUG);
		while(count($callsMade) > count($callsReceived) && $waitUntil > time() ) {
			$this->getCallsCDRs($callsMade);			
			$callsReceived = $this->getCallsCDRs($callsMade);
			sleep(60);
		}
		//compare the results from the CDR  with the results we actually got.
		print_r($callsMade);
		print_r($callsFailed);
		print_r($callsReceived);
	}
		
	public function load() {
		
	}	
	/**
	 * TODO temporary!
	 * @param type $calls
	 */
	protected function getCallsCDRs(&$calls) {
		$lines = Billrun_Factory::db()->getCollection('lines');
		$foundCalls = array();
		foreach($calls as $call) {
			$callCDR  = $lines->query(array('type' => 'nsn' , 
											'called_number' => "/".$call['called_number']."/", 
											'unified_record_time' => array(	'$lte'=> new MongoDate(strtotime($call['call_start_time'])+$this->options['interval'])  ,
																			'$gte'=> new MongoDate(strtotime($call['call_start_time'])-$this->options['interval']) ) ))->cursor()->current();
			if($callCDR->getRawData()) {
				$foundCalls[] = $callCDR;
				
			}
		}
		return $foundCalls;
	}
}
