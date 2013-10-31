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
	
	const ALLOWED_TIME_DIVEATION = 5;
	
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
	
		$report['details'] = $this->printSDetailedReport($this->calls,$this->billingCalls['unmatched_lines']);
		$report['summary'] = $this->printSummaryReport($this->calls,$this->billingCalls['unmatched_lines']);
		$report['from']	 = date("Y-m-d H:i:s", $this->from);
		$report['to']	 = date("Y-m-d H:i:s", $this->to);	
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
			$record['time_offset'] = Billrun_Util::getFieldVal($line['callee_duration'],0) - Billrun_Util::getFieldVal($line['billing_usagev'],0);
			$record['charge_offest'] = Billrun_Util::getFieldVal($line['callee_cost'],0) - Billrun_Util::getFieldVal($line['billing_aprice'],0);
			$record['rate_offest'] = Billrun_Util::getFieldVal($line['rate'],0) - Billrun_Util::getFieldVal($line['billling_arate'],0);
			$record['start_time_offest'] = Billrun_Util::getFieldVal($line['callee_call_start_time']->sec,0) - strtotime( Billrun_Util::getFieldVal($line['billing_charging_start_time'],'') );
			$record['end_time_offest'] = Billrun_Util::getFieldVal($line['callee_call_end_time']->sec,0) - strtotime(Billrun_Util::getFieldVal($line['billing_charging_end_time'],''));
			$record['call_recoding_diff'] =	isset($line['billing_urt'])  ? 0 : 1 ;
			$record['called_number_diff'] = Billrun_Util::getFieldVal($line['to'],'') != Billrun_Util::getFieldVal($line['billing_called_number'],'') ? 1 : 0;
			$record['correctness'] = ( // Check that the  call is corrent
										abs($record['start_time_offest']) <= 5 && abs($record['end_time_offest']) <= 5 &&
										$record['call_recoding_diff']  == 0 && $record['called_number_diff'] == 0 &&
										abs($record['charge_offest']) <= 0.3 && abs($record['time_offset']) <= 1 
									) ? 0 : 1;
			
			return $record;
	}
	
	protected function filterArray($filter,$arr) {
		$ret = array();
		foreach ($filter as $key => $value) {
				$val = Billrun_Util::getFieldVal($arr[$key],'');			
				$ret[$value] =  $val instanceof MongoDate ? date("Y-m-d H:i:s",$val->sec): $val;
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
		$summary=array(
					'generator' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0),
					'billing' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0),
					'offset' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0),
					'offset_pecentage' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0),
					'generator_standard_deviation' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0),
					'billing_standard_deviation' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0),
			);
		$allLines = array_merge($unmachedLines,$subscriberLines);
		foreach ( $allLines as $key => $value) {
			if(isset($value['action_type'])) {
				$summary['generator']['duration'] += $value['callee_duration'];
				$summary['generator']['price'] += Billrun_Util::getFieldVal($value['callee_price'],0);
				$summary['generator'][$value['action_type']] += 1;			
				$summary['generator']['rate'] += floatval(Billrun_Util::getFieldVal($value['rate'],0));	
			}
		
			if(isset($value['billing_usagev'])) {
				$summary['billing']['duration'] +=  Billrun_Util::getFieldVal($value['billing_usagev'],0) ;
				$summary['billing']['price'] +=  Billrun_Util::getFieldVal($value['billing_aprice'],0);			
				$summary['billing'][Billrun_Util::getFieldVal($value['action_type'],'regular')] +=  1;			
				$summary['billing']['rate'] +=  0;			
			}
		}
		$summary['offset']['duration'] =  $summary['generator']['duration'] - $summary['billing']['duration'];
		$summary['offset']['price'] =  $summary['generator']['price'] - $summary['billing']['price'];						
		$summary['offset']['rate'] =  $summary['generator']['rate'] - $summary['billing']['rate'];
		foreach (array('busy','regular', 'voice_mail' , 'no_answer') as  $value) {
			$summary['offset'][$value] =  $summary['generator'][$value] - $summary['billing'][$value];
		}
		
		$summary['offset_pecentage']['duration'] = (float) @( 100 * $summary['offset']['duration'] / $summary['generator']['duration'] );
		$summary['offset_pecentage']['price'] = (float) @( 100 * $summary['offset']['price'] / $summary['generator']['price'] );						
		$summary['offset_pecentage']['rate'] = (float) @( 100 * $summary['offset']['rate'] / $summary['generator']['rate'] );
		foreach (array('busy','regular', 'voice_mail' , 'no_answer') as  $value) {
			$summary['offset_pecentage'][$value] = (float)@( 100 * $summary['offset'][$value] / $summary['generator'][$value] );
		}
		//TODO calculate standard  deviation
		$summary['generator_standard_deviation'] = $this->calcStandardDev($allLines, array('callee_duration' => 'duration','callee_price' => 'price','rate' => 'rate'));
		$summary['billing_standard_deviation'] =$this->calcStandardDev($allLines, array('billing_usagev'=> 'duration','billing_aprice' => 'price','billing_arate' => 'rate'));
		return $summary;
	}

	
	protected function calcStandardDev($array,$fields) {
		$avgs = array();
		$diveations = array();
		$count = 0;
		foreach ( $array as $value) {
			foreach ($fields as $field => $toField) {				
				$avgs[$field] = Billrun_Util::getFieldVal($avgs[$field],0) + (float)(Billrun_Util::getFieldVal($value[$field],0));
			}			
			$count++;
		}
		
		foreach ( $avgs as &$val ) {
			$val = (float) ($val / $count);
		}
		
		foreach ( $array as $value) {		
			foreach ($fields as $field => $toField) {
				$diveations[$toField] = Billrun_Util::getFieldVal($diveations[$toField],0) + pow( floatval(Billrun_Util::getFieldVal($value[$field],0)) - $avgs[$field],2);
			}			
		}
		foreach ( $diveations as  &$val1 ) {
			$val1 = sqrt($val1 / $count);
		}
		
		return $diveations;
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
		$this->calls = array();
		foreach (Billrun_Factory::db()->linesCollection()->query($callsQuery) as  $value) {
			$this->calls[] = $value->getRawData();
		}		
	}
	
	/**
	 * 
	 * @param type $sub
	 */
	protected function mergeBillingLines($sub) {
		$neededFields = array('called_number','calling_number','usagev','aprice','charging_start_time','charging_end_time','arate','urt','stamp');
		$billingLines = $this->retriveSubscriberBillingLines($sub);
		
		$retBLines = array('raw_lines' => $billingLines, 'unmatched_lines' => array());
		//Billrun_Factory::log()->log("Sub lines : " . print_r($billingLines,1), Zend_Log::DEBUG);
		foreach ($billingLines as $bLine) {
			if($bLine['type'] != "nsn") { continue; }
			
			$data = array();
			foreach ($neededFields as $key) {
				if(isset($bLine[$key])) {
					$data['billing_'.$key] = $bLine[$key];
				}
			}			
			$updateResults =  Billrun_Factory::db()->linesCollection()->update(array('type'=>'generated_call',
																'from' => array('$regex' => preg_replace("/^972/","",$bLine['calling_number']) ),
																'to' => array('$regex' => preg_replace("/^972/","",$bLine['called_number']) ),
																'urt' => array('$lte' => new MongoDate($bLine['urt']->sec + self::ALLOWED_TIME_DIVEATION),
																			   '$gte' => new MongoDate($bLine['urt']->sec - self::ALLOWED_TIME_DIVEATION))
																),array('$set'=> $data),array('w'=>1));
			
			if (!($updateResults['ok'] && $updateResults['updatedExisting'])) {
				$retBLines['unmatched_lines'][] = $data;
			} else {
				Billrun_Factory::log()->log("line : " . print_r($bLine,1), Zend_Log::DEBUG);
				Billrun_Factory::log()->log("line : " . print_r($updateResults,1), Zend_Log::DEBUG);
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
			'calling_number' => (string) $this->callingNumber,
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
			$fd = fopen($outputDir. DIRECTORY_SEPARATOR.$fname,"w+");
			fwrite($fd, json_encode($report,JSON_PRETTY_PRINT));
			fclose($fd);	
			
			}				
	}
	
	
}
