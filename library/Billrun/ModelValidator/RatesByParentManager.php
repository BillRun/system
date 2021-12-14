<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a validator for Rate with a parent.
 *
 * @since 5.1
 */
class Billrun_ModelValidator_RatesByParentManager {

	/**
	 * Validate a rates object according to it's parent in a rate object
	 * 
	 * @param type $plan
	 * @param type $rate
	 * @return true on success, error message on failure
	 */
	public static function validate($plan, $rate) {
		$rateByParentValidator = self::getRatesByParentValidator($plan);
		return $rateByParentValidator->validate($plan, $rate);
	}

	/**
	 * Gets the correct validator of an inner rates object (inside Rate object)
	 * 
	 * @param type $parent - parent type in the Rate's object
	 * @return validaotr instance
	 */
	protected static function getRatesByParentValidator($parent) {
		$validatorClassName = 'Billrun_ModelValidator_RatesBy';
		$specialRatesByParentValidators = array('groups');
		if (in_array($parent, $specialRatesByParentValidators)) {
			$validatorClassName .= ucfirst($parent);
		} else {
			$validatorClassName .= 'Plan';
		}
		return new $validatorClassName();
	}

}
