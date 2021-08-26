<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates a yearly upfront charge
 *
 * @package  Plans
 * @since    5.2
 */
trait Billrun_Plans_Charge_Arrears_Traits_Custom {
	use Billrun_Plans_Charge_Traits_Custom;

	public function __construct($plan) {
		parent::__construct($plan);;
		$this->recurrenceConfig = $plan['recurrence'];
		$this->updateCycleByConfig($plan);
		$this->setSpanCover();
	}

	/**
	 *
	 */
	protected function getTariffForMonthCover($tariff, $startOffset, $endOffset ,$activation = FALSE) {
		$frequency = $this->recurrenceConfig['frequency'];
		return Billrun_Plan::getPriceByTariff($tariff, $startOffset, $endOffset ,$activation);
	}

		/**
	 * Get the price of the current plan.
	 */
	protected function setSpanCover() {
		$formatActivation = $this->proratedStart  ?
										date(Billrun_Base::base_dateformat, $this->activation) :
										date(Billrun_Base::base_dateformat,Billrun_Billingcycle::getBillrunStartTimeByDate(date(Billrun_Base::base_dateformat,$this->activation)));

		$formatCycleStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end()-1);
		$fakeSubDeactivation = (empty($this->subscriberDeactivation) ? PHP_INT_MAX : $this->subscriberDeactivation);
		$this->isTerminated =  ($fakeSubDeactivation <= $this->deactivation || empty($this->deactivation) && $fakeSubDeactivation < $this->cycle->end());
		$adjustedDeactivation = (empty($this->deactivation) || (!$this->proratedEnd && !$this->isTerminated || !$this->proratedTermination && $this->isTerminated ) ? $this->cycle->end() : $this->deactivation - 1);
		$formatEnd = date(Billrun_Base::base_dateformat, min( $adjustedDeactivation, $this->cycle->end() - 1) );

		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatStart,$formatCycleEnd);
		$this->startOffset = Billrun_Utils_Time::getDaysSpanDiff($formatActivation, $formatStart,$cycleSpan);
		$this->endOffset = Billrun_Utils_Time::getDaysSpanDiff($formatActivation, $formatEnd,$cycleSpan);
	}

}
