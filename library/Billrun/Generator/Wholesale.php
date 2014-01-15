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

	static protected $type = 'wholesale';

	public function __construct($options) {
		parent::__construct(array_merge($options, array('report_type' => 'wholesale')));
	}

	/**
	 * Write the generated report to the the fixed operator seperate files and  the wholesale report CSV files.
	 * @see Billrun_Generator_Base_WholesaleReport::generate
	 */
	public function generate() {
		$providerResults = parent::generate();
		foreach ($providerResults as $providerName => $value) {
			$fh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR . date('Ymd') . '_' . $providerName . '.csv', 'w');
			fputcsv($fh, array('Connection Type', '', 'Day', 'Product', 'Units', 'Minutes', 'Tariff per product', 'Charge', 'Direction'));
			$this->addArrayToCSV($fh, $value);
			fclose($fh);
		}
		$wh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR . date('Ymd') . '_wholesale_report.csv', 'w');
		fputcsv($wh, array('Provider', 'Connection Type', '', 'Day', 'Product', 'Units', 'Minutes', 'Tariff per product', 'Charge', 'Direction'));

		$this->addArrayToCSV($wh, $providerResults);
		fclose($wh);

		return $providerResults;
	}

	/**
	 * @see Billrun_Generator_Base_WholesaleReport::getCallLinesForProvider()
	 */
	protected function getCDRs($timehorizons) {
		//Billrun_Factory::log()->log("Retriving CDRs from DB  For {$provider}",Zend_Log::DEBUG);
		$results = Billrun_Factory::db()->linesCollection()->query(
				array(
					'type' => 'nsn',
					Billrun_Calculator_Wholesale_WholesalePricing::MAIN_DB_FIELD => array('$exists' => true),
					'urt' => array('$gte' => $timehorizons['start'], '$lt' => $timehorizons['end']),
					'record_type' => array('$in' => array("11", "12", "08", "09", "01", "02")),
			))->cursor()->sort(array('record_type' => -1));

		return $results;
	}

	/**
	 * 
	 * @param type $line
	 * @return type
	 */
	protected function priceForLine($line) {
		return isset($line[Billrun_Calculator_Wholesale_WholesalePricing::MAIN_DB_FIELD]) ? $line[Billrun_Calculator_Wholesale_WholesalePricing::MAIN_DB_FIELD] : 0;
	}

}
