<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator class for SMS records received in realltime
 *
 * @package  calculator
 * @since 4.0
 */
class Billrun_Calculator_Rate_Smsrt extends Billrun_Calculator_Rate_Sms {

	static protected $type = 'smsrt';

	/**
	 * Check if a given line should be rated.
	 * @param type $row
	 * @return type
	 */
	protected function shouldLineBeRated($row) {
		return true;
	}

}
