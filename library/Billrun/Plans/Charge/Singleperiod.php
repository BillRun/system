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
class Billrun_Plans_Charge_Singleperiod extends Billrun_Plans_Charge_Base {

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
				if (!empty($price)) {
					$charges[] = array('value' => $price['price'] * $quantity,
						'start' => Billrun_Plan::monthDiffToDate($price['start'], $this->activation),
						'end' => Billrun_Plan::monthDiffToDate($price['end'], $this->activation, FALSE, $this->cycle->end() >= $this->deactivation ? $this->deactivation : FALSE),
						'cycle' => $tariff['from'],
						'full_price' => floatval($tariff['price']) );

				}
			}
		}
		return $charges;
	}

}
