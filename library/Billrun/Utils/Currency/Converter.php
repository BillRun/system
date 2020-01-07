<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2018 BillRun LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing utilization class to handle currency conversions
 *
 * @package  Util
 * @since    2.6
 */
class Billrun_Utils_Currency_Converter {

	/**
	 * convert the amount received from given currency to the given currency
	 * 
	 * @param float $amount
	 * @param string $fromCurrency
	 * @param string $toCurrency
	 * @param unixtimestamp $urt - affective time (default is now)
	 * @return converted amount on success, false otherwise
	 * @todo currently, only uses for SDR conversions
	 */
	public static function convertCurrency($amount, $fromCurrency, $toCurrency, $urt = null) {
		if ($fromCurrency == $toCurrency) {
			return $amount;
		} 
		
		if ($fromCurrency == 'SDR') {
			return self::convertFromSdr($amount, $toCurrency, $urt);
		}
		
		if ($toCurrency == 'SDR') {
			return self::convertToSdr($amount, $fromCurrency, $urt);
		}
		
		return false;
	}
	
	/**
	 * convert given amount to SDR from given currency
	 * 
	 * @param float $amount
	 * @param string $fromCurrency
	 * @param unixtimestamp $urt - affective time (default is now)
	 * @return converted amount on success, false otherwise
	 */
	protected static function convertToSdr($amount, $fromCurrency, $urt = null) {
		$rate = self::getSdrExchangeRate($urt);
		if (!$rate || !isset($rate['rates'][$fromCurrency])) {
			return false;
		}
		$exchangeRate = $rate['rates'][$fromCurrency];
		return $amount * $exchangeRate;
	}
	
	/**
	 * convert given amount from SDR to given currency
	 * 
	 * @param float $amount
	 * @param string $fromCurrency
	 * @param unixtimestamp $urt - affective time (default is now)
	 * @return converted amount on success, false otherwise
	 */	
	protected static function convertFromSdr($amount, $toCurrency, $urt = null) {
		$rate = self::getSdrExchangeRate($urt);
		if (!$rate || !isset($rate['rates'][$toCurrency])) {
			return false;
		}
		$exchangeRate = $rate['rates'][$toCurrency];
		return $amount / $exchangeRate;
	}
	
	/**
	 * gets the rate used for SDR conversions
	 * 
	 * @param unixtimestamp $urt - affective time (default is now)
	 * @return rate object if found, false otherwise
	 */
	protected static function getSdrExchangeRate($urt = null) {
		if (is_null($urt)) {
			$urt = time();
		}
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$key = 'INCOMING_ROAMING_SDR_EXCHANGE_RATE';
		$query = array(
			'from' => array(
				'$lte' => new MongoDate($urt),
			),
			'to' => array(
				'$gte' => new MongoDate($urt),
			),
			'key' => $key,
		);		
		$rate = $rates_coll->query($query)->cursor()->current();
		if ($rate->isEmpty()) {
			return false;
		}
		return $rate;
	}

}