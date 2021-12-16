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
		
		$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
		$formatStart = date(Billrun_Base::base_dateformat, $this->cycle->start());
		$formatDeactivation = date(Billrun_Base::base_dateformat, $this->deactivation);
		
		// subscriber deactivates and should be charged for a partial month
		if ($this->activation > $this->cycle->start()) { 
			$fraction = Billrun_Plan::calcFractionOfMonth($this->cycle->key(), $formatActivation, $formatDeactivation);
			return $fraction / 12;
		} 
		
		$activationDiffStart = floor(Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatStart));
		$activationDiffDeactivation = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatDeactivation);
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
		$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
		$formatDeactivation = date(Billrun_Base::base_dateformat, $this->deactivation);
		$endMonths = $formatDeactivation;
		if (empty($endMonths)) {
			$endMonths = date(Billrun_Base::base_dateformat, $this->cycle->end() - 1);
		} 
		
		return Billrun_Utils_Time::getMonthsDiff($formatActivation, $endMonths);
	}
	
	public function getRefund(Billrun_DataTypes_CycleTime $cycle) {
		if (empty($this->deactivation)) {
			return null;
		}
		
		// get a refund for a cancelled plan paid upfront
		if ($this->activation > $cycle->start()) { 
			return null;
		}
		
		$lastUpfrontCharge = $this->getPrice()['value'];
		$formatActivation = date(Billrun_Base::base_dateformat, $this->activation);
		$formatDeactivation = date(Billrun_Base::base_dateformat, $this->deactivation);
		$monthsDiff = Billrun_Utils_Time::getMonthsDiff($formatActivation, $formatDeactivation);
		$refundFraction = 1 - ((floor($monthsDiff) % 12) + $monthsDiff - floor($monthsDiff));
		return array('value' => -$lastUpfrontCharge * $refundFraction);
	}
}
