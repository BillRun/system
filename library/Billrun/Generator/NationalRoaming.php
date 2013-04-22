<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Reports
 *
 * @author eran
 */
class Billrun_Generator_NationalRoaming extends Billrun_Generator {
	
	protected $reportType = 'national_roaming';
	
	protected $providers= array(
			'Bezeq' => array('provider'=> 'BZ[$Q]'),
			'BezeqInt' => array('provider'=> "BZI"),
			'Cellcom' => array('provider'=> "CEL"),
			'Partner' => array('provider'=> "PRT"),
			'Mirs' => array('provider'=> "MRS"),
			'Pelephone' => array('provider'=> "PEL"),
			'Smile' => array('provider'=> "SML"),
			'Hot' => array('provider'=> "HOT"),
			'Telzar' =>	array('provider'=> "TLZ"),
			'Xfone' =>	array('provider'=> "XFN"),
			'Hilat'=>	array('provider'=> "HLT"),
			'Kartel'=>	array('provider'=> "KRT(?=ROM|)"),
			'Paltel'=>	array('provider'=> "SPAL"),
			'Wataniya'=>	array('provider'=> "SWAT"),
		);
	protected $types = array(
				//'roaming' => "R",
				'international' => 'I',
				'connectivity' => 'M',
				'national' => 'N',
				'144' => '4',
			);
	public function __construct($options) {
		parent::__construct($options);
		$this->reportBasePath = Billrun_Factory::config()->getConfigValue('national_roaming.reports.path', './');
		$this->reportType  = isset($options['report_type']) ? $options['report_type'] : $this->reportType;
	}
	public function generate() {
		$ret =array();
		$wh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR. date('Ymd').'_notional_roaming.csv', 'w');
		foreach($this->providers as $providerName => $val) {
			$providerResults = array();
			Billrun_Factory::log()->log("-----------------------------------------------------------------------",Zend_Log::DEBUG);
			Billrun_Factory::log()->log("$providerName : ",Zend_Log::DEBUG);
			fputcsv($wh, array($providerName));
			foreach ($this->types as $typeName => $type) {
				$aggregate = $this->aggregate( $this->getCallLinesForProvider("^{$type}{$val['provider']}", new MongoDate(strtotime('2013-01-01T00:00:00Z')) ));
				if(!empty($aggregate) ) {
					Billrun_Factory::log()->log("$typeName : ",Zend_Log::DEBUG);
					$providerResults[$typeName] = $aggregate;
					fputcsv($wh, array('',$typeName));
					
					$order = array('day','product','units','minutes' ,'tariff_per_product' ,'charge' ,'direction' );
					foreach ($aggregate as $typeKey => $lines) {
						foreach ( $lines as $line) {
							$l = array('','',);
							foreach ($order as $name) {
								$l[] = $line[$name];
							}
							fputcsv($wh, $l);
						}	
					}
					Billrun_Factory::log()->log(print_r($providerResults[$typeName],1),Zend_Log::DEBUG);
				}
			}

			$ret[] = $providerResults; 
		}
		fclose($wh);
		return $ret;
	}
	

	public function load($initData = true) {
		return array();
	}
	
	protected function getCallLinesForProvider($provider, $timehorizon) {

		$results = Billrun_Factory::db()->linesCollection()->query(array(
											'type'=>'nsn',
											'record_type' => array('$in' => array("11","12")),
											'unified_record_time' => array('$gt' => $timehorizon ),
											'$and' => array(
												array( '$or' => array( 
															array("in_circuit_group_name" => array('$regex' => "$provider" )),
															array("out_circuit_group_name" => array('$regex' => "$provider" ))
													),
												),
												array('$or' => array( 
														array("in_circuit_group_name" => array('$regex' => "^RCEL" )),
														array("out_circuit_group_name" => array('$regex' => "^RECL" ))
													),
												),
											),		
										));

		return $results;
	}
	
	protected function getTariffForLine($param) {
		//TODO
		return '';
	}
	
	protected function aggregate($lines) {
		$aggregate = array();
		
		foreach ($lines as $value) {
			//Billrun_Factory::log()->log(print_r($value,1),Zend_Log::DEBUG);
			if( $value['record_type'] == "12"  ) {
				$callType = 'incoming';
			} else {
				$callType = 'outgoing';
			}
			$day = substr($value['call_reference_time'],0,8);
			$aggrKey = $day.$this->getTariffForLine($value);
			if(!isset($aggregate[$callType][$aggrKey])) {
				$aggregate[$callType][$aggrKey] =array(
													'day' => $day, 
													'product' => $callType.' call',
													'units' => 0,
													'minutes' => 0,
													'tariff_per_product' => '',
													'charge' => 0,
													'direction' => ($callType == 'incoming' ? 'TG' : 'FG'),
												);
			}
			 
			$aggregate[$callType][$aggrKey]['units']++;
			$aggregate[$callType][$aggrKey]['minutes'] += strtotime($value['charging_end_time']) - strtotime($value['charging_start_time']);
			$aggregate[$callType][$aggrKey]['tariff_per_product'] = $this->getTariffForLine($value);
			$aggregate[$callType][$aggrKey]['charge'] += isset($value['provider_price']) ? $value['provider_price'] : 0;
		}
		
		// process aggregated data.
		foreach ($aggregate as &$calltype) {
			foreach ($calltype as  &$value) {
				$value['minutes'] = $value['minutes']/60;
			}
		}
		return $aggregate;
	}
}

?>

