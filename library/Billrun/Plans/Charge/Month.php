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
class Billrun_Plans_Charge_Month extends Billrun_Plans_Charge_Base {
	
	/**
	 * Get the price of the current plan.
	 */
	public function getPrice() {
		$startOffset = Billrun_Plan::getMonthsDiff($this->activation, date(Billrun_Base::base_dateformat, strtotime('-1 day', strtotime($this->cycle->start()))));
		$endOffset = Billrun_Plan::getMonthsDiff($this->activation, $this->cycle->end());
		$charge = 0;
		foreach ($this->price as $tariff) {
			$charge += Billrun_Plan::getPriceByTariff($tariff, $startOffset, $endOffset);
		}
		return $charge;
	}
}
