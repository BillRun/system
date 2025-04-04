<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager of Charging in Account level
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Charging_Account extends Billrun_Job_Abstract {

	protected $method = 'Charging_Account';

	public function run() {
		Billrun_Factory::log("charging account start for " . ($this->config['aid'] ?? ''));
		try {
			Billrun_Bill_Payment::makePayment($this->config);
		} catch (Throwable $th) {
			Billrun_Factory::log("Error on charging account job. " . $th->getCode() . ": " . $th->getMessage());
		} catch (Exception $ex) {
			Billrun_Factory::log("Error on charging account job. " . $ex->getCode() . ": " . $ex->getMessage());
		}
		Billrun_Factory::log("charging account end for " . ($this->config['aid'] ?? ''));
	}
}
