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
class Billrun_Plans_Charge_Arrears_Month extends Billrun_Plans_Charge_Base {
	
	public function __construct($plan) {
		parent::__construct($plan);
		$this->setMonthlyCover();
	}
	
	/**
	 * Get the price of the current plan.
	 */
	public function getPrice($quantity = 1) {

		$charges = array();
		foreach ($this->price as $tariff) {
			$price = Billrun_Plan::getPriceByTariff($tariff, $this->startOffset, $this->endOffset ,$this->activation);
			if (!empty($price)) {
				$charges[] = array('value' => $price['price'] * $quantity,
					'start' => Billrun_Plan::monthDiffToDate($price['start'], $this->activation),
					'end' => Billrun_Plan::monthDiffToDate($price['end'], $this->activation, FALSE, $this->cycle->end() >= $this->deactivation ? $this->deactivation : FALSE, $this->deactivation && $this->cycle->end() > $this->deactivation ),
					'cycle' => $tariff['from'],
					'full_price' => floatval($tariff['price']) );
					
			}
		}
		return $charges;
	}

	/**
	 * Get the price of the current plan.
	 */
	protected function setMonthlyCover() {
		$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
		$formatStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatEnd = date(Billrun_Base::base_dateformat, min( (empty($this->deactivation) ? PHP_INT_MAX : $this->deactivation - 1), $this->cycle->end() - 1) );
		
		$isTerminated = min( (empty($this->deactivation) ? PHP_INT_MAX : $this->deactivation), $this->cycle->end()) <  $this->cycle->end();

		$this->startOffset = Billrun_Plan::getMonthsDiff($formatActivation, $formatStart);
		$this->endOffset = Billrun_Plan::getMonthsDiff($formatActivation, $formatEnd);
		if(!$this->proratedStart) {
			$this->startOffset = floor($this->startOffset);
		}
		if(!$this->proratedEnd || !$this->proratedTermination && $isTerminated ) {
			$this->endOffset = ceil($this->endOffset);
		}
	}
	

	
	
}
