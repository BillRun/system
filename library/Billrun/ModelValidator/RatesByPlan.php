<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a validator for Rate with a Plan name as a parent.
 *
 * @since 5.1
 */
class Billrun_ModelValidator_RatesByPlan extends Billrun_ModelValidator_Base {

	/**
	 * Check whether a rate object with PLAN_NAME as parent is valid
	 * 
	 * @param type $plan
	 * @param type $rate
	 * @return true on success, error message on failure
	 */
	public function validate($plan, $rate) {
		if (!Billrun_Plans_Util::isPlanExists($plan)) {
			return 'Plan "' . $plan . '" does not exist';
		}
		if (empty($rate['rate'])) {
			return 'No "rate" object found under usaget "' . $usaget . '" and plan "' . $plan . '"';
		}
		$lastInterval = 0;
		foreach ($rate['rate'] as $interval) {
			if (!isset($interval['from']) || !isset($interval['to']) || !isset($interval['price']) || !isset($interval['interval'])) {
				return 'Illegal rate structure';
			}

			$typeFields = array(
				'interval' => 'integer',
				'from' => 'integer',
//				'to' => 'integer', // TODO validate integer or "UNLIMITED"
				'price' => 'float',
			);
			$validateTypes = $this->validateTypes($interval, $typeFields);
			if ($validateTypes !== true) {
				return $validateTypes;
			}
			if ($interval['from'] != $lastInterval) {
				return 'Rate intervals must be continuous';
			}
			$lastInterval = $interval['to'];
		}
		
		return true;
	}

}
