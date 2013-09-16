<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Report
 *
 * @author eran
 */
abstract class Billrun_Generator_Base_WholesaleReport extends Billrun_Generator  {

	const CELLCOM_ROAMING_REGEX="^[R4]CEL";
	
	/**
	 * The report type  shou8ld  be  overriden  by inheriting functions.
	 */
	protected $reportType = 'report';
	
	/**
	 * hold  the providers configuration
	 */
	protected $providers;
			
	/**
	 * hold the  type of connections  available
	 */
	protected $types;
	
	
	public function __construct($options) {
		parent::__construct($options);
		$this->reportType  = isset($options['report_type']) ? $options['report_type'] : $this->reportType;
		$this->reportBasePath = Billrun_Factory::config()->getConfigValue($this->reportType.'.reports.path', './');

		$this->types = Billrun_Factory::config()->getConfigValue( $this->reportType.'.reports.types',
																  array(
																		'I' => 'International',
																		'M' => 'Mobile',
																		'N' => 'National',
																		'4' => 'National' ,
																		'P' => 'National',
																		'Un' => 'Other',
																	));
		
		$this->startDate = isset($options['start_date']) ?  strtotime($options['start_date']) : 
							 (strlen($this->stamp) > 8 ? strtotime($this->stamp) : Billrun_Util::getLastChargeTime(true));
		$this->endDate = isset($options['end_date']) ?  strtotime($options['end_date']) : strtotime(date('Ymt',$this->startDate));
	}
	
	/**
	 * generate the reports
	 * @return array containg the -
	 */
	public function generate() {
		$providerResults = array();
		$timeHorizions['start'] = new MongoDate($this->startDate);
		$timeHorizions['end'] = new MongoDate($this->endDate);
		Billrun_Factory::log()->log("Start Date : ".date("Y-m-d",$this->startDate)." End Date : ".date("Y-m-d",$this->endDate),Zend_Log::DEBUG);
		//foreach($this->providers as $providerName => $val) {
			//Billrun_Factory::log()->log("Aggregating  $providerName NSN usage: ",Zend_Log::DEBUG);
			$providerResults = $this->aggregate( $this->getCDRs( $timeHorizions ));
			
//			if( empty($providerAggregation) ) {
//				continue;
//			}
//			$providerResults[$providerName] = $providerAggregation; 
//		}
		return $providerResults;
	}
	
	/**
	 * 
	 * @param type $initData
	 * @r-eturn type
	 */
	public function load($initData = true) {
		return  false; //$this->getCDRs("", $timeHorizions);
	}
	
	/**
	 *  Retrive the call CDR lines from the DB for a given provider.
	 */
	abstract protected function getCDRs( $timehorizon );
	
	/**
	 * get  Rates for a given line
	 * @param type $line  the line to get the rate for.
	 * @return array containing the ratiing  details.
	 */
	protected function tariffForLine($line) {
		$line->collection(Billrun_Factory::db()->linesCollection());
		//TODO allow for  multiple rates...		
		$rate = '';
		if(isset($line['rates'][0]['rate']['price'])) {	
			$rate =  $line['rates'][0]['rate']['price'];
		}
		if($rate=='') {
			$zone = isset($line['provider_zone']['key']) ? $line['provider_zone']['key'] : ( $line['provider_zone'] == 'incoming' ? 'incoming' : '');
			if(isset($line['carir']['zones'][$zone][$line['usaget']]['rate'])) {	
				$rate =  $line['carir']['zones'][$zone][$line['usaget']]['rate'][0]['price'];
			}
		}
		return   $rate;
		
	}
	
	/**
	 * get  the price for a given line.
	 * @param type $line  the line to get the rate for.
	 * @return float the price of the line.
	 */
	abstract protected function priceForLine($line);
	
	/**
	 * Get the  textual representation of the product the cdr line represent.
	 * @param type $line the cdr line
	 * @return string the textual name  of the product that  was used.
	 */
	protected function productType($line) {
		$ret = ucfirst($line['usaget']);
		if(isset($line['provider_zone']['key'])) {
			$ret = $line['provider_zone']['key'] . " ".ucfirst($line['usaget']);
			if($line['provider_zone']['key'] == 'IL_TF') {
				$ret = "1800 ". ucfirst($line['usaget']);
			}
		}
		
		if(preg_match('/^(?=972|)144$/', $line['called_number'])) {
			$ret = "144 ".ucfirst($line['usaget']);
		}

		return $ret;
	}
	
