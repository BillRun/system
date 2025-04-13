<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Hello job
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Hello extends Billrun_Job_Abstract {

	protected function run() {
		Billrun_Factory::log("Hello from pid " . Billrun_Util::getPid());
		$nextTriggerDelay = rand(5, 15);
		Billrun_Factory::log("Hello job is going to create another hello job that will be triggered in " . $nextTriggerDelay . " seconds");
		Billrun_Jobsmanager::getInstance()->push('Hello', $this->config, null, time() + $nextTriggerDelay);
	}
}
