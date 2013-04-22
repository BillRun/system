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
class Billrun_Generator_NationalRoaming extends Billrun_Generator_Base_WholesaleReport {
		
	public function __construct($options) {
		parent::__construct(array_merge($options, array('report_type' => 'national_roaming')));
	}
	public function generate() {
		$providerResults = parent::generate();
		$wh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR. date('Ymd').'_notional_roaming.csv', 'w');
		fputcsv($wh,  array('Provider','Connection Type','Incoming / outgoing','','Day','Product','Units','Minutes' ,'Tariff per product' ,'Charge' ,'Direction' ));
		
		$this->addArrayToCSV( $wh, $providerResults);
		fclose($wh);
		return $providerResults;
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
														array("out_circuit_group_name" => array('$regex' => "^RCEL" ))
													),
												),
											),		
										));

		return $results;
	}
	
}