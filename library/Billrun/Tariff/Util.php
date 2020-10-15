<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the tariffs
 *
 * @package  Util
 * @since    5.1
 */
class Billrun_Tariff_Util {
	
	const PRICING_METHOD_TIERED = 'tiered';
	const PRICING_METHOD_VOLUME = 'volume';

	/**
	 * Gets correct access price from tariff
	 * @param array $tariff the tariff structure
	 * @return float Access price
	 * @deprecated since version 5.12 - use Billrun_Rate instead
	 */
	public static function getAccessPrice($tariff) {
		if (isset($tariff['access'])) {
			return $tariff['access'];
		}
		return 0;
	}

	/**
	 * @deprecated since version 5.12 - use Billrun_Rate instead
	 */
	public static function getChargeByVolume($tariff, $volume, $pricingMethod = null) {
		if (is_null($pricingMethod)) {
			$pricingMethod = self::PRICING_METHOD_TIERED;
		}
		$accessPrice = self::getAccessPrice($tariff);
		if ($volume < 0) {
			$volume *= (-1);
			$isNegative = true;
		} else {
			$isNegative = false;
		}
		$price = static::getChargeByTariffRatesAndVolume($tariff['rate'], $volume, $pricingMethod);
		$ret = $accessPrice + $price;
		return ($isNegative ? $ret * (-1) : $ret);
	}

	/**
	 * @deprecated since version 5.12 - use Billrun_Rate instead
	 */
	public static function getChargeByTariffRatesAndVolume($tariffs, $volume, $pricingMethod = null) {
		if (is_null($pricingMethod)) {
			$pricingMethod = self::PRICING_METHOD_TIERED;
		}
		$charge = 0;
		$lastRate = array();
		$volumeCount = $volume;
		foreach ($tariffs as $currRate) {
			// Check that it is an array.
			// TODO: Use a rate class.
			if (!is_array($currRate)) {
				Billrun_Factory::log("Invalid rate in tariff utils. " . print_r($currRate, 1), Zend_Log::WARN);
				continue;
			}

			$rate = self::buildRate($currRate, $lastRate);
			$lastRate = $rate;
			// volume could be negative if it's a refund amount
			if (0 == $volumeCount) {
				//break if no volume left to price.
				break;
			}

			$volumeCount = self::handleChargeAndVolume($volumeCount, $charge, $rate, $pricingMethod);
		}
		return $pricingMethod === self::PRICING_METHOD_TIERED ? $charge : self::getChargeValueForRateStep($volume, $lastRate);
	}

	public static function getIntervalCeiling($tariff, $volume) {
		$charge = 0;
		$lastRate = array();
		$volumeCount = $volume;
		foreach ($tariff['rate'] as $currRate) {
			// Check that it is an array.
			// TODO: Use a rate class.
			if (!is_array($currRate)) {
				Billrun_Factory::log("Invalid rate in tariff utils." . print_r($currRate, 1), Zend_Log::WARN);
				continue;
			}

			$rate = self::buildRate($currRate, $lastRate);

			// volume could be negative if it's a refund amount
			if (0 == $volumeCount) {
				//break if no volume left to price.
				break;
			}

			// Force the ceiling
			$rate['ceil'] = true;
			$volumeCount = self::handleChargeAndVolume($volumeCount, $charge, $rate);
			$lastRate = $rate;
		}

		return $charge;
	}
	
	public static function getTariffForVolume($tariff,$volume) {
		foreach ($tariff['rate'] as $currRate) {
			if (!is_array($currRate)) {
				Billrun_Factory::log("Invalid rate in tariff utils." . print_r($currRate, 1), Zend_Log::WARN);
				continue;
			}
			if($volume > $currRate['to']) {
				continue;
			}
			return $currRate['price'];
		}
		Billrun_Factory::log("Invalid tariff :" . print_r($tariff, 1), Zend_Log::WARN);
		return 0;
	}

	/**
	 * Build a rate according to another rate
	 * @param array $rate - The rate to be built.
	 * @param array $other - Rate to build according to.
	 * @return array rate instance.
	 * @deprecated since version 5.12 - use Billrun_Rate instead
	 */
	protected static function buildRate($rate, $other) {
		if (isset($rate['rate'])) {
			return $rate['rate'];
		}

		$toReturn = $rate;
		if (!isset($rate['from'])) {
			$toReturn['from'] = isset($other['to']) ? $other['to'] : 0;
		}
		return $toReturn;
	}

	/**
	 * Handle the charge and volume values for the current step.
	 * @param int $volume - The current volume 
	 * @param int $charge - Reference to the current charge
	 * @param array $rate - The current rate.
	 * @return int Volume value after handling.
	 * @deprecated since version 5.12 - use Billrun_Rate instead
	 */
	protected static function handleChargeAndVolume($volume, &$charge, $rate, $pricingMethod) {
		$maxVolumeInRate = ($rate['to'] === Billrun_Service::UNLIMITED_VALUE ? PHP_INT_MAX : $rate['to']) - $rate['from'];

		// get the volume that needed to be priced for the current rating
		$volumeToPriceCurrentRating = ($volume < $maxVolumeInRate) ? $volume : $maxVolumeInRate;
		
		if ($pricingMethod === self::PRICING_METHOD_TIERED) {
			$charge += self::getChargeValueForRateStep($volumeToPriceCurrentRating, $rate);
		}

		//decrease the volume that was priced
		return $volume - $volumeToPriceCurrentRating;
	}
	
	/**
	 * Gets the charge value according to rate parameters, handles the "ceil" mechanism.
	 * 
	 * @param type $volume
	 * @param type $rate
	 * @return type
	 * @deprecated since version 5.12 - use Billrun_Rate instead
	 */
	protected static function getChargeValueForRateStep($volume, $rate) {
		$ceil = true;

		if (isset($rate['ceil'])) {
			$ceil = $rate['ceil'];
		}
		
		if ($ceil) {
			// actually price the usage volume by the current 	
			return floatval(ceil($volume / $rate['interval']) * $rate['price']);
		}
	
		// actually price the usage volume by the current 
		return floatval($volume / $rate['interval'] * $rate['price']);
	}

}
