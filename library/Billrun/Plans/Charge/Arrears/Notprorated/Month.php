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
		if ($this->endOffset > 0 ) {
			foreach ($this->price as $tariff) {
				$step = new Billrun_Plans_Step($tariff);
				$price = $step->getRelativePrice($this->startOffset, $this->endOffset, false, $this->currency);
				if (!empty($price)) {
					$charge = array(
						'value' => $price['price'] * $quantity,
						'cycle' => $tariff['from'],
						'full_price' => $price['full_price'],
						'prorated_start' => false,
						'prorated_end' =>false,
					);

					if ($this->shouldAddOriginalCurrency()) {
						$charge['original_currency'] = [
							'aprice' => $price['orig_price'],
							'currency' => $this->defaultCurrency,
						];
					}

					$charges[] = $charge;
				}
			}
		}

		return $charges;
	}
	
	/**
	 * Get the price of the current plan.
	 */
	protected function setMonthlyCover() {
		$formatActivation = date('Y-m-01', $this->activation);
		$formatStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end() - 1 );
		$this->startOffset = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatStart);
		$this->endOffset = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatEnd);
	}
}
