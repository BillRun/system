<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for service records
 *
 * @package  calculator
 * @since    2.8
 */
class Billrun_Calculator_Rate_Service extends Billrun_Calculator_Rate {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "service";

	public function __construct($options = array()) {
		parent::__construct($options);
		$this->loadRates();
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 */
	protected function getLineVolume($row, $usage_type) {
		return $row['count'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 */
	protected function getLineUsageType($row) {
		return 'service';
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {

		$line_key = $row['service_name'];
		$line_time = $row['urt'];
		foreach ($this->rates as $rate) {
			if ( ($rate['key'] == $line_key) && ($line_time >= $rate['from']) && ($line_time <= $rate['to']) ) {
				return $rate;
			}
		}
		return false;

	}

	/**
	 * Caches the rates in the memory for fast computations
	 */
	protected function loadRates() {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$query = array(
			'rates.service' => array(
				'$exists' => 1
			),
		);
		$rates = $rates_coll->query($query)->cursor();
		$this->rates = array();
		foreach ($rates as $rate) {
			$rate->collection($rates_coll);
			$this->rates[] = $rate;
		}
	}
	
}
