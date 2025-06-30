<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2025 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */


/**
 * Roaming Packages plugin for roaming packages.
 *
 * @package  Application
 * @subpackage Plugins
 * @since    2.8
 */
class nonBillablePackagesPlugin extends Billrun_Plugin_BillrunPluginBase {

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
	 */
	public function planGroupRule(&$rateUsageIncluded, &$groupSelected, $limits, $plan, $usageType, $rate, $subscriberBalance) {
		if ( empty($limits['no_billable_affects']) ) {
			return;
		}
		//Billing  does allow  for  nono billable packages
		if(!empty($limits['no_billable_affects'])) {
			$groupSelected=FALSE;
		}
	}


}
