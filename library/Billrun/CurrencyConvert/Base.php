<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a class handles currency conversions for entities
 */
class Billrun_CurrencyConvert_Base {
		
	/**
	 * general parameters
	 *
	 * @var array
	 */
	protected $params = [];
		
	/**
	 * source currency (default currency in the system)
	 *
	 * @var string
	 */
	protected $baseCurrency = false;

	public function __construct(array $params = []) {
		$this->baseCurrency = Billrun_CurrencyConvert_Manager::getDefaultCurrency();
		$this->params = $params;
	}
	
	/**
	 * get price for customer
	 *
	 * @param  mixed $customer
	 * @param  Billrun_Rate_Step $step
	 * @return void
	 */
	public function getPriceForCustomer($customer, Billrun_Rate_Step $step) {
		$targetCurrency = Billrun_CurrencyConvert_Manager::getCustomerCurrency($customer);
		if ($targetCurrency === false) {
			Billrun_Factory::log("Failed to get currency for customer: " . print_R($customer, 1), Billrun_Log::ERR);
			return false;
		}
		
		return $this->getPrice($targetCurrency, $step);
	}

	public function getPrice($targetCurrency, Billrun_Rate_Step $step) {
		$price = $step->get('price');
		
		if (!Billrun_CurrencyConvert_Manager::isMultiCurrencyEnabled() || $targetCurrency === $this->baseCurrency) {
			return $price;
		}

		$overrideCurrencyPrice = $this->getOverrideCurrencyPrice($step, $price, $targetCurrency);
		if ($overrideCurrencyPrice !== false) {
			return $overrideCurrencyPrice;
		}

		$convertedPrice = Billrun_CurrencyConvert_Manager::convert($this->baseCurrency, $targetCurrency, $price);
		if ($convertedPrice === false) {
			Billrun_Factory::log("Failed to convert currency from {$targetCurrency}) on rate step: " . print_R($step, 1), Billrun_Log::ERR);
			return false;
		}

		return $convertedPrice;
	}
	
	/**
	 * get price for currency if defined on the rate step
	 *
	 * @param  Billrun_Rate_Step $step
	 * @param  float $price
	 * @param  string $targetCurrency
	 * @return float converted price if set, false otherwise
	 */
	protected function getOverrideCurrencyPrice(Billrun_Rate_Step $step, $price, $targetCurrency) {
		foreach ($step->get('currency_rates', []) as $currencyRate) {
			if ($currencyRate['currency'] === $targetCurrency) {
				if (isset($currencyRate['value'])) {
					return floatval($currencyRate['value']);
				}

				if (isset($currencyRate['rate'])) {
					return floatval($currencyRate['rate']) * $price;
				}

				return false;
			}
		}

		return false;
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
