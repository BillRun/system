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
	 * @var Billrun_CurrencyConvert_Manager
	 */
	protected static $instance;

	public static function getInstance($params = []) {
		if (is_null(self::$instance)) {
			self::$instance = new self($params);
		}
		
		return self::$instance;
	}

	/**
	 * Whether or not multi currency enabled
	 * 
	 * @return bool
	 */
	public static function isMultiCurrencyEnabled() {
		return Billrun_Factory::config()->getConfigValue('pricing.multi_currency', false);
	}

	/**
	 * Get system default currency
	 *
	 * @return string currency
	 */
	public static function getDefaultCurrency() {
		return Billrun_Factory::config()->getConfigValue('pricing.currency', 'USD');
	}

	/**
	 * Get system's available currencies
	 * 
	 * @return array currencies
	 */
	public static function getAvailableCurrencies() {
		return Billrun_Factory::config()->getConfigValue('pricing.available_currencies', []);
	}

	/**
	 * Convert given amount from $baseCurrency to $targetCurrency.
	 * Conversion is done according to the $time received (default is now)
	 * 
	 * @param $baseCurrency string currency to convert from
	 * @param $targetCurrency string currency to convert to
	 * @param $amount float amount to convert
	 * @param $time unixtimestamp exchange rate time
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
