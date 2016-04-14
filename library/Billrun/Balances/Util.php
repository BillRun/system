<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the balances
 *
 * @package  Util
 * @since    4.1
 */
class Billrun_Balances_Util {
	
	/**
	 * Get the balance value rounded to a point.
	 * @param array $balance - Balance record
	 * @param type $precision - The precision point to round the value to, default is 5.
	 * @return int Value of the balance.
	 */
	public static function getBalanceValue($balance, $precision = 5) {
		if(!$balance) {
			return 0;
		}
		
		if(!isset($balance['balance'])) {
			Billrun_Factory::log("Received invalid balance record!", Zend_Log::ERR);
			return 0;
		}
		
		$balanceValue = $balance['balance'];
		$value = Billrun_Util::getFirstValueOfMultidimentionalArray($balanceValue);
		$rounded = round($value, $precision, PHP_ROUND_HALF_UP);
		return $rounded;
	}	
}