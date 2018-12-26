<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the rates
 *
 * @package  Util
 * @since    5.1
 */
class Billrun_Rates_Util {
	
	protected static $currencyList;

	/**
	 * Get a rate by reference
	 * @param type $rate_ref
	 * @return type
	 */
	public static function getRateByRef($rate_ref) {
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rate = $rates_coll->getRef($rate_ref);
		return $rate;
	}

	/**
	 * Determines if a rate should not produce billable lines, but only counts the usage
	 * @param Mongodloid_Entity|array $rate the input rate
	 * @return boolean
	 */
	public static function isBillable($rate) {
		return !isset($rate['billable']) || $rate['billable'] === TRUE;
	}

	/**
	 * get rate interconnect
	 * @param type $rate
	 * @param type $usage_type
	 * @param type $plan
	 * @return boolean
	 * @deprecated since version 5.0
	 */
	public static function getInterconnect($rate, $usage_type, $plan) {
		Billrun_Factory::log("Use of deprecated method", Zend_Log::NOTICE);
		if (isset($rate['rates'][$usage_type][$plan]['interconnect'])) {
			return $rate['rates'][$usage_type][$plan]['interconnect'];
		}
		if (isset($rate['rates'][$usage_type]['BASE']['interconnect'])) {
			return $rate['rates'][$usage_type]['BASE']['interconnect'];
		}

		if (isset($rate['rates'][$usage_type]['interconnect'])) {
			return $rate['rates'][$usage_type]['interconnect'];
		}

		Billrun_Factory::log("Interconnect not found ", Zend_Log::DEBUG);
		return false;
	}

	public static function getTariff($rate, $usage_type, $planName = null, $services = array(), $time = null) {
		foreach ($services as $service) {
			$rates = $service->get('rates');
			if (isset($rates[$rate['key']], $rates[$rate['key']][$usage_type])) {
				return $rates[$rate['key']][$usage_type];
			}
		}
		if (is_null($time)) {
			$time = time();
		}
		if (!is_null($planName)) {
			$plan = Billrun_Factory::plan(array('name' => $planName, 'time' => $time));
		}
		if (isset($plan) && $plan instanceof Billrun_Plan && ($rates = $plan->get('rates')) &&
			isset($rates[$rate['key']]) && isset($rates[$rate['key']][$usage_type])) {
			return $rates[$rate['key']][$usage_type];
		}
		if (!is_null($planName) && isset($rate['rates'][$usage_type][$planName])) {
			return $rate['rates'][$usage_type][$planName];
		}
		if (isset($rate['rates'][$usage_type]['BASE'])) {
			return $rate['rates'][$usage_type]['BASE'];
		}
		return $rate['rates'][$usage_type];
	}

	/**
	 * Calculates the charges for the given volume
	 * 
	 * @param array $rate the rate entry
	 * @param string $usageType the usage type
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes of data)
	 * @param object $plan The plan the line is associate to
	 * @param int $offset call start offset in seconds
	 * @param int $time start of the call (unix timestamp)
	 * @todo : changed mms behavior as soon as we will add mms to rates
	 * 
	 * @return array the calculated charges
	 */
	public static function getCharges($rate, $usageType, $volume, $plan = null, $services = array(), $offset = 0, $time = NULL) {
		$tariff = static::getTariff($rate, $usageType, $plan, $services, $time);
		$pricingMethod = $rate['pricing_method'];
		if ($offset) {
			$chargeWoIC = Billrun_Tariff_Util::getChargeByVolume($tariff, $offset + $volume, $pricingMethod) - Billrun_Tariff_Util::getChargeByVolume($tariff, $offset, $pricingMethod);
		} else {
			$chargeWoIC = Billrun_Tariff_Util::getChargeByVolume($tariff, $volume, $pricingMethod);
		}
		return array(
			'total' => $chargeWoIC,
		);
	}

	/**
	 * Calculates the price for the given volume (w/o access price)
	 * @param array $rate the rate entry
	 * @param string $usageType the usage type
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes  of data)
	 * @return int the calculated price
	 */
	public static function getPrice($rate, $usageType, $volume) {
		// TODO: Use the tariff util module, it has the same function.
		$rates_arr = $rate['rates'][$usageType]['rate'];
		$price = 0;
		foreach ($rates_arr as $currRate) {
			if (0 == $volume) { // volume could be negative if it's a refund amount
				break;
			}//break if no volume left to price.
			//
			// get the volume that needed to be priced for the current rating
			$volumeToPriceCurrentRating = ($volume - $currRate['to'] < 0) ? $volume : $currRate['to'];
			if (isset($currRate['ceil'])) {
				$ceil = $currRate['ceil'];
			} else {
				$ceil = true;
			}
			if ($ceil) {
				// actually price the usage volume by the current 	
				$price += floatval(ceil($volumeToPriceCurrentRating / $currRate['interval']) * $currRate['price']);
			} else {
				// actually price the usage volume by the current 
				$price += floatval($volumeToPriceCurrentRating / $currRate['interval'] * $currRate['price']);
			}
			// decrease the volume that was priced
			$volume = $volume - $volumeToPriceCurrentRating;
		}
		return $price;
	}

