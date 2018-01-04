<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates a monthly charge
 *
 * @package  Plans
 * @since    5.2
 */
class Billrun_Plans_Charge_Arrears_Notprorated_Month extends Billrun_Plans_Charge_Arrears_Month {
	
	/**
	 * Get the price of the current plan.
	 */
	public function getPrice($quantity = 1) {
		$charges = array();

		foreach ($this->price as $tariff) {
			$price = Billrun_Plan::getPriceByTariff($tariff, max(0, floor($this->startOffset+1)), floor($this->endOffset+1));
			if (!empty($price)) {
				$charges[] = array('value' => $price['price'], 'cycle' => $tariff['from']);
			}
		}

		return $charges;
	}
}
