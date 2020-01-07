<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
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

}
