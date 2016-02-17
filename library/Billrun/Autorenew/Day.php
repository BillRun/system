<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * Class to represent the auto renew daily record.
 *
 * @author Tom Feigin
 */
class Billrun_Autorenew_Day extends Billrun_Autorenew_Record  {

	/**
	 * Get the next renew date for this recurring plan.
	 * @return Next update date.
	 */
	protected function getNextRenewDate() {
		$nextDay = strtotime("+1 day");
		return new MongoDate($nextDay);
	}

}
