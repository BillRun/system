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
		$charge = 0;
		$lastRate = array();
		foreach ($tariffs as $currRate) {
			$rate = self::buildRate($currRate, $lastRate);
			
			// volume could be negative if it's a refund amount
			if (0 == $volume) { 
				//break if no volume left to price.
				break;
			}
			
			self::handleChargeAndVolume($charge, $charge, $rate);
			$lastRate = $rate;
		}
		return $charge;
	}
	
	public static function getIntervalCeiling($tariff, $volume) {
		$charge = 0;
		$lastRate = array();
		foreach ($tariff['rate'] as $currRate) {
			$rate = self::buildRate($currRate, $lastRate);

			// volume could be negative if it's a refund amount
			if (0 == $volume) { 
				//break if no volume left to price.
				break;
			}

			// Force the ceiling
			$rate['ceil'] = true;
			self::handleChargeAndVolume($volume, $charge, $rate);
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
	 * @param int $volume - Reference to the current volume 
	 * @param int $charge - Reference to the current charge
	 * @param array $rate - The current rate.
	 */
	protected static function handleChargeAndVolume(&$volume, &$charge, $rate) {
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
		$volume -= $volumeToPriceCurrentRating; 
	}
}