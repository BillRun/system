<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the balances
 *
 * @package  Util
 * @since    5.1
 */
class Billrun_Rates_Util {

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
	
	public static function getInterconnect($rate, $usage_type, $plan) {
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
	
	public static function getTariff($rate, $usage_type, $planName = null) {
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
	public static function getCharges($rate, $usageType, $volume, $plan = null, $offset = 0, $time = NULL) {
		if (!empty($interconnect = self::getInterConnect($rate, $usageType, $plan))) {
			$query = array_merge(
				array(
				'key' => $interconnect,
				'params.interconnect' => TRUE,
				), Billrun_Util::getDateBoundQuery($time)
			);
			$interconnectRate = Billrun_Factory::db()->ratesCollection()->query($query)->cursor()->limit(1)->current();
			$interconnectCharge = static::getTotalCharge($interconnectRate, $usageType, $volume, $plan, $offset, $time);
		} else {
			$interconnectCharge = 0;
		}

		$tariff = static::getTariff($rate, $usageType, $plan);
		if ($offset) {
			$chargeWoIC = Billrun_Tariff_Util::getChargeByVolume($tariff, $offset + $volume) - Billrun_Tariff_Util::getChargeByVolume($tariff, $offset);
		} else {
			$chargeWoIC = Billrun_Tariff_Util::getChargeByVolume($tariff, $volume);
		}
		if ($interconnectCharge && $interconnectRate && (!isset($interconnectRate['params']['chargable']) || $interconnectRate['params']['chargable'])) {
			$ret = array(
				'interconnect' => $interconnectCharge,
				'total' => $interconnectCharge + $chargeWoIC,
			);
		} else if (isset($rate['params']['interconnect'], $rate['params']['chargable']) && $rate['params']['interconnect'] && $rate['params']['chargable']) { // the rate charge is interconnect charge
			$total = $chargeWoIC + $interconnectCharge;
			$ret = array(
				'interconnect' => $total,
				'total' => $total,
			);
		} else {
			$ret = array(
				'interconnect' => $interconnectCharge,
				'total' => $chargeWoIC,
			);
		}
		return $ret;
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
	
	public static function getTotalCharge($rate, $usageType, $volume, $plan = null, $offset = 0, $time = NULL) {
		return static::getCharges($rate, $usageType, $volume, $plan, $offset, $time)['total'];
	}
}