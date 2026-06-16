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
class Billrun_Plans_Charge_Upfront_Month extends Billrun_Plans_Charge_Upfront {

	/**
	 * 
	 * @return int
	 */
	protected function getFractionOfMonth() {
		//got a future subscriber for  some reason.
		if( $this->activation >= $this->cycle->end()) {
			return null;
		}

		if ((empty($this->deactivation) || $this->deactivation >= $this->cycle->end()) && $this->activation < $this->cycle->start()  ) {
			return 1;
		} 

		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront) 
		$formatCycleStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end()-1);
		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatCycleStart,$formatCycleEnd);

		$startActivation =  ($this->proratedStart ? $this->activation : $this->cycle->start()) ;
		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront)
		if ($this->activation >= $this->cycle->start() && $this->deactivation >= $this->cycle->end()) {
			return 1 + (Billrun_Utils_Time::getDaysSpanDiffUnix($startActivation, $this->cycle->end()-1,$cycleSpan) );
		}
		$endProration =  $this->proratedEnd && !$this->isTerminated($cycle) || ($this->proratedTermination && $this->isTerminated($cycle));

		// subscriber activates in the middle of the cycle and should be charged for a partial month
		if ($this->activation >= $this->cycle->start() && $this->deactivation <= $this->cycle->end()) {
			$endActivation = ($endProration ? $this->deactivation : $this->cycle->end())-1;
			return Billrun_Utils_Time::getDaysSpanDiffUnix($startActivation, $endActivation,$cycleSpan);
		} 		

		return null;
	}

	public function getRefund(Billrun_DataTypes_CycleTime $cycle, $quantity=1) {
		
		if (empty($this->deactivation) || //dont have  deactivateion
				!$this->proratedEnd && // dont need to be prorated on ending
				!($this->proratedTermination && $this->isTerminated()) ) { // dont return if termination and sub actually terminate
			return null;
		}
		$endProration =  $this->proratedEnd && !$this->isTerminated($cycle) || ($this->proratedTermination && $this->isTerminated($cycle));
		// get a refund for a cancelled plan paid upfront
		if ($this->activation >= $cycle->start() //No refund need as it  started  in the current cycle
			 || 
			$this->deactivation >= $this->cycle->end()  // the deactivation is in a future cycle
			 || // deactivation is before the cycle start
			$this->deactivation < $this->cycle->start() // the deactivation is in a future cycle
			 || // When termination the plan the is no proration (so no refund)
			!$endProration ) {
			return null;
		}
		
		$formatCycleStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end()-1);

		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatCycleStart,$formatCycleEnd);


		$lastUpfrontCharge = $this->getPriceForCycle($this->cycle);
		$origUpfrontCharge = $this->lastOrigPrice;
		$endActivation  =  $this->deactivation;
		$refundFraction = 1- Billrun_Utils_Time::getDaysSpanDiffUnix($this->cycle->start(), $endActivation, $cycleSpan);


		$ret = array(
			'value' => -$lastUpfrontCharge * $refundFraction * $quantity,
			'full_price' => floatval($lastUpfrontCharge),
			'start' => $this->deactivation ,
			'prorated_start_date' => new Mongodloid_Date($this->deactivation),
			'end' => $this->cycle->end(),
			'prorated_end_date' =>  new Mongodloid_Date($this->cycle->end()),
			'prorated_end' => $endProration,
			'is_upfront' => true
		);

		if ($this->shouldAddOriginalCurrency()) {
			$ret['original_currency'] = [
				'aprice' => -$origUpfrontCharge * $refundFraction * $quantity,
				'currency' => $this->defaultCurrency,
			];
		}

		return $ret;
	}
}
