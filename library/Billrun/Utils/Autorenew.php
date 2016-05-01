<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the autorenew
 *
 * @package  Util
 * @since    4.1
 */
class Billrun_Utils_Autorenew {

	public static function getNextRenewDate($from, $retMongoDate = true) {
		$from_dom = date('d', $from);
		$first_day_of_next_month = strtotime('tomorrow', strtotime('last day of this month'));
		$last_day_of_next_month = strtotime('last day of this month', $first_day_of_next_month); // this will be the last day of next month midnight
		$last_day_of_next_month_dom = date('d', $last_day_of_next_month);
		if ((int) $from_dom > (int) $last_day_of_next_month_dom) { // if next month is less than from date need to handle this
			$ret_ts = $last_day_of_next_month;
		} else {
			$ret_ts = strtotime(date('Y-m-' . $from_dom, $first_day_of_next_month));
		}

		if ($retMongoDate) {
			return new MongoDate($ret_ts);
		}
		return $ret_ts;
	}

}
