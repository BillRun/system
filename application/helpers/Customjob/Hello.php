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
class Customjob_Hello extends Billrun_Job_Abstract {

	protected function run() {
		Billrun_Factory::log("Hello from pid " . Billrun_Util::getPid());
		if (!empty($this->config['sleep'])) {
			$sleep = (int) $this->config['sleep'];
			Billrun_Factory::log("Hello job is going to sleep for " . $sleep . " seconds");
			sleep($sleep);
		}
		Billrun_Factory::log("Bye bye from pid " . Billrun_Util::getPid());
	}
	
	protected function finished() {
		$nextTriggerDelay = rand(5, 15);
		Billrun_Factory::log("Hello job is going to create another hello job that will be triggered in " . $nextTriggerDelay . " seconds");
		Billrun_Jobsmanager::getInstance()->push('Hello', $this->config, null, time() + $nextTriggerDelay);
		return true;
	}
}
