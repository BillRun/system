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

		if ((empty($this->deactivation) || $this->deactivation > $this->cycle->end() )&& $this->activation < $this->cycle->start()  ) {
			return 1;
		}
		$frequency = $this->recurrenceConfig['frequency'];
		$formatCycleStart = date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->start()));
		$formatCycleEnd = date(Billrun_Base::base_dateformat,  $this->cycle->end()-1);
		$cycleSpan = Billrun_Utils_Time::getDaysSpan($formatStart,$formatCycleEnd);

		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront)
		if ($this->activation > $this->cycle->start() && $this->deactivation > $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return 1 + (Billrun_Utils_Time::getDaySpanDiff($this->activation, $this->cycle->end(),$cycleSpan) );
		}
		// subscriber activates in the middle of the cycle and should be charged for a partial month
		if ($this->activation > $this->cycle->start() && $this->deactivation <= $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return Billrun_Utils_Time::getDaySpanDiff($this->activation, $endActivation,$cycleSpan);
		}

		return null;
	}
}
