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
				$price = Billrun_Plan::getPriceByTariff($tariff, $this->startOffset, $this->endOffset);
				if (!empty($price)) {
					$charges[] = array('value' => $price['price'] * $quantity, 'cycle' => $tariff['from'], 'full_price' => floatval($tariff['price']) ,'prorated_start' =>false,'prorated_end' =>false,"start"=>$this->cycle->start(),'end'=> $this->cycle->end(),
					'deactivation_date'=>  $this->deactivation,
					'activation_date'=>  $this->activation,
'start_date'=> new MongoDate($this->cycle->start()), 'end_date' => new MongoDate($this->cycle->end())
					);
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
		$this->startOffset = Billrun_Plan::getMonthsDiff($formatActivation, $formatStart);
		$this->endOffset = Billrun_Plan::getMonthsDiff($formatActivation, $formatEnd);
	}
}
