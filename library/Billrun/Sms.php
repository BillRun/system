<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Legacy class
 *
 * @package  Sms
 * @since    5.13
 * 
 */
class Billrun_Sms extends Billrun_Sms_Abstract {

	/**
	 * mockup method
	 * @return mixed
	 */
	public function send() {
		return true;
	}

}
