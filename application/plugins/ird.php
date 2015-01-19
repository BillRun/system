<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * IRD plugin for daily international roaming data (IRD)
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.8
 */
class irdPlugin extends Billrun_Plugin_BillrunPluginBase {

	protected $ird_daily = null;
	protected $line_type = null;

	public function beforeUpdateSubscriberBalance($balance, $row, $rate, $calculator) {
		if (isset($row['daily_ird_plan']) && $row['daily_ird_plan']) {
			$this->daily_ird = true;
		} else {
			$this->daily_ird = false;
		}
		$this->line_type = $row['type'];
	}

	/**
	 * method to override the plan group limits
	 * 
	 * @param type $rateUsageIncluded
	 * @param type $groupSelected
	 * @param type $limits
	 * @param type $plan
	 * @param type $usageType
	 * @param type $rate
	 * @param type $subscriberBalance
	 * 
	 * @todo need to verify when lines does not come in chronological order
	 */
	public function planGroupRule(&$rateUsageIncluded, &$groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if ($groupSelected != 'IRD' || $this->line_type != 'tap3') {
			return;
		}
		if ($this->daily_ird) {
			$rateUsageIncluded = 'UNLIMITED';
		} else {
			$rateUsageIncluded = FALSE; // usage is not associated with ird, let's remove it from the plan usage association
			$groupSelected = FALSE; // we will cancel the usage as group plan when set to false groupSelected
		}

	}

}
