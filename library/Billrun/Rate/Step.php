<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing rate's step
 *
 * @package  Rate
 * @since    5.12
 */
class Billrun_Rate_Step {
	
	/**
	 * previous step
	 *
	 * @var Billrun_Rate_Step
	 */
	protected $prevStep = null;
	
	/**
	 * Step's data
	 *
	 * @var array
	 */
	protected $data = null;

	public function __construct(array $step, Billrun_Rate_Step $prevStep = null) {
		if (empty($step)) {
			return;
		}

		if (!isset($step['from'])) {
			$step['from'] = !is_null($prevStep) ? ($prevStep->get('to', 0)) : 0;
		}
		
		$this->data = $step;
		$this->prevStep = $prevStep;
	}
	
	/**
	 * is the step valid
	 *
	 * @return boolean
	 */
	public function isValid() {
		return is_array($this->data) && !empty($this->data);
	}
        
    /**
     * get charge value of the given $volume inside the current step
	 * in the default currency, and (if given) in other currency also
     *
     * @param  float $volume
     * @param  string $currency
     * @return array
     */
    public function getChargeValue($volume, $currency = null) {
		$ceil = $this->get('ceil', true);
		$toCharge = $volume / $this->get('interval');
		
		if ($ceil) {
			$toCharge = ceil($toCharge);
		}
	
		$ret = [
			Billrun_CurrencyConvert_Manager::getDefaultCurrency() => floatval($toCharge * $this->getPrice()),
		];
		
		if (!empty($currency)) {
			$ret[$currency] = floatval($toCharge * $this->getPrice($currency));
		}
		
		return $ret;
	}
	
	/**
	 * get step's price in given currency if received, otherwise in default currency
	 *
	 * @param  string $currency
	 * @return float
	 */
	public function getPrice($currency = null) {
		if (empty($currency)) {
			return $this->get('price');
		}

		return Billrun_CurrencyConvert_Manager::getPrice($currency, $this);
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
	 * get Step's data
	 *
	 * @return array
	 */
	public function getData() {
		return $this->data;
	}
	
	/**
	 * get previous step
	 *
	 * @return Billrun_Step
	 */
	public function getPrevStep() {
		return $this->prevStep;
	}
}
