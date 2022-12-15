<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates a not prorated monthly upfront charge
 *
 * @package  Plans
 * @since    5.2
 */
class Billrun_Plans_Charge_Upfront_Notprorated_Month extends Billrun_Plans_Charge_Upfront_Month {
    
	protected function getFractionOfMonth() {

		if (empty($this->deactivation) && $this->activation < $this->cycle->start()  ) {
			return 1;
		}

		// subscriber activates in the middle of the cycle and should be charged for a partial month
		if ($this->activation >= $this->cycle->start() && $this->deactivation <= $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return 1;
		}
		//subscriber deactivated  during the  current cycle
		if ($this->deactivation > $this->cycle->end() ) {
			return 1;
		}

		// subscriber activates in the middle of the cycle and should be charged for a partial month and should be charged for the next month (upfront)
		if ($this->activation >= $this->cycle->start() && $this->deactivation > $this->cycle->end()) {
			$endActivation = strtotime('-1 second', $this->deactivation);
			return 1 + 1;
		}

		//probably not within the current cycle  return null to indicate invalid charge  without affecting other charges.
		return null;
	}

    //No Refunds  for non proated upfront
    public function getRefund(Billrun_DataTypes_CycleTime $cycle, $quantity=1) {
			return null;
		}
}
