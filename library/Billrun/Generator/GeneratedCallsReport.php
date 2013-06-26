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
	protected $calledNumber = "";
	protected $from;
	protected $to;
	
	protected $lines = null;		
	protected $calls = null;

	public function __construct($options) {
		parent::__construct($options);
		if($options['subscriber_id']) {
			$this->subscriber = $options['subscriber_id'];
		}
		if($options['called_number']) {
			$this->calledNumber = $options['called_number'];
		}
		$this->from = isset($options['from_date']) ? strtotime($options['from_date']) : time()-(24*3600);
	
		$this->to = isset($options['to_date']) ? strtotime($options['to_date']) : time();
	}
	
	public function generate() {
		$subscriberLines = array();
		$callResults = array();
		
		foreach($this->lines as $row) {
			$rowData = $row->getRawData();
			$subscriberLines[intval($rowData['unified_record_time']->sec/2)]['cdr'] = $rowData;
		}
		
		foreach($this->calls as $row) {
			$rowData = $row->getRawData();
			if($rowData['call_start_time']) {
				$subscriberLines[intval($rowData['unified_record_time']->sec/2)]['generated'] = $rowData;
			}
			$callResults[$rowData['direction']."_".$rowData['calling_result']."_AND_".$rowData['end_result']][] = $rowData;
		}
		
		print_r($subscriberLines);
		$report['cdr_calls_comparison'] = $subscriberLines;
		print_r($callResults);
		$report['generated_breakdown'] = $callResults;
		$report['summary'] = $this->printSummaryReport($subscriberLines, $callResults);
		$report['from']	 = $this->from;
		$report['to']	 = $this->to;
		return array(date("YmdHi").".xml" => $report);
	}
	
	public function getTemplate() {
		return 'generated_calls_report.phtml';
	}
	
	protected function printSummaryReport($subscriberLines,$callResults) {
		$summary = array();
		$missed =0;
		$durationDiff = 0;
		foreach($subscriberLines as $line) {
			if(isset($line['generated']) != isset($line['cdr']) ) {
				$missed++;
			} else if($line['generated']['duration'] != $line['cdr']['duration'] ){
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

	public function load() {
		//load CDRs recieved
		$query =array(	'type' => 'nsn',
						'called_number' => array('$regex' => (string) $this->calledNumber ),
						'subscriber_id' => (string) $this->subscriber, 
						'unified_record_time' => array(
													 '$lte'=> new MongoDate($this->to) ,
													'$gt' => new MongoDate($this->from) ) 
			);
		if($this->subscriber) {
			$this->lines =  Billrun_Factory::db()->linesCollection()
								->query($query);		
		}
		
		//load calls made
		$callsQuery =array(	'type' => 'calls',
							'called_number' =>  array('$regex' => (string) $this->calledNumber ),
							'$or' => array( 
								array( 'unified_record_time' => array( 													
													'$lte'=> new MongoDate($this->to) ,
													'$gt' => new MongoDate($this->from) )
								),
								array('unified_record_time' => array( '$exists' => FALSE )),				
							)
					);
		if($this->subscriber) {
			$this->calls =  Billrun_Factory::db()->linesCollection()
								->query($callsQuery);		
		}
		
	}
}
