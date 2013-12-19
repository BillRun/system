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
class Billrun_Generator_Report_GeneratedCalls extends Billrun_Generator_Report {
	
	const ALLOWED_TIME_DIVEATION = 10;
	
	protected $subscribers = array();
	protected $callingNumbers = array();
	protected $from;
	protected $to;
	
	protected $lines = null;		
	protected $calls = null;
	protected $billingCalls = null;
	protected $billingTimeOffset = 0;
	protected $allowedTimeDiveation = 10;
	

	public function __construct($options) {
		parent::__construct($options);
		if(isset($options['subscribers'])) {
			$this->subscribers = split(",",$options['subscribers']);
		}
		if(isset($options['numbers'])) {
			$this->callingNumbers = split(",",$options['numbers']);
		}
		if(isset($options['billing_time_offset'])) {
			$this->billingTimeOffset = $options['billing_time_offset'];
		}
		
		if(isset($options['allowed_time_dievation'])) {
			$this->allowedTimeDiveation = $options['allowed_time_dievation'];
		}
		$this->from = isset($options['from_date']) ? strtotime($options['from_date']) : time()-(48*3600);
	
		$this->to = isset($options['to_date']) ? strtotime($options['to_date']) : time();
	}
	
	public function generate() {
		//get the  compared lines  and order them by date
		$report['details'] = $this->printSDetailedReport($this->calls,$this->billingCalls['unmatched_lines']);		
		$report['summary'] = $this->printSummaryReport($report['details']);
		$report['from']	 = date("Y-m-d H:i:s", $this->from);
		$report['to']	 = date("Y-m-d H:i:s", $this->to);	
		$reports =  array(join("_",$this->subscribers)."_call_matching_report.csv" => $report);
		if(isset($this->options['out']) && $this->options['out']) {
			$this->generateFiles($reports, $this->options['out']);
		}
		
		return $reports;
	}
	
	/**
	 * Create  a detailed reprot  from the  matched  and unmatched lines 
	 * @param type $mergedLines the lines  the  were matched with the  billing system
	 * @param type $unmatchedBillingLines lines  that  were  retrived from the  billing system and  were able to match to calls from the generator.
	 * @return array asorted  array containing all the calls
	 */
	protected function printSDetailedReport($mergedLines,$unmatchedBillingLines) {
		$report = array();	
		
		foreach($mergedLines as $line) {			
			$report[] = $this->detailedReportLine($line);
		}
		foreach($unmatchedBillingLines as $line) {			
			$report[] = $this->detailedReportLine($line);
		}
		usort($report, function($a,$b) { 
			$valA = Billrun_Util::getFieldVal($a['generator_call_start_time'],Billrun_Util::getFieldVal($a['billing_charging_start_time'],PHP_INT_MAX));
			$valB = Billrun_Util::getFieldVal($b['generator_call_start_time'],Billrun_Util::getFieldVal($b['billing_charging_start_time'],PHP_INT_MAX));
			return  strcmp($valA,$valB);						
		});
		return $report;
	}
	
	protected function detailedReportLine($line) {
			$isCallerHanugup = Billrun_Util::getFieldVal($line['caller_end_result'],'') == 'hang_up';
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
				'callee_duration' => 'generator_duration',				
				'callee_call_start_time' => 'generator_call_start_time',
				'callee_call_end_time' => 'generator_call_end_time',
				'caller_end_result' => 'caller_end_status',
				'callee_end_result' => 'called_end_status',				
				'from' => 'generator_calling_number',
				'to' => 'generator_called_number',
				'caller_execution_start_time' => 'generator_calling_time',
				'action_type' => 'generator_call_type',
				'rate' => 'generator_rate',
				'callee_estimated_price' => 'generator_estimated_price',
			);			
			$record = array_merge( $this->filterArray($callingRecordFilter, $line),  $this->filterArray($billingRecordFilter, $line) );			
			$record['start_time_offest'] = Billrun_Util::getFieldVal($line['callee_call_start_time']->sec,0) - strtotime( Billrun_Util::getFieldVal($line['billing_charging_start_time'],'') );
			$record['end_time_offest'] = Billrun_Util::getFieldVal($line['callee_call_end_time']->sec,0) - strtotime(Billrun_Util::getFieldVal($line['billing_charging_end_time'],''));
			
