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
	
	/**
	 * Tariff data
	 *
	 * @var array
	 */
	protected $data = null;
	
	/**
	 * pricing method - one of tier/volume
	 *
	 * @var string
	 */
	protected $pricingMethod;
	
	/**
	 * percentages from original rate
	 *
	 * @var float
	 */
	protected $percentage = 1;
	
	/**
	 * parent rate of the tariff
	 *
	 * @var Billrun_Rate
	 */
	protected $rate;

	protected $params = [];
	
	/**
	 * plan name
	 *
	 * @var string
	 */
	protected $planName;

	public function __construct($rate, $usageType, $params = []) {
		$this->params = $params;
		$this->load($rate, $usageType, $params);
	}
		
	/**
	 * is the tariff alid
	 *
	 * @return boolean
	 */
	public function isValid() {
		return is_array($this->data) && !empty($this->data);
	}
	
	/**
	 * load Tariff data
	 *
	 * @param  Billrun_Rate $rate
	 * @param  string $usageType
	 * @param  array $params
	 */
	protected function load($rate, $usageType, $params = []) {
		$this->rate = $rate;
		$this->data = $this->getTariff($usageType, $params);
		$this->pricingMethod = $rate->getPricingMethod();
	}
		
	/**
	 * get tariff data by given parameters
	 *
	 * @param  string $usageType
	 * @param  array $params
	 * @return array
	 */
	protected function getTariff($usageType, $params = []) {
		$key = $this->rate->get('key');
		$this->planName = $params['plan_name'] ?? '';

		if (!empty($params['services'])) {
			foreach ($params['services'] as $service) {
				$rates = $service->get('rates');
				$tariff = $this->getOverriddenTariff($rates, $usageType);
				if ($tariff !== false) {
					return $tariff;
				}
			}
		}

		if (!empty($this->planName) && $this->planName !== 'BASE') {
			$time = $params['time'] ?? time();
			$plan = Billrun_Factory::plan(['name' => $this->planName, 'time' => $time]);
			
			if ($plan && $plan instanceof Billrun_Plan && ($rates = $plan->get('rates'))) {
				$tariff = $this->getOverriddenTariff($rates, $usageType);
				if ($tariff !== false) {
					return $tariff;
				}
			}
		}
		
		$rates = $this->rate->get('rates', []);
		if (isset($rates[$usageType][$this->planName])) {
			return $rates[$usageType][$this->planName];
		}

		if (isset($rates[$usageType]['BASE'])) {
			return $rates[$usageType]['BASE'];
		}
		
		return $rates[$usageType];
	}
	
	/**
	 * get the tariff in case it is overridden by one of the $rates given
	 *
	 * @param  array $rates
	 * @param  string $usageType
	 * @return array of tariff in case it is overridden, false otherwise
	 */
	protected function getOverriddenTariff($rates, $usageType) {
		$key = $this->rate->get('key');
		
		if (empty($rates) || !isset($rates[$key], $rates[$key][$usageType])) {
			return false;
		}
			
		$tariff = $rates[$key][$usageType];
		if (isset($tariff['percentage'])) {
			$this->percentage = floatval($tariff['percentage']);
			$this->planName = 'BASE'; // price was already overridden by plan/service based on percentages so no need to check if it was overridden on the rate
			return false;
		}

		return $tariff;
	}
	
	/**
	 * get the price to charge accodring to the volume
	 *
	 * @param  float $volume
	 * @return float
	 */
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
        
    /**
     * get tariff's access price
     *
     * @return float
     */
    public function getAccessPrice() {
		$price = $this->get('access', 0);
		$currency = $params['currency'] ?? '';
		if (empty($currency) || empty($price)) {
			return $price;
		}

		return Billrun_CurrencyConvert_Manager::convert(Billrun_CurrencyConvert_Manager::getDefaultCurrency(), $currency, $price);
    }
        
    /**
     * get the amount to charge by the given volume
     *
     * @param  float $volume
     * @return float
     */
    public function getCharge($volume) {
		$steps = $this->get('rate', []);
		$charge = 0;
		$lastStep = null;
		$volumeCount = $volume;
		foreach ($steps as $currStep) {
			$step = new Billrun_Rate_Step($currStep, $lastStep, $this->params);
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
		
	/**
	 * get the volume to use in the given $step
	 *
	 * @param  float $volume
	 * @param  float $charge
	 * @param  Billrun_Rate_Step $step
	 * @return float
	 */
	protected function handleChargeAndVolume($volume, &$charge, $step) {
		$maxVolumeInRate = ($step->get('to') === Billrun_Service::UNLIMITED_VALUE ? PHP_INT_MAX : $step->get('to')) - $step->get('from');

		// get the volume that needed to be priced for the current rating
		$volumeToPriceCurrentRating = ($volume < $maxVolumeInRate) ? $volume : $maxVolumeInRate;
		
		if ($this->pricingMethod === Billrun_Rate::PRICING_METHOD_TIERED) {
			$charge += $step->getChargeValue($volumeToPriceCurrentRating);
		}

		// decrease the volume that was priced
		return $volume - $volumeToPriceCurrentRating;
	}
		
	/**
	 * get percentages to apply on the tariff
	 *
	 * @return float
	 */
	public function getPercentage() {
		return $this->percentage;
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
	
	/**
	 * get Tariff's data
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}
}
