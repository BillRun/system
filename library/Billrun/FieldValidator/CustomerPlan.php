<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Field validator for the customer plan.
 *
 */
trait Billrun_FieldValidator_CustomerPlan {

	/**
	 * Validate the input plan for the subscriber
	 * @param $plan string - The plan to validate
	 * @return boolean True if valid or error string if failure.
	 * @todo change to return false on failure
	 */
	protected function validateCustomerPlan($plan) {
		// If the update doesn't affect the plan there is no reason to validate it.
		if (!isset($plan)) {
			return true;
		}
		$planName = $plan;
		$planQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$planQuery['type'] = 'customer';
		$planQuery['name'] = $planName;
		$planCollection = Billrun_Factory::db()->plansCollection();
		$currentPlan = $planCollection->query($planQuery)->cursor()->current();

		// TODO: Use the subscriber class.
		if ($currentPlan->isEmpty()) {
			$error = 'Invalid plan for the subscriber! [' . print_r($planName, true) . ']';
			return $error;
		}

		return true;
	}

}
