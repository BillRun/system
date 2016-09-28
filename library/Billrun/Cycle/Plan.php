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
	public function aggregate($planData) {
		$plan = new Billrun_Cycle_Data_Plan($planData);
		return $plan->getLine();
	}

}
