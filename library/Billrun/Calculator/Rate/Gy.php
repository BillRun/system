<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 * @since    4.0
 * @todo Merge to general internet data rate calculator
 *
 */
class Billrun_Calculator_Rate_Gy extends Billrun_Calculator_Rate_Ggsn {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'gy';

	/**
	 * @see Billrun_Calculator_Rate::getLineVolume
	 * @deprecated since version 2.9
	 */
	protected function getLineVolume($row) {
		return $row['MSCC']['used'];
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineUsageType
	 * @deprecated since version 2.9
	 */
	protected function getLineUsageType($row) {
		return 'data';
	}

	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row) {
		$line_time = $row['urt'];
		foreach ($this->rates as $rate) {
			if (preg_match($rate['params']['sgsn_addresses'], $row['sgsn_address']) && $rate['from'] <= $line_time && $line_time <= $rate['to']) {
				return $rate;
			}
		}
		Billrun_Factory::log()->log("Couldn't find rate for row : " . print_r($row['stamp'], 1), Zend_Log::DEBUG);
		return FALSE;
	}

}
