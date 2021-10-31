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

		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatCycleStart,$formatCycleEnd);
		$this->startOffset = Billrun_Utils_Time::getDaysSpanDiff($formatActivation, $formatCycleStart,$cycleSpan);
		$this->endOffset = Billrun_Utils_Time::getDaysSpanDiff($formatActivation, $formatEnd,$cycleSpan);
	}

	protected function getProrationData($price) {
		$endProration =  $this->proratedEnd && !$this->isTerminated || ($this->proratedTermination && $this->isTerminated);
		$proratedActivation =  $this->proratedStart  || $this->startOffset ?  $this->activation :  $this->cycle->start();
		$proratedEnding =  $this->cycle->end() >= $this->deactivation ? $this->deactivation : FALSE  ;
		$frequency = $this->recurrenceConfig['frequency'];
		return [	'start_date' => new Mongodloid_Date(Billrun_Plan::monthDiffToDate($price['start'],  $this->activation ,true,false,false, $frequency)),
					'start' => $this->proratedStart ? Billrun_Plan::monthDiffToDate($price['start'], $proratedActivation,true,false,false,$frequency) : $this->cycle->start(),
					'prorated_start_date' => new Mongodloid_Date($this->proratedStart   && $this->activation > $this->cycle->start()? Billrun_Plan::monthDiffToDate($price['start'], $proratedActivation,true,false,false,$frequency) : $this->cycle->start()),
					'prorated_start' =>  $this->proratedStart ,
					'end' => $endProration ? Billrun_Plan::monthDiffToDate($price['end'], $proratedActivation, FALSE, $proratedEnding, $this->deactivation && $this->cycle->end() > $this->deactivation, $frequency) : $this->cycle->end(),
					'prorated_end_date' => new Mongodloid_Date($endProration && $this->cycle->end() > $this->deactivation ? Billrun_Plan::monthDiffToDate($price['end'], $proratedActivation, FALSE, $proratedEnding, $this->deactivation && $this->cycle->end() > $this->deactivation, $frequency) : $this->cycle->end()),
					'end_date' => new Mongodloid_Date(Billrun_Plan::monthDiffToDate($price['end'],  $this->activation , FALSE, $this->deactivation ,$this->deactivation && $this->cycle->end() > $this->deactivation, $frequency)),
					'prorated_end' =>  $endProration
				];
	}

}
