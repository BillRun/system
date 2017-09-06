<?php

/**
 * @package	Billing
 * @copyright	Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license	GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * Handle the logic of an auto renew monthly record.
 *
 */
class Billrun_Autorenew_Month extends Billrun_Autorenew_Record {

	/**
	 * Get the next renew date for this recurring plan.
	 * @return Next update date.
	 */
	protected function getNextRenewDate() {
		return Billrun_Utils_Autorenew::getNextRenewDate($this->record['from']->sec);
	}

}
