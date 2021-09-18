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
		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront)
		if ($this->activation > $this->cycle->start() && $this->deactivation > $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return 1 + (Billrun_Utils_Time::getMonthsDiffUnix($this->activation, $this->cycle->end()) / $frequency);
		}
		// subscriber activates in the middle of the cycle and should be charged for a partial month
		if ($this->activation > $this->cycle->start() && $this->deactivation <= $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return Billrun_Utils_Time::getMonthsDiffUnix($this->activation, $endActivation) / $frequency ;
		}

		return null;
	}

		protected function getProrationData($price) {
			$frequency = $this->recurrenceConfig['frequency'];
			$startOffset = Billrun_Utils_Time::getMonthsDiff( date(Billrun_Base::base_dateformat, $this->activation), date(Billrun_Base::base_dateformat, strtotime('-1 day', $this->cycle->end() )) );
			return ['start' => $this->activation,
					'end' => $this->deactivation < $this->cycle->end() ? $this->deactivation : $this->cycle->end(),
					'start_date' =>new MongoDate(Billrun_Plan::monthDiffToDate($startOffset,  $this->activation ,true,false,false ,$frequency )),
					'end_date' => new MongoDate($this->deactivation < $this->cycle->end() ? $this->deactivation : $this->cycle->end())];
	}
}
