<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing rate's tariff
 *
 * @package  Rate
 * @since    5.12
 */
class Billrun_Rate_Tariff {

	protected $data = null;

	protected $pricingMethod;

	public function __construct(array $tariff, $pricingMethod = Billrun_Rate::PRICING_METHOD_TIERED) {
		$this->data = $tariff;
		$this->pricingMethod = $pricingMethod;
	}
	
	public function isValid() {
		return is_array($this->data) && !empty($this->data);
	}
	
	/**
	 * get Tariff instance by given parameters
	 *
	 * @param  Billrun_Rate $rate
	 * @param  string $usageType
	 * @param  array $params
	 * @return Billrun_Rate_Tariff
	 */
	public static function getInstance($rate, $usageType, $params = []) {
		$tariff = self::getTariff($rate, $usageType, $params);
		return new self($tariff, $rate->getPricingMethod());
	}
		
	/**
	 * get tariff data by given parameters
	 *
	 * @param  Billrun_Rate $rate
	 * @param  string $usageType
	 * @param  array $params
	 * @return array
	 */
	protected static function getTariff($rate, $usageType, $params = []) {
		$key = $rate->get('key');

		if (!empty($params['services'])) {
			foreach ($params['services'] as $service) {
				$rates = $service->get('rates');
				if (isset($rates[$key], $rates[$key][$usageType])) {
					return $rates[$key][$usageType];
				}
			}
		}

		$planName = $params['plan_name'] ?? '';
		if (!empty($planName)) {
			$time = $params['time'] ?? time();
			$plan = Billrun_Factory::plan(['name' => $planName, 'time' => $time]);
			
			if ($plan && $plan instanceof Billrun_Plan && ($rates = $plan->get('rates')) &&
				isset($rates[$key]) && isset($rates[$key][$usageType])) {
				return $rates[$key][$usageType];
			}	
		}
		
		$rates = $rate->get('rates', []);
		if (isset($rates[$usageType][$planName])) {
			return $rates[$usageType][$planName];
		}

		if (isset($rates[$usageType]['BASE'])) {
			return $rates[$usageType]['BASE'];
		}
		
		return $rates[$usageType];
	}

	public function getChargeByVolume($volume) {
		$accessPrice = $this->getAccessPrice();
		if ($volume < 0) {
			$volume *= (-1);
			$isNegative = true;
		} else {
			$isNegative = false;
		}
		
		$price = $this->getCharge($volume);
		$ret = $accessPrice + $price;
		return ($isNegative ? $ret * (-1) : $ret);
    }
    
    public function getAccessPrice() {
		return $this->get('access', 0);
    }
    
    public function getCharge($volume) {
		$steps = $this->get('rate', []);
		if (empty($steps)) {
			$a = 1;
		}
		
		$charge = 0;
		$lastStep = null;
		$volumeCount = $volume;
		foreach ($steps as $currStep) {
			$step = new Billrun_Rate_Step($currStep, $lastStep);
			if (!$step->isValid()) {
				Billrun_Factory::log("Invalid rate step. " . print_r($currStep, 1), Zend_Log::WARN);
				continue;
			}

			$lastStep = $step;
			
			// volume could be negative if it's a refund amount
			if (0 == $volumeCount) {
				//break if no volume left to price.
				break;
			}

			$volumeCount = $this->handleChargeAndVolume($volumeCount, $charge, $step);
		}
		
		return $this->pricingMethod === Billrun_Rate::PRICING_METHOD_TIERED ? $charge : $lastStep->getChargeValue($volume);
	}
	
	protected function handleChargeAndVolume($volume, &$charge, $step) {
		$maxVolumeInRate = ($step->get('to') === Billrun_Service::UNLIMITED_VALUE ? PHP_INT_MAX : $step->get('to')) - $step->get('from');

		// get the volume that needed to be priced for the current rating
		$volumeToPriceCurrentRating = ($volume < $maxVolumeInRate) ? $volume : $maxVolumeInRate;
		
		if ($this->pricingMethod === Billrun_Rate::PRICING_METHOD_TIERED) {
			$charge += $step->getChargeValue($volumeToPriceCurrentRating);
		}

		//decrease the volume that was priced
		return $volume - $volumeToPriceCurrentRating;
    }
	
	/**
	 * get entity field's value
	 *
	 * @param  string $prop
	 * @param  mixed $default
	 * @return mixed
	 */
	public function get($prop, $default = null) {
		return $this->data[$prop] ?? $default;
	}

	public function getData() {
		return $this->data;
	}
}
