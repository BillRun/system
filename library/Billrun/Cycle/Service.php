<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregatble service
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Service extends Billrun_Cycle_Plan {
	protected function getLine($planData) {
		$service = new Billrun_Cycle_Data_Service($planData);
		return $service->getLine();
	}
	
	public function aggregate($planData = array()) {
		$price = $planData['price'];
		Billrun_Factory::log("Service price: " . $price);
		$charges = array("service" => $price);
		
		// Get the charge.
		$planData['charges'] = $charges;
		$result = $this->getLine($planData);
		return $result;
	}
}
