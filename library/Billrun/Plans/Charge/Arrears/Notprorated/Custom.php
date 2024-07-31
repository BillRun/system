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
class Billrun_Plans_Charge_Arrears_Notprorated_Custom extends Billrun_Plans_Charge_Arrears_Notprorated_Month {
	use Billrun_Plans_Charge_Arrears_Traits_Custom;

	/**
	 * Get the amount the current plan/service covers the current cycle for non-prorated values.
	 */
	protected function setSpanCover() {
		$formatStart = date(Billrun_Base::base_dateformat, Billrun_Billingcycle::getBillrunStartTimeByDate(
										date(Billrun_Base::base_dateformat,$this->activation)
									)
								);

		$formatCycleStart = date(Billrun_Base::base_dateformat,  $this->cycle->start());
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end());

		$formatEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end() );

		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatCycleStart,$formatCycleEnd);
		//Round month days diffrences as there no proration and it should follow complete cycles
		$this->startOffset = round(Billrun_Utils_Time::getDaysSpanDiff($formatStart, $formatCycleStart, $cycleSpan));
		$this->endOffset = round(Billrun_Utils_Time::getDaysSpanDiff($formatStart, $formatEnd, $cycleSpan));
	}



}
