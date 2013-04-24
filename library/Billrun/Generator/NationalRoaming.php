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
	
	/**
	 * Write the generated report to the national roaming CSV file.
	 * @see Billrun_Generator_Base_WholesaleReport::generate
	 */
	public function generate() {
		$providerResults = parent::generate();
		$wh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR. date('Ymd').'_national_roaming.csv', 'w');
		fputcsv($wh,  array('Provider','Connection Type','Incoming / outgoing','','Day','Product','Units','Minutes' ,'Tariff per product' ,'Charge' ,'Direction' ));
		
		$this->addArrayToCSV( $wh, $providerResults);
		fclose($wh);
		return $providerResults;
	}
	
	/**
	 * @see Billrun_Generator_Base_WholesaleReport::getCallLinesForProvider()
	 */
	protected function getCDRs($provider, $timehorizons) {

		$results = Billrun_Factory::db()->linesCollection()->query(array(
											'type'=>'nsn',
											'record_type' => array('$in' => array("12","11","04")),
											'$and' => array(
												array( 'unified_record_time' => array('$gt' => $timehorizons['start'] ) ),
												array( 'unified_record_time' => array('$lt' => $timehorizons['end'] ) ),
											),
											'$and' => array(
												array( '$or' => array( 
															array("in_circuit_group_name" => array('$regex' => "$provider" )),
															array("out_circuit_group_name" => array('$regex' => "$provider" ))
													),
												),
												array('$or' => array( 
														array("in_circuit_group_name" => array('$regex' => self::CELLCOM_ROAMING_REGEX  )),
														array("out_circuit_group_name" => array('$regex' => self::CELLCOM_ROAMING_REGEX  )),
													),
												),
											),		
										));

		$lines = array();
		foreach ($results as $key => $value) {
			$lines[$value['call_reference']] = $value;
		}
		return $lines;
	}
	
}