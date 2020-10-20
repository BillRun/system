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
	
	/**
	 * general parameters
	 *
	 * @var array
	 */
	protected $params = [];

	/**
	 * store currency conversion done
	 *
	 * @var array
	 */
	protected $currencyConversion = [];

	public function __construct(array $step, Billrun_Rate_Step $prevStep = null, $params = []) {
		if (empty($step)) {
			return;
		}

		if (!isset($step['from'])) {
			$step['from'] = !is_null($prevStep) ? ($prevStep->get('to', 0)) : 0;
		}
		
		$this->data = $step;
		$this->prevStep = $prevStep;
		$this->params = $params;
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
     *
     * @param  float $volume
     * @return float
     */
    public function getChargeValue($volume) {
		$ceil = $this->get('ceil', true);
		$toCharge = $volume / $this->get('interval');
		
		if ($ceil) {
			$toCharge = ceil($toCharge);
		}
	
		return floatval($toCharge * $this->getPrice($this->params));
	}

	public function getPrice($params = []) {
		$price = $this->get('price');
		$currency = $params['currency'] ?? '';
		if (empty($currency)) {
			return $price;
		}

		$currencyConversion = [
			'type' => 'rate_step',
			'to_currency' => $currency,
			'base_price' => $price,
			'rate_step' => $this->getData(),
		];
		
		$currencyConversion['price'] = Billrun_CurrencyConvert_Manager::getPrice($currency, $this);
		$this->currencyConversion = $currencyConversion;
		return $currencyConversion['price'];
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

	/**
	 * get currency conversions done
	 *
	 * @return array
	 */
	public function getCurrencyConversion() {
		return $this->currencyConversion;
	}
}
