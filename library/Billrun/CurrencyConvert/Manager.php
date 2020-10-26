<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class handles Currency Conversions
 */
class Billrun_CurrencyConvert_Manager {

	/**
	 * Instance used as single instance
	 * 
	 * @var Billrun_CurrencyConvert_Manager
	 */
	protected static $instance;
	
	/**
	 * get single instance of Currency Convert
	 *
	 * @param  array $params
	 * @return Billrun_CurrencyConvert_Manager
	 */
	public static function getInstance($params = []) {
		if (is_null(self::$instance)) {
			self::$instance = new self($params);
		}
		
		return self::$instance;
	}

	/**
	 * whether or not multi currency enabled
	 * 
	 * @return boolean
	 */
	public static function isMultiCurrencyEnabled() {
		$availableCurrencies = self::getAvailableCurrencies();
		return count($availableCurrencies) > 0;
	}

	/**
	 * get system default currency
	 *
	 * @return string currency
	 */
	public static function getDefaultCurrency() {
		return Billrun_Factory::config()->getConfigValue('pricing.currency', 'USD');
	}

	/**
	 * get system's available currencies
	 * 
	 * @return array currencies
	 */
	public static function getAvailableCurrencies() {
		return Billrun_Factory::config()->getConfigValue('pricing.additional_currencies', []);
	}

	/**
	 * Convert given amount from $baseCurrency to $targetCurrency.
	 * Conversion is done according to the $time received (default is now)
	 * 
	 * @param string $baseCurrency currency to convert from
	 * @param string $targetCurrency currency to convert to
	 * @param float $amount amount to convert
	 * @param int $time (unixtimestamp) exchange rate time
	 * 
	 * @return float converted amount on success, false otherwise
	 */
	public function convert($baseCurrency, $targetCurrency, $amount, $time = null) {
		$exchangeRate = new Billrun_ExchangeRate($baseCurrency, $targetCurrency, null, $time);
		$rate = $exchangeRate->getRate();
        if ($rate === false || is_null($rate)) {
            return false;
        }

        return $amount * $rate;
	}
}
