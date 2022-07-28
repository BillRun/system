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
		
		if (empty($this->deactivation) && $this->activation < $this->cycle->start()  ) {
			return 1;
		} 

		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront) 
		if ($this->activation >= $this->cycle->start() && $this->deactivation > $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return 1 + Billrun_Plan::calcFractionOfMonthUnix($this->cycle->key(), $this->activation, $endActivation);
		}
		// subscriber activates in the middle of the cycle and should be charged for a partial month
		if ($this->activation >= $this->cycle->start() && $this->deactivation <= $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return Billrun_Plan::calcFractionOfMonthUnix($this->cycle->key(), $this->activation, $endActivation);
		}

		if ($this->deactivation > $this->cycle->end() ) {
			return 1;
		} 		

		return null;
	}

	public function getRefund(Billrun_DataTypes_CycleTime $cycle, $quantity=1) {
		
		if (empty($this->deactivation)  ) {
			return null;
		}
		$endProration =  $this->proratedEnd && !$this->isTerminated || ($this->proratedTermination && $this->isTerminated);
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
		
		$lastUpfrontCharge = $this->getPriceForCycle($cycle);
		$endActivation  = strtotime('-1 second', $this->deactivation);
		$refundFraction = 1- Billrun_Plan::calcFractionOfMonthUnix($cycle->key(), $this->activation, $endActivation);
		
		return array( 'value' => -$lastUpfrontCharge * $refundFraction * $quantity,
			'full_price' => floatval($lastUpfrontCharge),
			'start' => $this->activation,
			'prorated_start_date' => new Mongodloid_Date($this->deactivation),
			'end' => $this->deactivation,
			'prorated_end_date' =>  new Mongodloid_Date($this->cycle->end()),
			'prorated_end' => true,
			'is_upfront' => true);
	}
}
