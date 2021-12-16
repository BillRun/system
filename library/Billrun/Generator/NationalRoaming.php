<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * National roaming generator class
 *
 * @package  Billing
 * @since    0.5
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
		$wh = fopen($this->reportBasePath . DIRECTORY_SEPARATOR . date('Ymd') . '_national_roaming.csv', 'w');
		fputcsv($wh, array('Provider', 'Connection Type', '', 'Day', 'Product', 'Units', 'Minutes', 'Tariff per product', 'Charge', 'Direction'));

		$this->addArrayToCSV($wh, $providerResults);
		fclose($wh);
		return $providerResults;
	}

	/**
	 * @see Billrun_Generator_Base_WholesaleReport::getCallLinesForProvider()
	 */
	protected function getCDRs($timehorizons) {

		$results = Billrun_Factory::db()->linesCollection()->query(array(
			'type' => 'nsn',
			Billrun_Calculator_Wholesale_NationalRoamingPricing::MAIN_DB_FIELD => array('$exists' => true),
			'urt' => array('$gt' => $timehorizons['start'], '$lt' => $timehorizons['end'],),
			/* '$or' => array( 							
			  array('record_type' => "12","out_circuit_group_name" => array('$regex' => self::CELLCOM_ROAMING_REGEX  )),
			  array('record_type' =>"11",	"in_circuit_group_name" => array('$regex' => self::CELLCOM_ROAMING_REGEX  ),),
			  ), */
		));

		return $results;
	}

	protected function priceForLine($line) {
		return isset($line['price_nr']) ? $line['price_nr'] : 0;
	}

}
