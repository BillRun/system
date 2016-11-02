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

	/**
	 * Gets correct access price from tariff
	 * @param array $tariff the tariff structure
	 * @return float Access price
	 */
	public static function getAccessPrice($tariff) {
		if (isset($tariff['access'])) {
			return $tariff['access'];
		}
		return 0;
	}

	public static function getChargeByVolume($tariff, $volume) {
		$accessPrice = self::getAccessPrice($tariff);
		if ($volume < 0) {
			$volume *= (-1);
			$isNegative = true;
		} else {
			$isNegative = false;
		}
		$price = static::getChargeByTariffRatesAndVolume($tariff['rate'], $volume);
		$ret = $accessPrice + $price;
		return ($isNegative ? $ret * (-1) : $ret);
	}

	public static function getChargeByTariffRatesAndVolume($tariffs, $volume) {
		if(!$tariffs) {
            Billrun_Factory::log("Empty tariff array");
            return 0;
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

			// volume could be negative if it's a refund amount
			if (0 == $volumeCount) {
				//break if no volume left to price.
				break;
			}

			$volumeCount = self::handleChargeAndVolume($volumeCount, $charge, $rate);
			$lastRate = $rate;
		}
		return $charge;
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

	/**
	 * Build a rate according to another rate
	 * @param array $rate - The rate to be built.
	 * @param array $other - Rate to build according to.
	 * @return array rate instance.
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
	 */
	protected static function handleChargeAndVolume($volume, &$charge, $rate) {
		$maxVolumeInRate = $rate['to'] - $rate['from'];

		// get the volume that needed to be priced for the current rating
		$volumeToPriceCurrentRating = ($volume < $maxVolumeInRate) ? $volume : $maxVolumeInRate;

		$ceil = true;
		if (isset($rate['ceil'])) {
			$ceil = $rate['ceil'];
		}

		if ($ceil) {
			// actually price the usage volume by the current 	
			$charge += floatval(ceil($volumeToPriceCurrentRating / $rate['interval']) * $rate['price']);
		} else {
			// actually price the usage volume by the current 
			$charge += floatval($volumeToPriceCurrentRating / $rate['interval'] * $rate['price']);
		}

		//decrease the volume that was priced
		return $volume - $volumeToPriceCurrentRating;
	}

}
