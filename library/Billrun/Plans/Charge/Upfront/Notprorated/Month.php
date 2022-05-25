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
    
    //No Refunds  for non proated upfront
    public function getRefund(Billrun_DataTypes_CycleTime $cycle, $quantity=1) {
			return null;
		}
}
