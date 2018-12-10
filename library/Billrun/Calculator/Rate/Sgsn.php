<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * @since    0.5
 *
 */
class Billrun_Calculator_Rate_Sgsn extends Billrun_Calculator_Rate_Ggsn {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'sgsn';
	
	protected function getLineVolume($row, $usage_type) {
		return $row['gprs_downlink_volume'] + $row['gprs_uplink_volume'];
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		$roamingRate = $this->getRoamingLineRate($row, $usage_type);
		if ($roamingRate) {
			return $roamingRate;
		}
		return parent::getLineRate($row, $usage_type);
	}

}