			if($isCallerHanugup) {
				$record['generator_duration'] = Billrun_Util::getFieldVal($line['caller_duration'],Billrun_Util::getFieldVal($line['callee_duration'],0));
				$record['generator_call_start_time'] = date("Y-m-d H:i:s",Billrun_Util::getFieldVal($line['caller_call_start_time']->sec,Billrun_Util::getFieldVal($line['callee_call_start_time']->sec,0)));
				$record['generator_call_end_time'] = date("Y-m-d H:i:s", Billrun_Util::getFieldVal($line['caller_call_end_time']->sec,Billrun_Util::getFieldVal($line['callee_call_end_time']->sec,0)));
				$record['start_time_offest'] = Billrun_Util::getFieldVal($line['caller_call_start_time']->sec,0) - strtotime( Billrun_Util::getFieldVal($line['billing_charging_start_time'],'') );
				$record['end_time_offest'] = Billrun_Util::getFieldVal($line['caller_call_end_time']->sec,0) - strtotime(Billrun_Util::getFieldVal($line['billing_charging_end_time'],''));			
			}
			
			$record['crashed'] = Billrun_Util::getFieldVal($line['stage'],'call_done') != 'call_done' ? 1 : 0;
			$record['time_offset'] = Billrun_Util::getFieldVal($record['generator_duration'],0) - Billrun_Util::getFieldVal($line['billing_usagev'],0);
			$record['charge_offest'] = Billrun_Util::getFieldVal($line['callee_cost'],0) - Billrun_Util::getFieldVal($line['billing_aprice'],0);
			$record['rate_offest'] = Billrun_Util::getFieldVal($line['rate'],0) - Billrun_Util::getFieldVal($line['billling_arate'],0);
			$record['call_recoding_diff'] =	isset($line['billing_urt'])  ? 0 : 1 ;
			$record['called_number_diff'] = Billrun_Util::getFieldVal($line['to'],'') != Billrun_Util::getFieldVal($line['billing_called_number'],'') ? 1 : 0;
			$record['correctness'] = ($record['caller_end_status'] == 'no_call' && $record['called_end_status'] == 'no_call')^ ( // Check that the  call is corrent
										abs($record['start_time_offest']) <= 1.5 && abs($record['end_time_offest']) <= 1.5 &&
										$record['call_recoding_diff']  == 0 && $record['called_number_diff'] == 0 &&
										abs($record['charge_offest']) <= 0.3 && abs($record['time_offset']) <= 1 
									) ? 0 : 1;
			