	public static function getTotalCharge($rate, $usageType, $volume, $plan = null, $services = array(), $offset = 0, $time = NULL) {
		return static::getCharges($rate, $usageType, $volume, $plan, $services, $offset, $time)['total'];
	}

	// TODO: This is a temporary function
	public static function getVat($default = 0.18) {
		return Billrun_Factory::config()->getConfigValue('taxation.vat', $default);
	}

	/**
	 * 
	 * @param type $rate
	 * @param type $usageType
	 * @param type $volume
	 * @param type $plan
	 * @param array $services
	 * @param type $offset
	 * @param type $time
	 * @return type
	 * 
	 * @todo move to rate class or rates util
	 */
	public static function getTotalChargeByRate($rate, $usageType, $volume, $plan = null, $services = array(), $offset = 0, $time = NULL) {
		return static::getChargesByRate($rate, $usageType, $volume, $plan, $services, $offset, $time)['total'];
	}

	/**
	 * Calculates the charges for the given volume
	 * 
	 * @param array $rate the rate entry
	 * @param string $usageType the usage type
	 * @param int $volume The usage volume (seconds of call, count of SMS, bytes of data)
	 * @param object $plan The plan the line is associate to
	 * @param array $services The services associated with the line
	 * @param int $offset call start offset in seconds
	 * @param int $time start of the call (unix timestamp)
	 * @todo : changed mms behavior as soon as we will add mms to rates
	 * 
	 * @return array the calculated charges
	 */
	public static function getChargesByRate($rate, $usageType, $volume, $plan = null, $services = array(), $offset = 0, $time = NULL) {
		$tariff = Billrun_Rates_Util::getTariff($rate, $usageType, $plan, $services, $time);
		$pricingMethod = $rate['pricing_method'];
		if ($offset) {
			$chargeWoIC = Billrun_Tariff_Util::getChargeByVolume($tariff, $offset + $volume, $pricingMethod) - Billrun_Tariff_Util::getChargeByVolume($tariff, $offset, $pricingMethod);
		} else {
			$chargeWoIC = Billrun_Tariff_Util::getChargeByVolume($tariff, $volume, $pricingMethod);
		}
		return array(
			'total' => $chargeWoIC,
		);
	}

