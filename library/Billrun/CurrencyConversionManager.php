<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class handles Currency Conversions
 */
class Billrun_CurrencyConversionManager {

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
}
