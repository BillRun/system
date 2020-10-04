<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Exchange Rates plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class exchangeRatesPlugin extends Billrun_Plugin_Base {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'exchangeRates';

	public function cronHour() {
		if ($this->shouldUpdate('hourly')) {
			$this->updateExchangeRates();
		}
	}
	
	public function cronDay() {
		if ($this->shouldUpdate('daily')) {
			$this->updateExchangeRates();
		}
	}

	/**
	 * Should exchange rate update be done
	 *
	 * @param  string $period daily/hourly/...
	 * @return boolean
	 */
	protected function shouldUpdate($period) {
		return Billrun_CurrencyConvert_Manager::isMultiCurrencyEnabled() &&
			$this->getUpdatePeriod() == $period;
	}

	/**
	 * Get period on which we want to update the exchange rates
	 * 
	 * @return string daily/hourly/...
	 */
	protected function getUpdatePeriod() {
		return Billrun_Factory::config()->getConfigValue('exchange_rate.update_period', '');
	}

	/**
	* Get updated exchange rates from $baseCurrency to $targetCurrencie
	* 
	* @param string $baseCurrency currency from which the exchange rate will be taken
	* @param array $targetCurrencie currencies to which the exchage rate will be taken
	* @return array list of currencies and the exchange rate to them from the $baseCurrency
	 */
	public function getExchangeRates($baseCurrency, $targetCurrencies = []) {
		$url = Billrun_Factory::config()->getConfigValue('exchange_rate.url', '');
		$method = Billrun_Factory::config()->getConfigValue('exchange_rate.method', Zend_Http_Client::POST);
		$apiKey = Billrun_Factory::config()->getConfigValue('exchange_rate.api_key', '');
		$data = [
			'base_currency' => $baseCurrency,
		];
		if (!empty($targetCurrencies)) {
			$data['to_currency'] = implode(',', $targetCurrencies);
		}
		$headers = [
			'Api-Key' => $apiKey,
		];
		$rates = @json_decode(Billrun_Util::sendRequest($url, $data, $method, $headers), JSON_OBJECT_AS_ARRAY);
		
		if (empty($rates)) {
			return [];
		}
		
		return !empty($rates['status']) ? $rates['details'] : [];
	}

	/**
	 * Update exchange rates in the system
	 */
	public function updateExchangeRates() {
		$baseCurrency = Billrun_CurrencyConvert_Manager::getDefaultCurrency();
		$targetCurrencies = Billrun_CurrencyConvert_Manager::getAvailableCurrencies();
		if (empty($targetCurrencies)) {
			return;
		}
		
		$exchangeRates = $this->getExchangeRates($baseCurrency, $targetCurrencies);
		$time = time();

		foreach ($exchangeRates as $exchangeRate) {
			$exchangeRateEntity = new Billrun_ExchangeRate($exchangeRate['base_currency'], $exchangeRate['to_currency'], $exchangeRate['rate'], $time);
			$exchangeRateEntity->save();
		}
	}

}
