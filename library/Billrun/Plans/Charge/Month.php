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
		$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
		$formatStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', strtotime($this->cycle->start())));
		$formatEnd = date(Billrun_Base::base_dateformat, strtotime('-1 day', strtotime($this->cycle->end())));
		
		$startOffset = Billrun_Plan::getMonthsDiff($formatActivation, $formatStart);
		$endOffset = Billrun_Plan::getMonthsDiff($formatEnd, $formatActivation);
		$charge = 0;
		foreach ($this->price as $tariff) {
			$charge += Billrun_Plan::getPriceByTariff($tariff, $startOffset, $endOffset);
		}
		return $charge;
	}
}
