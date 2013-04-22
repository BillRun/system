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

	
	protected $reportType = 'report';
	
	protected $providers;
			
	
	protected $types;
	public function __construct($options) {
		parent::__construct($options);
		$this->reportType  = isset($options['report_type']) ? $options['report_type'] : $this->reportType;
		$this->reportBasePath = Billrun_Factory::config()->getConfigValue($this->reportType.'.reports.path', './');

		$this->types = Billrun_Factory::config()->getConfigValue( $this->reportType.'.reports.types',
																  array(
																		'R' => '',
																		'I' => '',
																		'M' => '',
																		'N' => '',
																		'4' => '' ,
																	));
		$this->providers = Billrun_Factory::config()->getConfigValue( $this->reportType.'.reports.providers', 
																	  array(
																			'Bezeq' => array('provider'=> '^\wBZ[$Q]'),
																			'BezeqInt' => array('provider'=> "^\wBZI"),
																			'Cellcom' => array('provider'=> "^[^R]CEL"),
																			'Partner' => array('provider'=> "^\wPRT"),
																			'Mirs' => array('provider'=> "^\wMRS"),
																			'Pelephone' => array('provider'=> "^\wPEL"),
																			'Smile' => array('provider'=> "^\wSML"),
																			'Hot' => array('provider'=> "^\wHOT"),
																			'Telzar' =>	array('provider'=> "^\wTLZ"),
																			'Xfone' =>	array('provider'=> "^\wXFN"),
																			'Hilat'=>	array('provider'=> "^\wHLT"),
																			'Kartel'=>	array('provider'=> "^\wKRT(?=ROM|)"),
																			'Paltel'=>	array('provider'=> "^\wSPAL"),
																			'Wataniya'=>	array('provider'=> "^\wSWAT"),
																		));
	}
	public function generate() {
		$providerResults = array();
		$timeHorizion = new MongoDate(strlen($this->stamp) > 8 ? strtotime($this->stamp) : Billrun_Util::getLastChargeTime(true));
		foreach($this->providers as $providerName => $val) {
			Billrun_Factory::log()->log("Aggregating  $providerName NSN usage: ",Zend_Log::DEBUG);
			$providerAggregation = $this->aggregate( $this->getCallLinesForProvider("{$val['provider']}", $timeHorizion ));
			
			if( empty($providerAggregation) ) {
				continue;
			}
			$providerResults[$providerName] = $providerAggregation; 
		}
		return $providerResults;
	}
	
	/**
	 * 
	 * @param type $initData
	 * @return type
	 */
	public function load($initData = true) {
		return  $this->getCallLinesForProvider("", new MongoDate(Billrun_Util::getLastChargeTime()));
	}
	
	/**
	 *  retrive the call CDR lines from the DB for a given provider.
	 */
	abstract protected function getCallLinesForProvider($provider, $timehorizon);
	
	/**
	 * get  Rates for a given line
	 * @param type $line  the line to get the rate for.
	 * @return array containing the ratiing  details.
	 */
	protected function getTariffForLine($line) {
		//TODO when  merged with the rating system.
		return '';
	}
	
	/**
	 * aggreate lines thats  was retrived from the DB 
	 * @param Mongodloid_Query $lines the CDR lines that were retrived from the DB.
	 * @return array containing the lines values  aggreagted by type  and day.
	 */
	protected function aggregate(Mongodloid_Query $lines) {
		Billrun_Factory::log()->log("Aggregating all the related CDRs, this can take awhile...",Zend_Log::DEBUG);
		$aggregate = array();
	
		foreach ($lines as $value) {
			$isIncoming = ($value['record_type'] == "02" || $value['record_type'] == "12" && preg_match("/^RCEL/", $value['out_circuit_group_name']));
			$callType =  $isIncoming ?  'incoming' : 'outgoing';			
			$lineConnectType = ($isIncoming ? substr($value['in_circuit_group_name'],0,1) : substr($value['out_circuit_group_name'],0,1));
			
			if(!isset($this->types[$lineConnectType])) {
				Billrun_Factory::log()->log(print_r($value,1),Zend_Log::DEBUG);
				continue;
			}
			$connectType =  $this->types[$lineConnectType];
			$day = substr($value['call_reference_time'],0,8);
			$aggrKey = $day.$this->getTariffForLine($value);
			
			if(!isset($aggregate[$connectType][$callType][$aggrKey])) {
				$aggregate[$connectType][$callType][$aggrKey] =array(
													'day' => $day, 
													'product' => $callType.' calls',
													'units' => 0,
													'minutes' => 0,
													'tariff_per_product' => '',
													'charge' => 0,
													'direction' => ( $isIncoming ? 'TG' : 'FG'),
												);
			}
	
			$aggregate[$connectType][$callType][$aggrKey]['units']++;
			$aggregate[$connectType][$callType][$aggrKey]['minutes'] += ($value['charging_end_time'] && $value['charging_start_time']) ?
																				strtotime($value['charging_end_time']) - strtotime($value['charging_start_time']) :
																				$value['duration'];
			$aggregate[$connectType][$callType][$aggrKey]['tariff_per_product'] = $this->getTariffForLine($value);
			$aggregate[$connectType][$callType][$aggrKey]['charge'] += isset($value['provider_price']) ? $value['provider_price'] : 0;
		}
		
		// process aggregated data.
		foreach ($aggregate as &$connectType) {
			foreach ($connectType as &$calltype) {
				foreach ($calltype as  &$value) {
					$value['minutes'] = $value['minutes']/60;
				}
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

