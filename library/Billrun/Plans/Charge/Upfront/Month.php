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

		$activation = strtotime($this->activation);
		
		// subscriber deactivates and should be charged for a partial month		
		if ($activation > $this->cycle->start()) {
			return Billrun_Plan::calcFractionOfMonth($this->cycle->key(), $this->activation, $this->deactivation);
		}
		$activationDiffStart = floor(Billrun_Plan::getMonthsDiff($this->activation, $this->cycle->start()));
		$activationDiffDeactivation = Billrun_Plan::getMonthsDiff($this->activation, $this->deactivation);
		$flooredActDeacDiff = floor($activationDiffDeactivation);
		
		if ($activationDiffStart == $flooredActDeacDiff) {
			// TODO: What the hell???? Why am i returning null here? why is this condition so important?
			return null;
		}
		
		return $activationDiffDeactivation - floor($activationDiffDeactivation);
	}

}
