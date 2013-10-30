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
	protected $billingCalls = null;
	

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
			$subscriberLines[] = $rowData; //TODO filter the filed to only return relevent fields
		}
		

		$report['summary'] = $this->printSDetailedReport($this->calls,$this->billingCalls['unmatched_lines']);
		$report['from']	 = $this->from;
		$report['to']	 = $this->to;	
		$reports =  array("call_matching_report" => $report);
		if(isset($this->options['out']) && $this->options['out']) {
			$this->generateFiles($reports, $this->options['out']);
		}
		
		return $reports;
	}
	
	/**
	 * 
	 * @param type $subscriberLines
	 * @param type $callResults
	 * @return type
	 */
	protected function printSDetailedReport($mergedLines,$unmatchedBillingLines) {
		$report = array();	
		
		foreach($mergedLines as $line) {			
			$report[] = $this->detailedReportLine($line);
		}
		foreach($unmatchedBillingLines as $line) {			
			$report[] = $this->detailedReportLine($line);
		}
		return $report;
	}
	
	protected function detailedReportLine($line) {
			$billingRecordFilter = array(
				'billing_stamp' => 'billing_stamp',			
				'billing_calling_number' => 'billing_calling_number',
				'billing_called_number' => 'billing_called_number',
				'billing_usagev' => 'billing_duration',
				'billing_charging_start_time' => 'billing_charging_start_time',
				'billing_charging_end_time' => 'billing_charging_end_time',
				'billing_arate' => 'billing_rate',
				'billing_aprice' => 'billing_price',
			);			
			$callingRecordFilter = array(
				'call_id' => 'generator_id',
				'caller_execution_start_time' => 'generator_calling_time',
				'callee_duration' => 'generator_duration',
				'callee_call_start_time' => 'generator_call_start_time',
				'callee_call_end_time' => 'generator_call_end_time',
				'from' => 'generator_calling_number',
				'to' => 'generator_dialed_number',
				'to' => 'generator_called_number',
				'action_type' => 'generator_call_type',
				'rate' => 'generator_rate',
				'callee_estimated_price' => 'generator_estimated_price',
			);			
			$record = array_merge( $this->filterArray($callingRecordFilter, $line),  $this->filterArray($billingRecordFilter, $line) );			
			print_r($record);
			$record['time_offset'] = Billrun_Util::getFieldVal($line['callee_duration'],0) - Billrun_Util::getFieldVal($line['usagev'],0);
			$record['charge_offest'] = Billrun_Util::getFieldVal($line['callee_cost'],0) - Billrun_Util::getFieldVal($line['aprice'],0);
			$record['rate_offest'] = Billrun_Util::getFieldVal($line['rate'],0) - Billrun_Util::getFieldVal($line['rate'],0);
			$record['start_time_offest'] = Billrun_Util::getFieldVal($line['callee_call_start_time']->sec,0) - strtotime( Billrun_Util::getFieldVal($line['billing_charging_start_time'],'') );
			$record['end_time_offest'] = Billrun_Util::getFieldVal($line['callee_call_end_time'],0) - strtotime(Billrun_Util::getFieldVal($line['billing_charging_end_time'],''));
			$record['call_recoding_diff'] =	isset($line['billing_urt'])  ? 0 : 1 ;
			$record['called_number_diff'] = Billrun_Util::getFieldVal($line['to'],'') != Billrun_Util::getFieldVal($line['billing_called_number'],'') ? 1 : 0;
			$record['correctness'] = (
										$record['start_time_offest']  == 0 && $record['end_time_offest'] ==	0 &&
										$record['call_recoding_diff']  == 0 && $record['called_number_diff'] == 0 &&
										$record['charge_offest'] == 0 &&$record['time_offset'] == 0
									) ? 0 : 1;
			
			return $record;
	}
	
	protected function filterArray($filter,$arr) {
		$ret = array();
		foreach ($filter as $key => $value) {
			if(isset($arr[$key])) {
				$ret[$value] = $arr[$key];
			}
		}
		return $ret;
	}
	
	/**
	 * 
	 * @param type $subscriberLines
	 * @param type $callResults
	 * @return type
	 */
	protected function printSummaryReport($subscriberLines,$unmachedLines) {
		
		Billrun_Factory::log()->log("From the CDR records : Had $missed call that weren't found in the calling records and $durationDiff with missmatching duration", Zend_Log::DEBUG);
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
	
		$this->billingCalls = $this->mergeBillingLines($this->subscriber);
		//load calls made
		$callsQuery =array(	'type' => 'generated_call',
							'from' =>  array('$regex' => (string) $this->callingNumber ),
							 'urt' => array( 													
													'$lte'=> new MongoDate($this->to) ,
													'$gt' => new MongoDate($this->from) 
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
		$neededFields = array('called_number','subscriber_id','account_id','duration','price_customer');
		$billingLines = $this->retriveSubscriberBillingLines($sub);
		
		$retBLines = array('raw_lines' => $billingLines, 'unmached_lines' => array());
		//Billrun_Factory::log()->log("Sub lines : " . print_r($billingLines,1), Zend_Log::DEBUG);
		foreach ($billingLines as $bLine) {
			if($bLine['type'] != "nsn") { continue; }
			Billrun_Factory::log()->log("line : " . print_r($bLine,1), Zend_Log::DEBUG);
			$data = array();
			foreach ($neededFields as $key) {
				if(isset($bLine[$key])) {
					$data['billing_'.$key] = $bLine[$key];
				}
			}			
			$updateResults =  Billrun_Factory::db()->linesCollection()->update(array('type'=>'generated_call',
																'from' => array('$regex' => (string)"/". $bLine['calling_number'] ."/"),
																'to' => array('$regex' => (string)"/". $bLine['called_number'] ."/" ),
																'urt' => array('$lte' => new MongoDate($bLine['urt']->sec+5),
																			   '$gte' => new MongoDate($bLine['urt']->sec-5))
																),array('$set'=> $data));
			
			if (!($updateResults['ok'] && $updateResults['updatedExisting'])) {
				$retBLines['unmatched_lines'][] = $data;
			}
		}
		return $retBLines;
	}
	
	/**
	 * 
	 * @param type $sub
	 * @return type
	 */
	protected function retriveSubscriberBillingLines($sub) {
		//TODO use API
		$options = array(
			'type' => 'SubscriberUsage',
			'subscriber_id' => (string) $this->subscriber,
			'from' => date("Y-m-d H:i:s",$this->from),
			'to' => date("Y-m-d H:i:s",$this->to),
		);
		$generator = Billrun_Generator::getInstance($options);
		$generator->load();
		$results = $generator->generate();
		
		return $results['lines'];
	}
	
	/**
	 * 
	 * @param type $resultFiles
	 * @param type $generator
	 * @param type $outputDir
	 */
	protected function generateFiles($resultFiles,$outputDir = GenerateAction::GENERATOR_OUTPUT_DIR) {
		foreach ($resultFiles as $name => $report) {
			$fname = date('Ymd'). "_" . $name .".json";
			Billrun_Factory::log("Generating file $fname");
			$fd = fopen($outputDir. DIRECTORY_SEPARATOR.$fname,"w+");//@TODO change the  output  dir to be configurable.
			
			fwrite($fd, json_encode($report));
			fclose($fd);	
			}				
	}
	
	
}