	/**
	 * Calculates the volume for the given price
	 * used on prepaid (realtime) when billing engine compute usage volume
	 * 
	 * @param array $rate the rate entry
	 * @param string $usage_type the usage type
	 * @param int $price The price
	 * @param object $plan The plan the line is associate to
	 * @param array $services The services associated with the line
	 * @param int $offset call start offset in seconds
	 * @param int $min_balance_cost minimum balance cost
	 * @param int $min_balance_volume minimum balance volume
	 * 
	 * @return int the calculated volume
	 * 
	 */
	public static function getVolumeByRate($rate, $usage_type, $price, $plan = null, $services = array(), $offset = 0, $min_balance_cost = 0, $min_balance_volume = 0, $time = null, $maxUsage = null) {
		// Check if the price is enough for default usagev
		if (is_null($maxUsage)) {
			$defaultUsage = (float) Billrun_Factory::config()->getConfigValue('rates.prepaid_granted.' . $usage_type . '.usagev', 100, 'float'); // float avoid set type to int
		} else {
			$defaultUsage = $maxUsage;
		}
		$defaultUsagePrice = static::getTotalChargeByRate($rate, $usage_type, $defaultUsage, $plan, $services, $offset, $time);
		if ($price >= $defaultUsagePrice) {
			return $defaultUsage;
		}

		// Check if the price is enough for minimum cost
		if ($price < $min_balance_cost) {
			return 0;
		}

		// Let's find the best volume by lion in the desert algorithm
		$previousUsage = $defaultUsage;
		$currentUsage = $defaultUsage - (abs($defaultUsage - $min_balance_volume) / 2);
		$epsilon = Billrun_Factory::config()->getConfigValue('rates.getVolumeByRate.epsilon', 0.000001);
		$limitLoop = Billrun_Factory::config()->getConfigValue('rates.getVolumeByRate.limitLoop', 50);
		while (abs($currentUsage - $previousUsage) > $epsilon && $limitLoop-- > 0) {
			$currentPrice = static::getTotalChargeByRate($rate, $usage_type, $currentUsage, $plan, $services, $offset, $time);
			$diff = abs($currentUsage - $previousUsage) / 2;
			if ($price < $currentPrice) {
				$previousUsage = $currentUsage;
				$currentUsage -= $diff;
			} else {
				$previousUsage = $currentUsage;
				$currentUsage += $diff;
			}
		}

		// Check if the price is enough for minimum cost
		if ($currentPrice >= $min_balance_cost) {
			return floor(round($currentUsage, $epsilon));
		}
		return 0;
	}
	/**
	 * Calculates the volume granted for subscriber by rate and balance
	 * @param type $row
	 * @param type $rate
	 * @param type $balance
	 * @param type $usageType
	 * @param type $balanceTotalKeys
	 * @param int $callOffset call start offset in seconds
	 * @param int $min_balance_cost minimum balance cost
	 * @param int $min_balance_volume minimum balance volume
	 */
	public static function getPrepaidGrantedVolume($row, $rate, $balance, $usageType, $balanceTotalKeys = null, $callOffset = 0, $min_balance_cost = 0, $min_balance_volume = 0, $time = null) {
		if (empty($balanceTotalKeys)) {
			$balanceTotalKeys = $usageType;
		}
		if (isset($row['api_name']) && $row['api_name'] == 'release_call') {
			return 0;
		}
		$requestedVolume = PHP_INT_MAX;
		if (isset($row['usagev'])) {
			$requestedVolume = $row['usagev'];
		}
		if ((isset($row['billrun_pretend']) && $row['billrun_pretend']) ||
			(isset($row['free_call']) && $row['free_call'])) {
			return 0;
		}
		$maximumGrantedVolume = self::getPrepaidGrantedVolumeByRate($rate, $usageType, $row['plan'], $callOffset, $min_balance_cost, $min_balance_volume, $time, $requestedVolume);
		$rowInOrOutOfBalanceKey = 'in';
		if (isset($balance->get("balance")["totals"][$balanceTotalKeys]["usagev"])) {
			$currentBalanceVolume = $balance->get("balance")["totals"][$balanceTotalKeys]["usagev"];
		} else {
			if (isset($balance->get("balance")["totals"][$balanceTotalKeys]["cost"])) {
				$price = $balance->get("balance")["totals"][$balanceTotalKeys]["cost"];
			} else {
				$price = $balance->get("balance")["cost"];
				$rowInOrOutOfBalanceKey = 'out';
			}
			$currentBalanceVolume = Billrun_Rates_Util::getVolumeByRate($rate, $usageType, abs($price), $row['plan'], array(), $callOffset, $min_balance_cost, $min_balance_volume, $time, $requestedVolume); // TODO pass the correct subscriber services
		}
		$currentBalanceVolume = abs($currentBalanceVolume);
		$usagev = min(array($currentBalanceVolume, $maximumGrantedVolume, $requestedVolume));
		$row[$rowInOrOutOfBalanceKey . '_balance_usage'] = $usagev;
		return $usagev;
	}

	/**
	 * Gets the maximum allowed granted volume for rate
	 * @param type $rate
	 * @param type $usageType
	 * @param type $planName
	 * @param int $callOffset call start offset in seconds
	 * @param int $min_balance_cost minimum balance cost
	 * @param int $min_balance_volume minimum balance volume
	 */
	public static function getPrepaidGrantedVolumeByRate($rate, $usageType, $planName, $callOffset = 0, $min_balance_cost = 0, $min_balance_volume = 0, $time = null, $maxUsage = null) {
		if (isset($rate["rates"][$usageType]["prepaid_granted_usagev"])) {
			return $rate["rates"][$usageType]["prepaid_granted_usagev"];
		}
		if (isset($rate["rates"][$usageType]["prepaid_granted_cost"])) {
			return Billrun_Rates_Util::getVolumeByRate($rate, $usageType, $rate["rates"][$usageType]["prepaid_granted_cost"], $planName, array(), $callOffset, $min_balance_cost, $min_balance_volume, $time, $maxUsage); // TODO pass the correct subscriber services
		}

		return Billrun_Rates_Util::getVolumeByRate($rate, $usageType, Billrun_Factory::config()->getConfigValue("rates.prepaid_granted.$usageType.cost", 5), $planName, array(), $callOffset, $min_balance_cost, $min_balance_volume, $time, $maxUsage); // TODO pass the correct subscriber services
	}

	/**
	 * method to get currency symbol by the currency code (eur, gbp, usd, ils, etc)
	 * 
	 * @param string $currency the currency to retrieve
	 * 
	 * @return mixed on success return the symbol else false
	 */
	public static function getCurrencySymbol($currency) {
		try {
			if (empty(self::$currencyList)) {
				self::$currencyList = Zend_Locale::getTranslationList('currencysymbol');
			}
			if (isset(self::$currencyList[$currency])) {
				return self::$currencyList[$currency];
			}
		} catch (Exception $ex) {
			Billrun_Factory::log($ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::WARN);
		}
		return false;
	}

	public static  function getRateByName($rateName, $timestamp = null) {
		$timestamp = empty($timestamp) ? time() : $timestamp;
		$rates_coll = Billrun_Factory::db()->ratesCollection();
		$rate = $rates_coll->query(array_merge(array('key'=>$rateName), Billrun_Utils_Mongo::getDateBoundQuery($timestamp)))->cursor()->limit(1)->current();
		return $rate;
	}

}
