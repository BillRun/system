<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a validator for Rate.
 *
 */
class Billrun_ModelValidator_Rates extends Billrun_ModelValidator_Base {

	protected function validateRates($data) {
		if (empty($data['rates'])) {
			return '"rates" field must be set in rate';
		}
		foreach ($data['rates'] as $usaget => $plans) {
			if (!$this->isUsagetValid($usaget)) {
				return 'Usage type "' . $usaget . '" is not valid';
			}
			foreach ($plans as $plan => $rate) {
				$rateByParentValidator = Billrun_ModelValidator_RatesByParentManager::getRatesByParentValidator($plan);
				$res = $rateByParentValidator->validate($plan, $rate);
				if ($res !== true) {
					return $res;
				}
			}
		}

		return true;
	}

	protected function isUsagetValid($usaget) {
		return in_array($usaget, Billrun_Factory::config()->getConfigValue('usage_types'));
	}

	protected function isGroupsValid($groups) {
		return is_array($groups) && (empty($groups) || !Billrun_Util::isAssoc($groups));
	}

}
