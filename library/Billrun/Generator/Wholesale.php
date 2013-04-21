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
class Billrun_Generator_Wholesale extends Billrun_Generator {
	
	protected $reportType = 'wholesale';
	
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
			//	'PBX' => 'P',
			//	'voice_mail' => 'V',
				'016' => 'G',
			);
	public function __construct($options) {
		parent::__construct($options);
		$this->reportBasePath = Billrun_Factory::config()->getConfigValue('wholesale.reports.path', './');
		$this->reportType  = isset($options['report_type']) ? $options['report_type'] : $this->reportType;
	}
	public function generate() {
		$ret =array();
		$wh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR. date('Ymd').'_wholesale_report.csv', 'w');
		foreach($this->providers as $key => $val) {
			$providerResults = array();
			Billrun_Factory::log()->log("-----------------------------------------------------------------------",Zend_Log::DEBUG);
			Billrun_Factory::log()->log("$key : ",Zend_Log::DEBUG);
			fputcsv($wh, array($key));
			$fh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR. date('Ymd').'_'.$key.'.csv', 'w');
			foreach ($this->types as $typeName => $type) {
				//$incoming = $this->callForProvider('in',array_merge($val,array('type' => $type)));
				//$outgoing = $this->callForProvider('out',array_merge($val,array('type' => $type)));
				$providerAggregation = $this->aggregate( $this->getCallLinesForProvider("^{$type}{$val['provider']}", new MongoDate(strtotime('2013-01-01T00:00:00Z')) ));
				if( !empty($providerAggregation) ) {
					Billrun_Factory::log()->log("$typeName : ",Zend_Log::DEBUG);
					$providerResults[$typeName] = $providerAggregation;
					fputcsv($fh, array($typeName));
					fputcsv($wh, array($typeName));
					
					$order = array('day','product','units','minutes','tariff_per_product','charge','direction');
					foreach ($providerAggregation as $typeKey => $lines) {
						fputcsv($wh, array($typeKey));
						fputcsv($wh, array($typeKey));
						foreach ( $lines as $line) {
							$l = array();
							foreach ($order as $name) {
								$l[] = $line[$name];
							}
							fputcsv($fh, $l);
							fputcsv($wh, $l);
						}	
					}
					
					Billrun_Factory::log()->log(print_r($providerResults[$typeName],1),Zend_Log::DEBUG);
				}
			}
			
			fclose($fh);
			$ret[] = $providerResults; 
		}
		fclose($wh);
		return $ret;
	}
	

	public function load($initData = true) {
		return array();
	}
	
//	protected function callForProvider($dir, $param) {
//		$provider = "{$param['type']}{$param['provider']}";
//
//		$direction = array(
//							'in' => array(
//								'match' => array( 
//											'$or' => array(
//												array('record_type' => array('$in' => array("02"))),
//												array('$and' => array(array('record_type' => array('$in' => array("04"))), array('in_circuit_group_name' => array('$regex' => "^RCEL") ))),
//											),
//										),	
//									),
//							'out'=> array(
//								'match' => array( 
//										'$or' => array(
//											array('record_type' => array('$in' => array("01"))),
//											array('$and' => array(array('record_type' => array('$in' => array("04"))), array('in_circuit_group_name' => array('$regex' => "^RCEL") ) )),
//										),
//									),
//								),
//						);
//		$lines = Billrun_Factory::db()->linesCollection();
//		$results = $lines->aggregate(
//						array(
//							array(
//								'$match' => array_merge( 
//										array(
//											'type'=>'nsn',										
//											'unified_record_time' => array('$gt' => new MongoDate(strtotime('2013-01-01T00:00:00Z'))),	
//										),
//										$direction[$dir]['match']
//									),
//							),
//							array(
//								'$match' => array(							
//										"{$dir}_circuit_group_name" => array('$regex' => "$provider" ),							
//									),
//							),
//							array(
//								'$project' => array(
//											"{$dir}_circuit_group_name" => 1,
//											'charging_start_time' => 1,
//											'call_reference_time' => 1,
//											'calc_duration' =>array('$cond' => array( array('$gt' => array( '$charging_start_time', 0)),
//																						array('$subtract'=>  array('$charging_end_time','$charging_start_time')),
//																						0
//																					)),
//											'unified_record_time' => 1,
//											'product' => array('$cond' => array( array('$eq' => array( '$record_type', ($dir == 'in' ? "09" : "08"))),'SMS','Call')),
//									),
//							),
//						array(
//							'$group' => array(
//									'_id' => array(
//												'product' => '$product',
//												'date' => array('$substr' => array('$call_reference_time',0,8)),
//												//'date' => array( '$dayOfMonth' => '$unified_record_time' ), 
//												'group_name' => array( '$substr' => array('$'.$dir.'_circuit_group_name',0,4) )
//											),
//									'minutes' => array('$sum' => array('$divide'=> array('$calc_duration',60))),
//									'units' => array('$sum' => 1),
//							),
//						
//						),
//						array(
//							'$project' => array(
//										'_id' => 0,
//										'day' => '$_id.date',
//										'product' => '$_id.product',
//										'units' => 1,
//										'minutes' => 1,											
//										'tariff_per_product' => 1,
//										'charge' => 1,
//										'direction' => array('$cond' => array( $dir == 'in' ,'TG','FG')),
//								),
//						),							
//					)
//			);
//			
//			return $results;
//	}
	protected function getCallLinesForProvider($provider, $timehorizon) {
		$results = Billrun_Factory::db()->linesCollection()->query(array(
											'type'=>'nsn',
											'unified_record_time' => array('$gt' => $timehorizon )))->
											query((array( '$or' =>array(
													array('record_type' => array('$in' => array("02","01"))),
													array('$and' => array(	array('record_type' => array('$in' => array("11","12"))), 
																			array('$or' => array( 
																				array("in_circuit_group_name" => array('$regex' => "^RCEL" )),
																				array("out_circuit_group_name" => array('$regex' => "^RECL" ))
																			),)) 
														),)
												)))->
										query(array(
											'$or' => array( 
														array("in_circuit_group_name" => array('$regex' => "$provider" )),
														array("out_circuit_group_name" => array('$regex' => "$provider" ))
												),
										));
							;

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
			if( preg_match("/^RCEL/", $value['out_circuit_group_name']) ) {
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
			$aggregate[$callType][$aggrKey]['minutes'] += $value['charging_end_time'] - $value['charging_start_time'];
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

