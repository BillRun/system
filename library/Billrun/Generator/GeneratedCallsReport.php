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
class Billrun_Generator_GeneratedCallsReport extends Billrun_Generator {
	protected $subscriber = "";
	protected $callingNumber = "";
	protected $from;
	protected $to;
	
	protected $lines = null;		
	protected $calls = null;

	public function __construct($options) {
		parent::__construct($options);
		if($options['subscriber_id']) {
			$this->subscriber = $options['subscriber_id'];
		}
		if($options['number']) {
			$this->callingNumber = $options['number'];
		}
		$this->from = isset($options['from_date']) ? strtotime($options['from_date']) : time()-(48*3600);
	
		$this->to = isset($options['to_date']) ? strtotime($options['to_date']) : time();
	}
	
	public function generate() {
		$subscriberLines = array();
		$callResults = array();	
		
		foreach($this->calls as $row) {
			$rowData = $row->getRawData();
			$callResults[$rowData['caller_end_result']][] = $rowData;
			$subscriberLines[] = $rowData;
		}
		
		//print_r($subscriberLines);
		$report['cdr_calls_comparison'] = $subscriberLines;
		$report['summary'] = $this->printSummaryReport($subscriberLines, $callResults);
		$report['from']	 = $this->from;
		$report['to']	 = $this->to;
		
		return array(date("YmdHi").".xml" => $report);
	}
	
	/**
	 * 
	 * @param type $subscriberLines
	 * @param type $callResults
	 * @return type
	 */
	protected function printSummaryReport($subscriberLines,$callResults) {
		$summary = array();
		$missed =0;
		$durationDiff = 0;
		foreach($subscriberLines as $line) {
			if(isset($line['callee_duration']) != isset($line['billing_duration']) ) {
				$missed++;
			} else if(isset($line['callee_duration']) && $line['callee_duration'] != $line['billing_duration']){
				$durationDiff++;
			}
		}
		$summary['missed'] = $missed;
		$summary['duration_diff'] = $durationDiff;
		Billrun_Factory::log()->log("From the CDR records : Had $missed call that weren't found in the calling records and $durationDiff with  missmatching duration", Zend_Log::DEBUG);
		foreach ($callResults as $key => $value) {
			$summary['calls_types'][$key] = count($value);
			Billrun_Factory::log()->log("Had ". count($value). " calls that were : $key", Zend_Log::DEBUG);
		}
		return $summary;
	}

	/**
	 * 
	 */
	public function load() {
	
		$this->mergeBillingLines($this->subscriber);
		//load calls made
		$callsQuery =array(	'type' => 'generated_call',
							'from' =>  array('$regex' => (string) $this->callingNumber ),
							'$or' => array( 
								array( 'unified_record_time' => array( 													
													'$lte'=> new MongoDate($this->to) ,
													'$gt' => new MongoDate($this->from) )
								)			
							)
					);
		$this->calls =  Billrun_Factory::db()->linesCollection()
							->query($callsQuery)->cursor();		

		
	}
	
	/**
	 * 
	 * @param type $sub
	 */
	protected function mergeBillingLines($sub) {
		$neededFields = array();
		$billingLines = $this->retriveSubscriberBillingLines($sub);
		Billrun_Factory::log()->log("Sub lines : " . print_r($billingLines,1), Zend_Log::DEBUG);
		foreach ($billingLines as $bLine) {
			$data = array();
			foreach ($neededFields as $key) {
				$data['billing_'.$key] = $bLine[$key];
			}
			
			Billrun_Factory::log()->log(print_r( Billrun_Factory::db()->linesCollection()->upate(array('type'=>'generated_call',
															 'to' => array('$regex' => (string) $bLine['called_number'] ),
															 'unified_record_time' => array('$lte' => $bLines['unified_record_time']->sec+3,
																							'$gte' => $bLines['unified_record_time']->sec-3)
															 ),array('$set'=> $data)) ,1) );
		}
	}
	
	/**
	 * 
	 * @param type $sub
	 * @return type
	 */
	protected function retriveSubscriberBillingLines($sub) {
		//TODO use API
		$query =array(	'type' => 'nsn',						
						'subscriber_id' => (string) $this->subscriber, 
						'unified_record_time' => array(
													 '$lte'=> new MongoDate($this->to) ,
													'$gt' => new MongoDate($this->from) ) 
			);
		if($this->subscriber) {
			$this->lines =  Billrun_Factory::db()->linesCollection()
								->query($query);		
		}
		$retData = array();
		foreach ($this->lines as $value) {
			$retData[] = $value->getRawData();
		}
		return $retData;
	}
}
