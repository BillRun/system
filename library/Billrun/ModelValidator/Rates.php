<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a validator for Rate.
 *
 * @since 5.1
 */
class Billrun_ModelValidator_Rates extends Billrun_ModelValidator_Base {

	/**
	 * Validates a Rate object
	 * 
	 * @param type $data - rate object to validate
	 * @return true on success, error message on failure
	 */
	protected function validateRates($data) {
		if (empty($data['rates'])) { // No "rates" object means the rate is not valid
			return '"rates" field must be set in rate';
		}
		foreach ($data['rates'] as $usaget => $plans) {
			if (!$this->isUsagetValid($usaget)) { // If the "usaget" of the rate is not in the system, the rate is not valid
				return 'Usage type "' . $usaget . '" is not valid';
			}
			foreach ($plans as $plan => $rate) {
				$res = Billrun_ModelValidator_RatesByParentManager::validate($plan, $rate);
				if ($res !== true) { // In case one of the internal rates' objects has an invalid "parent" (PLAN_NAME/groups/...), the rate is invalid
					return $res;
				}
			}
		}

		return true;
	}

	/**
	 * Check if the usaget received is an allowed usage type in the system
	 * 
	 * @param type $usaget to validate
	 * @return boolean
	 */
	protected function isUsagetValid($usaget) {
		return in_array($usaget, Billrun_Factory::config()->getConfigValue('usage_types'));
	}

}
