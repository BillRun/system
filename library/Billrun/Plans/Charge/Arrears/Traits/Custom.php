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
		$this->setMonthlyCover();
	}

	/**
	 *
	 */
	protected function getTariffForMonthCover($tariff, $startOffset, $endOffset ,$activation = FALSE) {
		$frequency = $this->recurrenceConfig['frequency'];
		return Billrun_Plan::getPriceByTariff($tariff, $startOffset/$frequency, $endOffset/$frequency ,$activation);
	}

	protected function getProrationData($price) {
		$endProration =  $this->proratedEnd && !$this->isTerminated || ($this->proratedTermination && $this->isTerminated);
		$proratedActivation =  $this->proratedStart  || $this->startOffset ?  $this->activation :  $this->cycle->start();
		$proratedEnding =  $this->cycle->end() >= $this->deactivation ? $this->deactivation : FALSE  ;
		$frequency = $this->recurrenceConfig['frequency'];
		return [	'start_date' => new MongoDate(Billrun_Plan::monthDiffToDate($price['start'],  $this->activation ,true,false,false, $frequency)),
					'start' => $this->proratedStart ? Billrun_Plan::monthDiffToDate($price['start'], $proratedActivation,true,false,false,$frequency) : $this->cycle->start(),
					'prorated_start' =>  $this->proratedStart ,
					'end' => $endProration ? Billrun_Plan::monthDiffToDate($price['end'], $proratedActivation, FALSE, $proratedEnding, $this->deactivation && $this->cycle->end() > $this->deactivation, $frequency) : $this->cycle->end(),
					'end_date' => new MongoDate(Billrun_Plan::monthDiffToDate($price['end'],  $this->activation , FALSE, $this->deactivation ,$this->deactivation && $this->cycle->end() > $this->deactivation, $frequency)),
					'prorated_end' =>  $endProration
				];
	}

}
