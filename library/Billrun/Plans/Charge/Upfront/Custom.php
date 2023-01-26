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
class Billrun_Plans_Charge_Upfront_Custom extends Billrun_Plans_Charge_Upfront_Month {

	use Billrun_Plans_Charge_Traits_Custom;

	protected function getFractionOfMonth() {

		//got a future subscriber for  some reason.
		if( $this->activation >= $this->cycle->end()) {
			return null;
		}

		if ((empty($this->deactivation) || $this->deactivation >= $this->cycle->end() ) && $this->activation < $this->cycle->start()  ) {
			return 1;
		}
		$frequency = $this->recurrenceConfig['frequency'];
		$formatCycleStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end()-1);
		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatCycleStart,$formatCycleEnd);

		$startActivation =  ($this->proratedStart ? $this->activation : $this->cycle->start()) ;
		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront)
		if ($this->activation >= $this->cycle->start() && $this->deactivation >= $this->cycle->end()) {
			return 1 + (Billrun_Utils_Time::getDaysSpanDiffUnix($startActivation, $this->cycle->end()-1,$cycleSpan) );
		}
		// subscriber activates in the middle of the cycle and should be charged for a partial month
		if ($this->activation >= $this->cycle->start() && $this->deactivation <= $this->cycle->end()) {
			$endActivation = ($this->proratedEnd || $this->proratedTermination && $this->isTerminated() ? $this->deactivation : $this->cycle->end())-1;
			return Billrun_Utils_Time::getDaysSpanDiffUnix($startActivation, $endActivation,$cycleSpan);
		}

		return null;
	}

	public function getRefund(Billrun_DataTypes_CycleTime $cycle, $quantity=1) {
		// $cycle is ignored  as the custom cycle configuration  will overseed the billrun cycle  configuration
		if (	empty($this->deactivation) || //dont have  deactivateion
				!$this->proratedEnd && // dont need to be prorated on ending
				!($this->proratedTermination && $this->isTerminated()) ) { // dont return if termination and sub actually terminate
			return null;
		}

		$endProration =  $this->proratedEnd && !$this->isTerminated || ($this->proratedTermination && $this->isTerminated);
		// get a refund for a cancelled plan paid upfront
		if ($this->activation >= $this->cycle->start() //No refund need as it started in the current cycle
			 ||
			$this->deactivation >= $this->cycle->end() // the deactivation is in a future cycle
			 || // deactivation is before the cycle start
			$this->deactivation < $this->cycle->start()
			 || // When termination the plan the is no proration (so no refund)
			!$endProration ) {
			return null;
		}

		$formatCycleStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end()-1);

		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatCycleStart,$formatCycleEnd);


		$lastUpfrontCharge = $this->getPriceForCycle($this->cycle);
		$endActivation  =  $this->deactivation;
		$refundFraction = 1- Billrun_Utils_Time::getDaysSpanDiffUnix($this->cycle->start(), $endActivation, $cycleSpan);


		return array( 'value' => -$lastUpfrontCharge * $refundFraction * $quantity,
			'full_price' => floatval($lastUpfrontCharge),
			'start' => $this->activation,
			'prorated_start_date' => new Mongodloid_Date($this->deactivation),
			'end' => $this->deactivation,
			'prorated_end_date' =>  new Mongodloid_Date($this->cycle->end()),
			'prorated_end' => true,
			'is_upfront' => true);
	}


	protected function getProrationData($price, $cycle = false) {
			$frequency = $this->recurrenceConfig['frequency'];
			$endProration =  $this->proratedEnd && !$this->isTerminated || ($this->proratedTermination && $this->isTerminated);
			$cycle = empty($cycle) ? $this->cycle : $cycle;
			$startOffset = Billrun_Utils_Time::getMonthsDiff( date(Billrun_Base::base_dateformat, $this->activation), date(Billrun_Base::base_dateformat, strtotime('-1 day', $cycle->end() )) );
			$nextCycle = $this->getUpfrontCycle($cycle);
			$isUpfront =  $cycle->start() >= $this->cycle->end()  || !$this->seperatedCrossCycleCharges && $this->deactivation >= $this->cycle->end();
			//"this->deactivation < $this->cycle->end()" as the  deactivation date euqal the end of the current (and not next) cycle mean that the deactivation is in the future
			return ['start' => $this->activation,
					'prorated_start_date' => new Mongodloid_Date($this->activation > $cycle->start() ? $this->activation  : ($this->seperatedCrossCycleCharges ? $cycle->start() :$nextCycle->start())),
					'end' => $this->deactivation < $this->cycle->end() ? $this->deactivation : $cycle->end(),
					'prorated_end_date' => new Mongodloid_Date($this->deactivation < $this->cycle->end() ? $this->deactivation : ($this->seperatedCrossCycleCharges ? $cycle->end() : $nextCycle->end())),
					'start_date' =>new Mongodloid_Date(Billrun_Plan::monthDiffToDate($startOffset,  $this->activation ,true,false,false ,$frequency )),
					'end_date' => new Mongodloid_Date($this->deactivation < $this->cycle->end() ? $this->deactivation : $cycle->end()),
					'is_upfront' =>  $isUpfront,
					'prorated_start' =>  $this->proratedStart && !($isUpfront && $this->seperatedCrossCycleCharges),
					'prorated_end' =>  $endProration && !$isUpfront
					];
	}

	protected function getUpfrontCycle($regularCycle) {
		$nextCycleKey = Billrun_Billingcycle::getFollowingBillrunKey($regularCycle->key());
		return  new Billrun_DataTypes_CustomCycleTime($nextCycleKey, $this->recurrenceConfig,$regularCycle->invoicingDay(),$this->activation);
	}

	protected function getPriceForCycle($cycle) {
        $formatStart = date(Billrun_Base::base_dateformat,  $cycle->start());
        $formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
        $cycleCount = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatStart)/$this->recurrenceConfig['frequency'];
        return $this->getPriceByOffset($cycleCount);
	}

}
