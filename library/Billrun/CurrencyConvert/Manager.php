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
		return array_column(self::getCurrenciesConfig(), 'currency');
	}
	
	/**
	 * get additional currencies configuration
	 *
	 * @return array
	 */
	public static function getCurrenciesConfig() {
		return Billrun_Factory::config()->getConfigValue('pricing.additional_currencies', []);
	}
		
	/**
	 * get currencies that is required to sync their exchange rate
	 *
	 * @return array
	 */
	public static function getCurrenciesToSync() {
		$ret = [];
		foreach (self::getCurrenciesConfig() as $currencyConfig) {
			if (Billrun_Util::getIn($currencyConfig, 'auto_sync', true)) {
				$ret[] = $currencyConfig['currency'];
			}
		}

		return $ret;
	}
	
	/**
	 * is the given currency editable
	 *
	 * @param  string $currency
	 * @return boolean
	 */
	public static function canEditCurrency($currency) {
		foreach (self::getCurrenciesConfig() as $currencyConfig) {
			if ($currencyConfig['currency'] === $currency) {
				return !Billrun_Util::getIn($currencyConfig, 'auto_sync', true);
			}
		}

		return false;
	}

	/**
	 * get customer's (account's) currency
	 * 
	 * @param  array $customer
	 * @return string currency
	 */
	public static function getCustomerCurrency($customer) {
		return !empty($customer['currency']) ? $customer['currency'] : self::getDefaultCurrency();
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
	public static function convert($baseCurrency, $targetCurrency, $amount, $time = null) {
		$converter = self::getConverter();
		return $converter->convert($baseCurrency, $targetCurrency, $amount, $time);
	}
		   
	public static function getPrice($targetCurrency, Billrun_Rate_Step $step, $params = []) {
		$converter = self::getConverter($params);
		return $converter->getPrice($targetCurrency, $step);
	}
		   
	public static function getPriceForCustomer($customer, Billrun_Rate_Step $step, $params = []) {
		$converter = self::getConverter($params);
		return $converter->getPriceForCustomer($customer, $step);
	}

	protected static function getConverter($params = []) {
		$baseClass = 'Billrun_CurrencyConvert';
		$entityType = $params['type'] ?? 'base';
		$converterClass = "{$baseClass}_" . ucfirst($entityType);
		if (!class_exists($converterClass)) {
			$converterClass = "{$baseClass}_Base";
		}		 
		
		return new $converterClass($params);
	}
}
