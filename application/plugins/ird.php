<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
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
		if (isset($row['daily_ird']) || $row['daily_ird']) {
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
	public function triggerPlanGroupRateRule(&$rateUsageIncluded, $groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if ($groupSelected != 'IRD' || $this->line_type != 'tap3') {
			return;
		}
		if ($this->daily_ird) {
			$rateUsageIncluded = 'UNLIMITED';
		}
	}

}
