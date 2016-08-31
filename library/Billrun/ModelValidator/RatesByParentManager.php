<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a validator for Rate with a parent.
 *
 */
class Billrun_ModelValidator_RatesByParentManager extends Billrun_ModelValidator_Base {

	public static function getRatesByParentValidator($parent) {
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
