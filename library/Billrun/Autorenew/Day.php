<?php

/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * Class to represent the auto renew daily record.
 *
 */
class Billrun_Autorenew_Day extends Billrun_Autorenew_Record {

	/**
	 * Get the next renew date for this recurring plan.
	 * @return Next update date.
	 */
	protected function getNextRenewDate() {
		$nextDay = strtotime("+1 day 00:00:00");
		return new Mongodloid_Date($nextDay);
	}

}
