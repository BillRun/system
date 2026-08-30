<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Calculates a yearly upfront charge - the regular (arrears) yearly charge of the next cycle,
 * paid in advance (see Billrun_Plans_Charge_Upfront).
 *
 * NOTE: the upfront charge and its reconciliation are calculated by the matching arrears charge
 * class. A yearly arrears calculator (Billrun_Plans_Charge_Arrears_Year) is not implemented yet,
 * so yearly upfront plans are currently not charged (an error is logged) - defining the plan with
 * a custom recurrence (frequency 12) is the supported way to charge yearly.
 *
 * @package  Plans
 * @since    5.2
 */
class Billrun_Plans_Charge_Upfront_Year extends Billrun_Plans_Charge_Upfront {

}
