<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Collect SMS Action
 *
 * @package  Billing
 * @since    5.0
 */
class Billrun_CollectionSteps_Actions_Sms implements Collect_ActionStrategy {

	public function run($task) {
		error_log(__FILE__ . '(' . __FUNCTION__ . ":" . __LINE__ . ") " . "\n" . " Run CollectSmsAction, Task data" . " :\n" . print_r($task, 1) . "\n");
	}

}