			return $record;
	}
	
	protected function filterArray($filter,$arr) {
		$ret = array();
		foreach ($filter as $key => $value) {
				$val = Billrun_Util::getFieldVal($arr[$key],'');			
				$ret[$value] = strstr($value,"_time") ? date("Y-m-d H:i:s",$val instanceof MongoDate ? $val->sec : strtotime($val) ) : $val;
		}
		return $ret;
	}
	
	/**
	 * 
	 * @param type $subscriberLines
	 * @param type $callResults
	 * @return type
	 */
	protected function printSummaryReport($allLines) {
		$summary=array(
					'generator' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0,'out_of_bounds' => 0),
					'billing' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0,'out_of_bounds' => 0),
					'offset' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0,'out_of_bounds' => 0),
					'offset_pecentage' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0,'out_of_bounds' => 0),
					'generator_standard_deviation' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0,'out_of_bounds' => 0),
					'billing_standard_deviation' => array('duration'=>0,'price'=> 0, 'busy'=> 0,'regular' => 0, 'voice_mail' => 0 , 'no_answer' => 0,'rate'=> 0,'out_of_bounds' => 0),
			);
		//$allLines = array_merge($unmachedLines,$subscriberLines);
		foreach ( $allLines as $key => $value) {

			if(!Billrun_Util::getFieldVal($this->options['summaries_crashed_calls'],false) && 
				Billrun_Util::getFieldVal($value['stage'],'call_done') != 'call_done') {
				continue;
			}
			
			if(Billrun_Util::getFieldVal($value['generator_call_type'],false) && $value['called_end_status'] != 'no_call') {
				$summary['generator']['duration'] += Billrun_Util::getFieldVal($value['generator_duration'],0);
				$summary['generator']['price'] += 0;//Billrun_Util::getFieldVal($value['generator_estimated_price'],0);
				$summary['generator'][$value['generator_call_type']] += 1;			
				$summary['generator']['rate'] += floatval(Billrun_Util::getFieldVal($value['generator_rate'],0));	
				$summary['generator']['out_of_bounds'] += Billrun_Util::getFieldVal($value['correctness'],0);
			}
		
			if(Billrun_Util::getFieldVal($value['billing_stamp'],false)) {
				$summary['billing']['duration'] +=  Billrun_Util::getFieldVal($value['billing_duration'],0) ;
				$summary['billing']['price'] +=  Billrun_Util::getFieldVal($value['billing_price'],0);			
				$summary['billing'][Billrun_Util::getFieldVal($value['action_type'],'regular')] +=  1;			
				$summary['billing']['rate'] +=  Billrun_Util::getFieldVal($value['billing_rate'],0);
				$summary['billing']['out_of_bounds'] += Billrun_Util::getFieldVal($value['correctness'],0);
			}
		}
		$summary['offset']['duration'] =  $summary['generator']['duration'] - $summary['billing']['duration'];
		$summary['offset']['price'] =  $summary['generator']['price'] - $summary['billing']['price'];						
		$summary['offset']['rate'] =  $summary['generator']['rate'] - $summary['billing']['rate'];
		$summary['offset']['out_of_bounds'] =  $summary['generator']['out_of_bounds'] - $summary['billing']['out_of_bounds'];
		foreach (array('busy','regular', 'voice_mail' , 'no_answer') as  $value) {
			$summary['offset'][$value] =  $summary['generator'][$value] - $summary['billing'][$value];
		}
		
		$summary['offset_pecentage']['duration'] = (float) @( 100 * $summary['offset']['duration'] / $summary['generator']['duration'] );
		$summary['offset_pecentage']['price'] = (float) @( 100 * $summary['offset']['price'] / $summary['generator']['price'] );						
		$summary['offset_pecentage']['rate'] = (float) @( 100 * $summary['offset']['rate'] / $summary['generator']['rate'] );
		$summary['offset_pecentage']['out_of_bounds'] = (float) @( 100 * $summary['offset']['out_of_bounds'] / $summary['generator']['out_of_bounds'] );
		foreach (array('busy','regular', 'voice_mail' , 'no_answer') as  $value) {
			$summary['offset_pecentage'][$value] = (float)@( 100 * $summary['offset'][$value] / $summary['generator'][$value] );
		}
		//TODO calculate standard  deviation
		$summary['generator_standard_deviation'] =  array_merge( $summary['generator_standard_deviation'],$this->calcStandardDev($allLines, array('generator_duration' => 'duration','generator_price' => 'price','generator_rate' => 'rate')) );		
		$summary['billing_standard_deviation'] = array_merge( $summary['billing_standard_deviation'] ,$this->calcStandardDev($allLines, array('billing_duration'=> 'duration','billing_price' => 'price','billing_rate' => 'rate')) );
		
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

		$this->billingCalls= array();
		foreach($this->subscribers as $subscriber) {
			$this->billingCalls = array_merge($this->billingCalls,$this->mergeBillingLines($subscriber));
		}
		
		//load calls made
		$this->calls = array();
		foreach($this->callingNumbers as $number) {
			$callsQuery =array(	'type' => 'generated_call',

								'urt' => array(
											'$gt' => new MongoDate($this->from),
											'$lte'=> new MongoDate($this->to),										
										 ),
								//'$or' => array(
								//	array('callee_call_start_time' => array('$gt'=> new MongoDate(0) )),
								//	array('billing_urt' => array('$gt'=> new MongoDate(0) )),
									//array('caller_end_result' => array('$ne'=> 'no_call' )),
								//),
								'from' =>  array('$regex' => (string) $number ),
						);
		
			$cursor = Billrun_Factory::db()->linesCollection()->query($callsQuery)->cursor()->sort(array('urt'=>1));
			foreach ($cursor as $value) {
				$this->calls[] = $value->getRawData();
			}
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
																'urt' => array('$lte' => new MongoDate($bLine['urt']['sec'] + $this->billingTimeOffset + $this->allowedTimeDiveation),
																			   '$gte' => new MongoDate($bLine['urt']['sec'] + $this->billingTimeOffset - $this->allowedTimeDiveation))
																),array('$set'=> $data),array('w'=>1));
			
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
		
		$url = $this->options['billing_api_url']."/apigenerate/?type=SubscriberUsage&stamp={$this->stamp}&subscriber_id={$sub}&from=".  urlencode(date("Y-m-d H:i:s",$this->from))."&to=".  urlencode(date("Y-m-d H:i:s",$this->to));
		Billrun_Factory::log()->log("Quering billing  with : $url");
		$curlFd = curl_init($url);
		curl_setopt($curlFd, CURLOPT_RETURNTRANSFER, TRUE);
		$results = json_decode(curl_exec($curlFd),JSON_OBJECT_AS_ARRAY);
		foreach ($results as &$value) {
			if($value['type'] != 'nsn' || $value['usaget'] != 'call') {
				unset($value);
			}
			$value['arate'] = $value['arate']['call']['rate'][0]['price'];
		}
		return $results;
	}
	
	/**
	 * @see Billrun_Generator_Report::writeToFile( &$fd, &$report )
	 */
	function writeToFile( &$fd, &$report ) {
		foreach ($report as $key => $section) {			
			fputcsv($fd, array($key, is_array($section) ? '' : $section) );
			if(!empty($section) && is_array($section)) {
				fputcsv($fd, array_merge( ($key != 'details' ? array(""): array()) , array_keys($section[key($section)]) ) );
				foreach ($section as $Skey => $fields) {
					fputcsv($fd, array_merge(($key != 'details' ? array($Skey): array()),$fields));
				}
			}	
		}
		
	}
}