	/**
	 * aggreate lines thats  was retrived from the DB 
	 * @param Mongodloid_Query $lines the CDR lines that were retrived from the DB.
	 * @return array containing the lines values  aggreagted by type  and day.
	 */
	protected function aggregate( $lines ) {
		Billrun_Factory::log()->log("Aggregating all the related CDRs, this can take a while...",Zend_Log::DEBUG);
		$aggregate = array();
		$callReferences= array();
		$linesCount = 0;
		$totalLinesCount = $lines->count();
		//Billrun_Factory::log()->log(print_r($lines->count(),1),Zend_Log::DEBUG);
		foreach ($lines as $value) {			
			if(($linesCount++) % 1000 == 0) {
				Billrun_Factory::log()->log(print_r("aggregated : ". ($linesCount/$totalLinesCount*100) . "%" ,1),Zend_Log::DEBUG);
			}
			
			if(isset($callReferences[$value['call_reference'].$value['called_number']])) { 
				continue;
			}
			$callReferences[$value['call_reference'].$value['called_number']] = true;
			
			$value->collection(Billrun_Factory::db()->linesCollection());
			$provider = $value[Billrun_Calculator_Carrier::MAIN_DB_FIELD]['key'];
			$isIncoming = $provider == 'GOLAN' || $provider == 'NR'; //!preg_match("/".$providerRegex."/", $value['out_circuit_group_name']);					
			if($isIncoming) {
				$provider = $value[Billrun_Calculator_Carrier::MAIN_DB_FIELD."_in"]['key'];
			}
			$lineConnectType = ($isIncoming ? substr($value['in_circuit_group_name'],0,1) : substr($value['out_circuit_group_name'],0,1));
			
			if(!isset($this->types[$lineConnectType])) {
				//Billrun_Factory::log()->log(print_r($value,1),Zend_Log::DEBUG);
				//continue;
				$lineConnectType = "Un";
			}
			$connectType =  $this->types[$lineConnectType];
			
			$day = substr($value['call_reference_time'],0,8);
			$aggrKey = $day.$isIncoming.$this->tariffForLine($value).$this->productType($value);
			
			if(!isset($aggregate[$provider][$connectType][$aggrKey])) {				
				$aggregate[$provider][$connectType][$aggrKey] =array(
											'day' => $day, 
											'product' => $this->productType($value),
											'units' => 0,
											'minutes' => 0,
											'tariff_per_product' => '',
											'charge' => 0,
											'direction' => ( $isIncoming ? 'TG' : 'FG'),
										);

			}
			$aggrGroup = &$aggregate[$provider][$connectType][$aggrKey];
			$aggrGroup['units']++;
			if($value['usaget'] == 'call') {
			$aggrGroup['minutes'] += ($value['charging_end_time'] && $value['charging_start_time']) ?
																				strtotime($value['charging_end_time']) - strtotime($value['charging_start_time']) :
																				$value['duration'];
			}
			$aggrGroup['tariff_per_product'] = $this->tariffForLine($value);
			$aggrGroup['charge'] +=  $this->priceForLine($value);
			
		}
		Billrun_Factory::log()->log(print_r("Done aggregating",1),Zend_Log::DEBUG);
		
		// process aggregated data.
		foreach ($aggregate as $provider => $val) {
			foreach ($aggregate[$provider] as $key => $connectType) {
					$tmp = array();
					ksort($connectType);
					foreach ($connectType as $value) {
						$value['minutes'] = $value['minutes']/60;
						$tmp[]= $value;
					}
					$aggregate[$provider][$key] = $tmp;
			}
		}
		return $aggregate;
	}
	
	/**
	 * Write an cascaded array into a CSV file.
	 * @param type $fileDesc the CSV file descriptor (should be opened).
	 * @param type $array the array to turn to CSV.
	 * @param type $depth (optional) the depth to show in the CSV.
	 */
	protected function addArrayToCSV($fileDesc, $array, $depth = 0) {
		$depthArr = array();
		for($i=0; $i < $depth; $i++) {
			$depthArr[] ='';
		}
		//Billrun_Factory::log()->log(print_r($array,1),Zend_Log::DEBUG);
		$line = $depthArr;
		foreach ($array as $key => $value) {			
			if(is_array($value)) {				
				if(!is_numeric($key)) {
					//Billrun_Factory::log()->log("$key : $depth",Zend_Log::DEBUG);
					fputcsv($fileDesc, array_merge($depthArr,array($key)));
				}
				$this->addArrayToCSV($fileDesc, $value, $depth+1);
				continue;
			}
			$line[] = $value;
		}
		fputcsv($fileDesc, $line);
	}
}
