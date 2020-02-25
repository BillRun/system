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
		if (empty($this->deactivation) ) {
			return 1;
		} 

		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront) 
		if ($this->activation > $this->cycle->start() && $this->deactivation > $this->cycle->end()) {
			return 1 + Billrun_Plan::calcFractionOfMonthUnix($this->cycle->key(), $this->activation, $this->deactivation);
		}
                // subscriber activates in the middle of the cycle and should be charged for a partial month
                if ($this->activation > $this->cycle->start() && $this->deactivation <= $this->cycle->end()){
                    return Billrun_Plan::calcFractionOfMonthUnix($this->cycle->key(), $this->activation, $this->deactivation);
                }

		if ($this->deactivation > $this->cycle->end() ) {
			return 1;
		} 		

		return null;
	}

	public function getRefund(Billrun_DataTypes_CycleTime $cycle) {
		
		if (empty($this->deactivation)  ) {
			return null;
		}
		
		// get a refund for a cancelled plan paid upfront
		if ($this->activation > $cycle->start() //No refund need as it  started  in the current cycle
			 || 
			$this->deactivation > $this->cycle->end() // the deactivation is in a future cycle
			) { 
			return null;
		}
		
		$lastUpfrontCharge = $this->getPriceForcycle($cycle);
		$refundFraction = 1- Billrun_Plan::calcFractionOfMonthUnix($cycle->key(), $this->activation, $this->deactivation);
		
		return array( 'value' => -$lastUpfrontCharge * $refundFraction, 
			'start' => $this->activation, 
			'end' => $this->deactivation);
	}
}
