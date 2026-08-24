<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates a not prorated custom recurrence upfront charge - the regular (arrears) not prorated
 * custom charge of the next custom cycle, paid in advance (see Billrun_Plans_Charge_Upfront).
 *
 * @package  Plans
 * @since    5.2
 */
class Billrun_Plans_Charge_Upfront_Notprorated_Custom extends Billrun_Plans_Charge_Upfront_Notprorated_Month {

	use Billrun_Plans_Charge_Traits_Custom;

}
