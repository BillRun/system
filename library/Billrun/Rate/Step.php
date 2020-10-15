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

	protected $prevStep = null;

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

	public function isValid() {
		return is_array($this->data) && !empty($this->data);
	}
    
    public function getChargeValue($volume) {
		$ceil = $this->get('ceil', true);
		$toCharge = $volume / $this->get('interval');
		
		if ($ceil) {
			$toCharge = ceil($toCharge);
		}
	
		return floatval($toCharge * $this->get('price'));
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

	public function getPrevStep() {
		return $this->prevStep;
	}
}
