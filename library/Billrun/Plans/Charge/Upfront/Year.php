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
class Billrun_Plans_Charge_Upfront_Year extends Billrun_Plans_Charge_Upfront {
	/**
	 * Get the price of the current plan when the plan is to be paid upfront
	 * @param type $startOffset
	 * @return price
	 */
	protected function getPriceByOffset($startOffset) {
		return parent::getPriceByOffset($startOffset / 12);
	}

	/**
	 * Calc the fraction of month
	 * @return int
	 */
	protected function getFractionOfMonth() {
		$monthsDiff = $this->getMonthsDiff();
		$flooredDiff = floor($monthsDiff);
		
		$inMonthExpression = ($flooredDiff % 12) + $monthsDiff - $flooredDiff;
		$isInMonth = ($inMonthExpression <= 1);
		if (empty($this->deactivation) && $isInMonth) {
			return 1;
		} 
		
		$activation = strtotime($this->activation);
		// subscriber deactivates and should be charged for a partial month
		if ($activation > $this->cycle->start()) { 
			$fraction = Billrun_Plan::calcFractionOfMonth($this->cycle->key(), $this->activation, $this->deactivation);
			return $fraction / 12;
		} 
		
		$activationDiffStart = floor(Billrun_Plan::getMonthsDiff($this->activation, $this->cycle->start()));
		$activationDiffDeactivation = Billrun_Plan::getMonthsDiff($this->activation, $this->deactivation);
		$flooredActDeacDiff = floor($activationDiffDeactivation);
		if ($activationDiffStart != $flooredActDeacDiff) {
			return null;
		}
		
		if ($isInMonth) {
			return $inMonthExpression;
		}
		
		return null;
	}

	protected function getMonthsDiff() {
		$endMonths = $this->deactivation;
		if (empty($endMonths)) {
			$endMonths = date(Billrun_Base::base_dateformat, $this->cycle->end() - 1);
		} 
		
		return Billrun_Plan::getMonthsDiff($this->activation, $endMonths);
	}
}
