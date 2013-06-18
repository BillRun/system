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
	
	static protected $atCmdMap = array(
							'call' => 'ATD%0%',
							'answer' => 'ATA',
							'hangup' => 'ATH',	
							'reset' => 'ATZ',
						);

	static protected $resultsMap = array(
						'NO ANSWER' => 'remote didnt answer',
						'BUSY' => 'remote is busy',
						'NO CARRIER' => 'remote didn`t answer',	
					);
	
	protected $calls = array();

	/**
	 * TODO
	 * @var type 
	 */
	protected $callingPhoneFD = null;

	/**
	 * TODO
	 * @var type 
	 */
	protected $answeringPhoneFD = null;

	public function __construct($options) {
		parent::__construct($options);
		if(isset($options['path_to_calling_device'])) {
			$this->callingPhoneFD = fopen($options['path_to_calling_device'], 'r+');
		}
		if(isset($options['path_to_answering_device'])) {
			$this->answeringPhoneFD = fopen($options['path_to_answering_device'], 'r+');
		}		
		Billrun_Factory::log()->log(print_r($options,1),  Zend_Log::DEBUG);
	}
	
	public function generate() {		
		if($this->callingPhoneFD) {
			//make the calls and remember their results
			for($i=0; $i < $this->options['times']; $i++) {
				$call = array('start_time' => date("YmdTHis"));							
				$call['result'] = $this->call($this->callingPhoneFD, $this->options['number_to_call']);
				$call['end_time'] = date("YmdTHis");
				$call['duration'] = strtotime($call['end_time'] ) - strtotime($call['start_time']);
				Billrun_Factory::log()->log(print_r($call,1),  Zend_Log::DEBUG);
			}	
			sleep($this->options['interval']);
		}
		
		//wait predetermind time for the lines to be processed..
		
		//compare the results from the CDR  with the results we actually got.
	}
		

	public function load() {
		
	}
	
	protected function call($dev,$number) {
	
		fwrite($dev, $this->getATcmd('call', array($number)));
		fflush($dev);
		while ($callResult = fgets($dev)) {
			if(preg_match('/\w+/', $callResult)) {				
					return $callResult;
			}
		}
		
		return FALSE;
				
	}
	
	protected function getATcmd($command,$params) {
		$cmdStr = self::$atCmdMap[$command];
		foreach ($params as $key => $value) {
			$cmdStr = preg_replace('/%'.$key.'%/', $value, $cmdStr);
		}
		return $cmdStr . ";\n";
	}
}
