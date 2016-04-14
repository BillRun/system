<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Util class for the balances
 *
 * @author Tom Feigin
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