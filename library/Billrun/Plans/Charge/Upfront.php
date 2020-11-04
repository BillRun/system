<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates an upfront charge
 *
 * @package  Plans
 * @since    5.2
 */
abstract class Billrun_Plans_Charge_Upfront extends Billrun_Plans_Charge_Base {
	
	public function __construct($plan) {
		parent::__construct($plan);
		
		// Check if a deactivation date exists.
		
	}

	/**
	 * 
	 */
	protected abstract function getFractionOfMonth();
	
	public abstract function getRefund(Billrun_DataTypes_CycleTime $cycle);
	
	/**
	 * Get the price of the current plan.
	 * @return int, null if no charge
	 */
	public function getPrice($quantity = 1) {
		
		$priceForCycle = $this->getPriceForcycle($this->cycle);
		$price = $priceForCycle['price'];
		$fraction = $this->getFractionOfMonth();
		if($fraction === null) {
			return null;
		}
		
		$charge = array(
			'value'=> $price * $fraction, 
			'start' => $this->activation, 
			'end' => $this->deactivation < $this->cycle->end() ? $this->deactivation : $this->cycle->end(),
			'full_price' => floatval($price)
			);

		if (!empty($this->currency) && $this->currency !== $this->defaultCurrency) {
			$charge['original_currency'] = [
				'aprice' => $priceForCycle['orig_price'],
				'currency' => $this->defaultCurrency,
			];
		}

		return $charge;
	}

	protected function getPriceForcycle($cycle) {
		$formatStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $cycle->end()));
		$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
		$startOffset = Billrun_Plan::getMonthsDiff($formatActivation, $formatStart);
		return $this->getPriceByOffset($startOffset);
	}
	
	/**
	 * Get the price of the current plan
	 * @param type $startOffset
	 * @return price
	 */
	protected function getPriceByOffset($startOffset) {
		foreach ($this->price as $tariff) {
			if ($tariff['from'] <= $startOffset && (Billrun_Plan::isValueUnlimited($tariff['to']) ? PHP_INT_MAX : $tariff['to']) > $startOffset) {
				return [
					'orig_price' => $tariff['price'],
					'price' => $this->getConvertedPrice($tariff),
				];
			}
		}
		
		return ['price' => 0];
	}
	
}
