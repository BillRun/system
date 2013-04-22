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
class Billrun_Generator_Wholesale extends Billrun_Generator_Base_WholesaleReport {

	public function __construct($options) {
		parent::__construct(array_merge($options, array('report_type' => 'wholesale')));
	}
	
	public function generate() {
		$providerResults = parent::generate();
		foreach ($providerResults as $providerName => $value) {
				$fh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR. date('Ymd').'_'.$providerName.'.csv', 'w');
				fputcsv($fh,  array('Connection Type','Incoming / outgoing','','Day','Product','Units','Minutes' ,'Tariff per product' ,'Charge' ,'Direction' ));				
				$this->addArrayToCSV( $fh, $value);
				fclose($fh);
		}
		$wh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR. date('Ymd').'_wholesale_report.csv', 'w');
		fputcsv($wh,  array('Provider','Connection Type','Incoming / outgoing','','Day','Product','Units','Minutes' ,'Tariff per product' ,'Charge' ,'Direction' ));
		
		$this->addArrayToCSV( $wh, $providerResults);
		fclose($wh);
		
		return $providerResults;
	}
	

	/**
	 * @see Billrun_Generator_Base_WholesaleReport::getCallLinesForProvider()
	 */
	protected function getCallLinesForProvider($provider, $timehorizon) {
		Billrun_Factory::log()->log("Retriving CDRs from DB  For {$provider}",Zend_Log::DEBUG);
		$results = Billrun_Factory::db()->linesCollection()->query(
										array(
											'type'=>'nsn',
											'unified_record_time' => array('$gt' => $timehorizon ),
											'$and' => array(
												array('$or' => array(
															array('record_type' => array('$in' => array("02","01"))),
															array('$and' => array(
																				array('record_type' => array('$in' => array("11","12"))), 
																				array('$or' => array( 
																					array("in_circuit_group_name" => array('$regex' => "^RCEL" )),
																					array("out_circuit_group_name" => array('$regex' => "^RECL" ))
																				),)) 
															),
													),),
												array('$or' => array( 
															array("in_circuit_group_name" => array('$regex' => "$provider" )),
															array("out_circuit_group_name" => array('$regex' => "$provider" ))
													),),
											),
										));

		return $results;
	}
}
