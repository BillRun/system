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
		if (empty($this->deactivation)) {
			return 1;
		} 

		// subscriber deactivates and should be charged for a partial month		
		if ($this->activation > $this->cycle->start()) {
			return Billrun_Plan::calcFractionOfMonth($this->cycle->key(), $this->activation, $this->deactivation);
		}
		
		$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
		$formatStart = date(Billrun_Base::base_dateformat, $this->cycle->start());
		$formatDeactivation = date(Billrun_Base::base_dateformat, $this->deactivation);
		
		$activationDiffStart = floor(Billrun_Plan::getMonthsDiff($formatActivation, $formatStart));
		$activationDiffDeactivation = Billrun_Plan::getMonthsDiff($formatActivation, $formatDeactivation);
		$flooredActDeacDiff = floor($activationDiffDeactivation);
		
		if ($activationDiffStart == $flooredActDeacDiff) {
			// TODO: What the hell???? Why am i returning null here? why is this condition so important?
			return null;
		}
		
		return $activationDiffDeactivation - floor($activationDiffDeactivation);
	}

	public function getRefund(Billrun_DataTypes_CycleTime $cycle) {
		if (empty($this->deactivation)) {
			return null;
		}
		
		// get a refund for a cancelled plan paid upfront
		if ($this->activation > $cycle->start()) { 
			return null;
		}
		
		$lastUpfrontCharge = $this->getPrice();
		$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
		$formatDeactivation = date(Billrun_Base::base_dateformat, $this->deactivation);
		$refundFraction = 1 - Billrun_Plan::calcFractionOfMonth($cycle->key(), $formatActivation, $formatDeactivation);
		return -$lastUpfrontCharge * $refundFraction;
	}
}
