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
		$this->providers['Golan'] = array('provider'=> '^(?=RCEL.*|)$');
		$this->providers['All'] = array('provider'=> '.*');
		$this->providers['Voice Mail'] = array('provider'=> '^VVOM');
		
	}
	
	/**
	 * Write the generated report to the national roaming CSV file.
	 * @see Billrun_Generator_Base_WholesaleReport::generate
	 */
	public function generate() {
		$providerResults = parent::generate();
		$wh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR. date('Ymd').'_national_roaming.csv', 'w');
		fputcsv($wh,  array('Provider','Connection Type','','Day','Product','Units','Minutes' ,'Tariff per product' ,'Charge' ,'Direction' ));
		
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
											'record_type' => array('$in' => array("12","11")),
											'price_nr' => array('$exists' => true),
											'unified_record_time' => array( '$gt' => $timehorizons['start'] , '$lt' => $timehorizons['end'] ,),
											'$or' => array( 
														array(	"in_circuit_group_name" => array('$regex' => "$provider" ),
																"out_circuit_group_name" => array('$regex' => self::CELLCOM_ROAMING_REGEX  )),
												
														array(	"in_circuit_group_name" => array('$regex' => self::CELLCOM_ROAMING_REGEX  ),
																"out_circuit_group_name" => array('$regex' => "$provider" ),),
											),		
										));

		return $results;
	}

	protected function priceForLine($line) {
		return isset($value['price_nr']) ? $value['price_nr'] : 0;
	}
	
}