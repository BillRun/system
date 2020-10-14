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
	 * @param  mixed $rate
	 * @return void
	 */
	public function getPriceForCustomer($customer, $rate) {
		$targetCurrency = Billrun_CurrencyConvert_Manager::getCustomerCurrency($customer);
		if ($targetCurrency === false) {
			Billrun_Factory::log("Failed to get currency for customer: " . print_R($customer, 1), Billrun_Log::ERR);
			return false;
		}
		
		return $this->getPrice($targetCurrency, $rate);
	}

	public function getPrice($targetCurrency, $rate) {
		$price = $this->getBasePrice($rate);
		
		if ($targetCurrency === $this->baseCurrency) {
			return $price;
		}

		$overrideCurrencyPrice = $this->getOverrideCurrencyPrice($rate, $price);
		if ($overrideCurrencyPrice !== false) {
			return $overrideCurrencyPrice;
		}

		$convertedPrice = Billrun_CurrencyConvert_Manager::getInstance()->convert($this->baseCurrency, $targetCurrency, $price);
		if ($convertedPrice === false) {
			Billrun_Factory::log("Failed to convert currency from {$targetCurrency}) on rate: " . print_R($rate, 1), Billrun_Log::ERR);
			return false;
		}

		return $convertedPrice;
	}

	protected function getBasePrice($rate) {
		return $rate['price'];
	}

	protected function getOverrideCurrencyPrice($rate, $price) {
		foreach ($rate['currency_rates'] ?? [] as $currencyRate) {
			if ($currencyRate['currency'] === $this->targetCurrency) {
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
