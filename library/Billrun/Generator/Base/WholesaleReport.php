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

	const CELLCOM_ROAMING_REGEX="^RCEL";
	
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
																		'R' => 'Roaming',
																		'I' => 'International',
																		'M' => 'Mobile',
																		'N' => 'National',
																		'4' => 'National' ,
																	));
		
		$this->providers = Billrun_Factory::config()->getConfigValue( $this->reportType.'.reports.providers', 
																	  array(
																			'Bezeq' => array('provider'=> '^\wBZQ'),
																			'BezeqInt' => array('provider'=> "^\wBZI"),
																			'Cellcom' => array('provider'=> "^[^R]CEL"),
																			'Partner' => array('provider'=> "^\wPRT"),
																			'Mirs' => array('provider'=> "^\wMRS"),
																			'Pelephone' => array('provider'=> "^\wPEL"),
																			/*'Smile' => array('provider'=> "^\wSML"),
																			'Hot' => array('provider'=> "^\wHOT"),
																			'Telzar' =>	array('provider'=> "^\wTLZ"),
																			'Xfone' =>	array('provider'=> "^\wXFN"),
																			'Hilat'=>	array('provider'=> "^\wHLT"),
																			'Kartel'=>	array('provider'=> "^\wKRT(?=ROM|)"),
																			'Paltel'=>	array('provider'=> "^\wSPAL"),
																			'Wataniya'=>	array('provider'=> "^\wSWAT"),*/
																		));
		$this->startDate = isset($options['start_date']) ? $options['start_date'] : 
							 (strlen($this->stamp) > 8 ? strtotime($this->stamp) : Billrun_Util::getLastChargeTime(true));
		$this->endDate = isset($options['start_date']) ? $options['start_date'] : 
							new MongoDate(strtotime($this->startDate) + 30*24*3600);
	}
	
	/**
	 * generate the reports
	 * @return array containg the -
	 */
	public function generate() {
		$providerResults = array();
		$startTime = strlen($this->stamp) > 8 ? strtotime($this->stamp) : Billrun_Util::getLastChargeTime(true);
		$timeHorizions['start'] = $this->startDate;
		$timeHorizions['end'] = $this->endDate;
		foreach($this->providers as $providerName => $val) {
			Billrun_Factory::log()->log("Aggregating  $providerName NSN usage: ",Zend_Log::DEBUG);
			$providerAggregation = $this->aggregate( $this->getCDRs($val['provider'], $timeHorizions ));
			
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
		$timeHorizions['start'] = new MongoDate(strlen($this->stamp) > 8 ? strtotime($this->stamp) : Billrun_Util::getLastChargeTime(true));
		$timeHorizions['end'] = new MongoDate(strlen($this->stamp) > 8 ? strtotime($this->stamp) : Billrun_Util::getLastChargeTime(true));

		return  false; //$this->getCDRs("", $timeHorizions);
	}
	
	/**
	 *  Retrive the call CDR lines from the DB for a given provider.
	 */
	abstract protected function getCDRs($provider, $timehorizon);
	
	/**
	 * get  Rates for a given line
	 * @param type $line  the line to get the rate for.
	 * @return array containing the ratiing  details.
	 */
	protected function tariffForLine($line) {
		//TODO when  merged with the rating system.
		return '';
	}
	
	protected function productType($line) {
		$ret = 'calls';
		if(preg_match('/^(?=972)1800/', $line['called_number'])) {
			$ret = "1800 calls";
		}
		if(preg_match('/^(?=972)144$/', $line['called_number'])) {
			$ret = "144 calls";
		}

		return $ret;
	}
	
	/**
	 * aggreate lines thats  was retrived from the DB 
	 * @param Mongodloid_Query $lines the CDR lines that were retrived from the DB.
	 * @return array containing the lines values  aggreagted by type  and day.
	 */
	protected function aggregate( $lines ) {
		Billrun_Factory::log()->log("Aggregating all the related CDRs, this can take awhile...",Zend_Log::DEBUG);
		$aggregate = array();
	
		foreach ($lines as $value) {
			$isIncoming = ($value['record_type'] == "02" || $value['record_type'] == "12" && preg_match("/".self::CELLCOM_ROAMING_REGEX."/", $value['out_circuit_group_name']));
			$lineConnectType = ($isIncoming ? substr($value['in_circuit_group_name'],0,1) : substr($value['out_circuit_group_name'],0,1));
			
			if(!isset($this->types[$lineConnectType])) {
				Billrun_Factory::log()->log(print_r($value,1),Zend_Log::DEBUG);
				continue;
			}
			$connectType =  $this->types[$lineConnectType];
			$day = substr($value['call_reference_time'],0,8);
			$aggrKey = $day.$isIncoming.$this->tariffForLine($value).$this->productType($value);
			
			if(!isset($aggregate[$connectType][$aggrKey])) {
				$aggregate[$connectType][$aggrKey] =array(
											'day' => $day, 
											'product' => $this->productType($value),
											'units' => 0,
											'minutes' => 0,
											'tariff_per_product' => '',
											'charge' => 0,
											'direction' => ( $isIncoming ? 'TG' : 'FG'),
										);

			}
			$aggrGroup = &$aggregate[$connectType][$aggrKey];
			$aggrGroup['units']++;
			$aggrGroup['minutes'] += ($value['charging_end_time'] && $value['charging_start_time']) ?
																				strtotime($value['charging_end_time']) - strtotime($value['charging_start_time']) :
																				$value['duration'];
			$aggrGroup['tariff_per_product'] = $this->tariffForLine($value);
			$aggrGroup['charge'] += isset($value['provider_price']) ? $value['provider_price'] : 0;
		}
		Billrun_Factory::log()->log(print_r("Done aggregating",1),Zend_Log::DEBUG);
		
		// process aggregated data.
		
		foreach ($aggregate as $key => $connectType) {
				$tmp = array();
				foreach ($connectType as $value) {
					$value['minutes'] = $value['minutes']/60;
					$tmp[]= $value;
				}
				$aggregate[$key] = $tmp;
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

