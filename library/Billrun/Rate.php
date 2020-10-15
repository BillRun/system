<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing rate (product) class
 *
 * @package  Rate
 * @since    5.12
 */
class Billrun_Rate extends Billrun_Entity {

    const PRICING_METHOD_TIERED = 'tiered';
	const PRICING_METHOD_VOLUME = 'volume';

    /**
     * see parent::getCollection
     */
    public static function getCollection() {
        return Billrun_Factory::db()->ratesCollection();
    }
	
	/**
     * see parent::getLoadQueryByParams
     */
	protected function getLoadQueryByParams($params = []) {
        if (isset($params['key'])) {
            return [
                'key' => $params['key'],
            ];
        }

        return false;
    }

    public function getTotalCharge($params = []) {//$usageType, $volume, $plan = null, $services = array(), $offset = 0, $time = NULL) {
		return $this->getCharges($rate, $usageType, $volume, $plan, $services, $offset, $time)['total'];
	}

    public function getCharges($usageType, $volume, $plan = null, $services = array(), $offset = 0, $time = NULL) {
        $rate = $this->getData();
        $tariff = $this->getTariff($usageType, $plan, $services, $time);
		$percentage = 1;
		
		// if $overrideByPercentage is true --> use the original rate and set the correct percentage
		if (array_keys($tariff)[0] === 'percentage') {
			if (isset($rate['rates'][$usageType]['BASE'])) {
				$percentage = array_values($tariff)[0];
				$tariff = $rate['rates'][$usageType]['BASE'];
			}
		}
		$pricingMethod = $rate['pricing_method'];
		if ($offset) {
			$chargeWoIC = $this->getChargeByVolume($tariff, $offset + $volume, $pricingMethod) - $this->getChargeByVolume($tariff, $offset, $pricingMethod);
		} else {
			$chargeWoIC = $this->getChargeByVolume($tariff, $volume, $pricingMethod);
		}
		$chargeWoIC *= $percentage;
		return array(
			'total' => $chargeWoIC,
		);
    }
    
    public function getTariff($usage_type, $planName = null, $services = array(), $time = null) {
        $rate = $this->getData();
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
    
    public function getChargeByVolume($tariff, $volume, $pricingMethod = null) {
		if (is_null($pricingMethod)) {
			$pricingMethod = self::PRICING_METHOD_TIERED;
		}
		$accessPrice = $this->getAccessPrice($tariff);
		if ($volume < 0) {
			$volume *= (-1);
			$isNegative = true;
		} else {
			$isNegative = false;
		}
		$price = $this->getChargeByTariffRatesAndVolume($tariff['rate'], $volume, $pricingMethod);
		$ret = $accessPrice + $price;
		return ($isNegative ? $ret * (-1) : $ret);
    }
    
    public function getAccessPrice($tariff) {
		if (isset($tariff['access'])) {
			return $tariff['access'];
		}
		return 0;
    }
    
    public function getChargeByTariffRatesAndVolume($tariffs, $volume, $pricingMethod = null) {
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

			$rate = $this->buildRate($currRate, $lastRate);
			$lastRate = $rate;
			// volume could be negative if it's a refund amount
			if (0 == $volumeCount) {
				//break if no volume left to price.
				break;
			}

			$volumeCount = $this->handleChargeAndVolume($volumeCount, $charge, $rate, $pricingMethod);
		}
		return $pricingMethod === self::PRICING_METHOD_TIERED ? $charge : $this->getChargeValueForRateStep($volume, $lastRate);
    }
    
    protected function handleChargeAndVolume($volume, &$charge, $rate, $pricingMethod) {
		$maxVolumeInRate = ($rate['to'] === Billrun_Service::UNLIMITED_VALUE ? PHP_INT_MAX : $rate['to']) - $rate['from'];

		// get the volume that needed to be priced for the current rating
		$volumeToPriceCurrentRating = ($volume < $maxVolumeInRate) ? $volume : $maxVolumeInRate;
		
		if ($pricingMethod === self::PRICING_METHOD_TIERED) {
			$charge += $this->getChargeValueForRateStep($volumeToPriceCurrentRating, $rate);
		}

		//decrease the volume that was priced
		return $volume - $volumeToPriceCurrentRating;
    }

    protected function buildRate($rate, $other) {
		if (isset($rate['rate'])) {
			return $rate['rate'];
		}

		$toReturn = $rate;
		if (!isset($rate['from'])) {
			$toReturn['from'] = isset($other['to']) ? $other['to'] : 0;
		}
		return $toReturn;
	}
    
    protected function getChargeValueForRateStep($volume, $rate) {
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
