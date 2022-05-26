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

		if ((empty($this->deactivation) || $this->deactivation >= $this->cycle->end() ) && $this->activation < $this->cycle->start()  ) {
			return 1;
		}
		$frequency = $this->recurrenceConfig['frequency'];
		$formatCycleStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end()-1);
		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatCycleStart,$formatCycleEnd);

		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront)
		if ($this->activation >= $this->cycle->start() && $this->deactivation >= $this->cycle->end()) {
			return 1 + (Billrun_Utils_Time::getDaysSpanDiffUnix($this->activation, $this->cycle->end()-1,$cycleSpan) );
		}
		// subscriber activates in the middle of the cycle and should be charged for a partial month
		if ($this->activation >= $this->cycle->start() && $this->deactivation <= $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return Billrun_Utils_Time::getDaysSpanDiffUnix($this->activation, $endActivation,$cycleSpan);
		}

		return null;
	}

	public function getRefund(Billrun_DataTypes_CycleTime $cycle, $quantity=1) {
		// $cycle is ignored  as the custom cycle configuration  will overseed the billrun cycle  configuration
		if (empty($this->deactivation)  ) {
			return null;
		}

		// get a refund for a cancelled plan paid upfront
		if ($this->activation > $this->cycle->start() //No refund need as it  started  in the current cycle
			 ||
			$this->deactivation >= $this->cycle->end() // the deactivation is in a future cycle
			 || // deactivation is before the cycle start
			$this->deactivation < $this->cycle->start() ) {
			return null;
		}

		$formatCycleStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end()-1);

		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatCycleStart,$formatCycleEnd);


		$lastUpfrontCharge = $this->getPriceForCycle($this->cycle);
		$endActivation  = strtotime('-1 second', $this->deactivation);
		$refundFraction = 1- Billrun_Utils_Time::getDaysSpanDiffUnix($this->cycle->start(), $endActivation, $cycleSpan);

		return array( 'value' => -$lastUpfrontCharge * $refundFraction * $quantity,
			'full_price' => floatval($lastUpfrontCharge),
			'start' => $this->activation,
			'prorated_start_date' => new Mongodloid_Date($this->deactivation),
			'end' => $this->deactivation,
			'prorated_end_date' =>  new Mongodloid_Date($this->cycle->end()) );
	}


	protected function getProrationData($price, $cycle = false) {
			$frequency = $this->recurrenceConfig['frequency'];

			$cycle = empty($cycle) ? $this->cycle : $cycle;
			$startOffset = Billrun_Utils_Time::getMonthsDiff( date(Billrun_Base::base_dateformat, $this->activation), date(Billrun_Base::base_dateformat, strtotime('-1 day', $cycle->end() )) );
			$nextCycle = $this->getUpfrontCycle($cycle);
			//"this->deactivation < $this->cycle->end()" as the  deactivation date euqal the end of the current (and not next) cycle mean that the deactivation is in the future
			return ['start' => $this->activation,
					'prorated_start_date' => new Mongodloid_Date($this->activation > $cycle->start() ? $this->activation  : ($this->seperatedCrossCycleCharges ? $cycle->start() :$nextCycle->start())),
					'end' => $this->deactivation < $this->cycle->end() ? $this->deactivation : $cycle->end(),
					'prorated_end_date' => new Mongodloid_Date($this->deactivation < $this->cycle->end() ? $this->deactivation : ($this->seperatedCrossCycleCharges ? $cycle->end() : $nextCycle->end())),
					'start_date' =>new Mongodloid_Date(Billrun_Plan::monthDiffToDate($startOffset,  $this->activation ,true,false,false ,$frequency )),
					'end_date' => new Mongodloid_Date($this->deactivation < $this->cycle->end() ? $this->deactivation : $cycle->end())];
	}

	protected function getUpfrontCycle($regularCycle) {
		$nextCycleKey = Billrun_Billingcycle::getFollowingBillrunKey($regularCycle->key());
		return  new Billrun_DataTypes_CustomCycleTime($nextCycleKey, $this->recurrenceConfig,$regularCycle->invoicingDay(),$this->activation);
	}

}
