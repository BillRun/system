<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregatble plan
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Plan implements Billrun_Aggregator_Aggregateable {
	protected $charger;
	
	public function __construct() {
		$this->charger = new Billrun_Plans_Charge();
	}
	
	public function aggregate($planData = array()) {
		// Get the charge.
		$charges = $this->charger->charge($planData);
		$planData['charges'] = $charges;
		$result = $this->getLine($planData);
		return $result;
	}
	
	protected function getLine($planData) {
		$plan = new Billrun_Cycle_Data_Plan($planData);
		return $plan->getLine();
	}

}
