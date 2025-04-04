<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Job Manager of Confirming in Account level
 *
 * @package  Job Manager
 * @since    5.16
 */
class Billrun_Job_Confirm_Account extends Billrun_Job_Abstract {

	protected $method = 'Confirm_Account';

	public function run() {
		Billrun_Factory::log("confirm cycle start for " . ($this->config['aid'] ?? ''));
		try {
			$options = [
				'type' => 'billrunToBill',
				'invoices' => $this->config['invoices'] ?? "0",
				'stamp' => $this->config['stamp'],
			];
			$generator = Billrun_Generator::getInstance($options);
			$generator->load();
			$generator->generate();
		} catch (Throwable $th) {
			Billrun_Factory::log("Error on confirming account job. " . $th->getCode() . ": " . $th->getMessage());
		} catch (Exception $ex) {
			Billrun_Factory::log("Error on confirming account job. " . $ex->getCode() . ": " . $ex->getMessage());
		}
		Billrun_Factory::log("confirm cycle end for " . ($this->config['aid'] ?? ''));
	}
}
