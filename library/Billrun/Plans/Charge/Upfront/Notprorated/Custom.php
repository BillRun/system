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
class Billrun_Plans_Charge_Upfront_Notprorated_Custom extends Billrun_Plans_Charge_Upfront_Notprorated_Month {

	use Billrun_Plans_Charge_Traits_Custom;

	protected function getFractionOfMonth() {
		//No change in current cycle  charge  just for the upfront
		if ((empty($this->deactivation) || $this->deactivation > $this->cycle->end() ) && $this->activation < $this->cycle->start()  ) {
			return 1;
		}
		$frequency = $this->recurrenceConfig['frequency'];
		// subscriber activates in the middle of the current cycle month and should be charged for the next month (upfront)
		if ($this->activation >= $this->cycle->start() && $this->deactivation > $this->cycle->end() ) {
			return ($this->proratedStart ? 1 : 0 ) + 1;
		}
		// subscriber activates in the middle of the cycle and ends  before the  cyclle is done  should be charged for the current cycle month and no more
		if ($this->activation >= $this->cycle->start() && $this->deactivation <= $this->cycle->end() ) {
			return 1;
		}
		// nothing to charge as the values are out of this cycle. return null to indicate invalid charge  without affecting other charges.
		Billrun_Factory::log(Billrun_Utils_Dev::colorText('Non prorated plan with no charge exists in cycle','Yellow'),Zend_Log::DEBUG);

		return null;
	}

	protected function getUpfrontCycle($regularCycle) {
		$nextCycleKey = Billrun_Billingcycle::getFollowingBillrunKey($regularCycle->key());
		return  new Billrun_DataTypes_CustomCycleTime($nextCycleKey, $this->recurrenceConfig, $regularCycle->invoicingDay(), $this->activation);
	}
}
