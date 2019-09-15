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
	
	protected $isTerminated = FALSE;

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
					'prorated_start' =>  $this->proratedStart ,
					'end' => Billrun_Plan::monthDiffToDate($price['end'], $this->activation, FALSE, $this->cycle->end() >= $this->deactivation ? $this->deactivation : FALSE, $this->deactivation && $this->cycle->end() > $this->deactivation ),
					'prorated_end' =>  $this->proratedEnd && !$this->isTerminated || ($this->proratedTermination && $this->isTerminated),

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
		$formatActivation = $this->proratedStart  ?
										date(Billrun_Base::base_dateformat, $this->activation) :
										date(Billrun_Base::base_dateformat,Billrun_Billingcycle::getBillrunStartTimeByDate(date(Billrun_Base::base_dateformat,$this->activation)));

		$formatStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$fakeSubDeactivation = min( (empty($this->subscriberDeactivation) ? PHP_INT_MAX : $this->subscriberDeactivation));
		$this->isTerminated =  ($fakeDeactivation <= $this->deactivation || empty($this->deactivation) && $fakeSubDeactivation < $this->cycle->end());
		$adjustedDeactivation = (empty($this->deactivation) || (!$this->proratedEnd && !$this->isTerminated || !$this->proratedTermination && $this->isTerminated ) ? $this->cycle->end() : $this->deactivation - 1);
		$formatEnd = date(Billrun_Base::base_dateformat, min( $adjustedDeactivation, $this->cycle->end() - 1) );
		


		$this->startOffset = Billrun_Plan::getMonthsDiff($formatActivation, $formatStart);
		$this->endOffset = Billrun_Plan::getMonthsDiff($formatActivation, $formatEnd);

	}
	

	
	
}
