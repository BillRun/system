<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates a custom period single charge
 *
 * @package  Plans
 * @since    5.2
 */
class Billrun_Plans_Charge_Custom extends Billrun_Plans_Charge_Base {

	public function __construct($plan) {
		parent::__construct($plan);
	}

	/**
	 * Get the price of the current plan.
	 */
	public function getPrice($quantity = 1) {

		$charges = array();
		if($this->activation >= $this->cycle->start() && $this->activation < $this->cycle->end() ) {
			foreach ($this->price as $tariff) {
				$price = Billrun_Plan::getPriceByTariff($tariff, 0, 1,$this->activation);
				$convertedPrice = $this->getConvertedPrice($tariff);
				$multiplier =  $price['multiplier'] ?? 1;
				if (!empty($price)) {
					$charge = array(
						'value' => $convertedPrice * $multiplier * $quantity,
						'start' => Billrun_Plan::monthDiffToDate($price['start'], $this->activation),
						'end' => Billrun_Plan::monthDiffToDate($price['end'], $this->activation, FALSE, $this->cycle->end() >= $this->deactivation ? $this->deactivation : FALSE),
						'cycle' => $tariff['from'],
						'full_price' => $convertedPrice,
					);

					if ($this->shouldAddOriginalCurrency()) {
						$charge['original_currency'] = [
							'aprice' => $price['price'],
							'currency' => $this->defaultCurrency,
						];
					}
					
					$charges[] = $charge;
				}
			}
		}
		return $charges;
	}
	
}
