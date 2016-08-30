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

	// TODO: Move the default balance operation value to here.
	
	/**
	 * Get the operation value from an array of options
	 * @param array $options - Array of options that should have the operation field,
	 * if it doesn't have an operation field, return default operation value.
	 * @param string $operationKey - The field name for the operation in the options 
	 * array, "operation" by default.
	 */
	public static function getOperationValue($options, $operationKey="operation") {
		if(!isset($options[$operationKey])) {
			return 1;
		}
		
		// Get the operation value.
		$operation = $options[$operationKey];
		
		// Get the operation list.
		$operationList = Billrun_Factory::config()->getConfigValue("balances.operation");
		
		if(!isset($operationList[$operation])) {
			// Return default.
			return 1;
		}
		
		$operationValue = $operationList[$operation];
		return boolval($operationValue);
	}
	
	/**
	 * Get the balance value rounded to a point.
	 * @param array $balance - Balance record
	 * @param type $precision - The precision point to round the value to, default is 5.
	 * @return int Value of the balance.
	 */
	public static function getBalanceValue($balance, $precision = 5) {
		if (!$balance) {
			return 0;
		}

		if (!isset($balance['balance'])) {
			Billrun_Factory::log("Received invalid balance record!", Zend_Log::ERR);
			return 0;
		}

		$balanceValue = $balance['balance'];
		$value = Billrun_Util::getFirstValueOfMultidimentionalArray($balanceValue);
		$rounded = round($value, $precision, PHP_ROUND_HALF_UP);
		return $rounded;
	}

}
