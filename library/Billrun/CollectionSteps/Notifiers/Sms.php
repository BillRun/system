<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Collect SMS Notifier
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_CollectionSteps_Notifiers_Sms extends Billrun_CollectionSteps_Notifiers_Abstract {

	protected function run() {
		error_log(__FILE__ . '(' . __FUNCTION__ . ":" . __LINE__ . ") " . "\n" . "SMS Notify not implemented yet, Task data" . " :\n" . print_r($this->task, 1) . "\n");
		return false;
	}

	protected function isResponseValid($response) {
		return $response;
	}

}
