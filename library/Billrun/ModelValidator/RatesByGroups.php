<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a validator for Rate with groups as a parent.
 *
 * @since 5.1
 */
class Billrun_ModelValidator_RatesByGroups extends Billrun_ModelValidator_Base {

	/**
	 * Check whether a rate object with "groups" as parent is valid
	 * 
	 * @param type $plan
	 * @param type $rate
	 * @return true on success, error message on failure
	 */
	public function validate($plan, $rate) {
		if (!$this->isGroupsValid($rate)) {
			return 'Invalid "groups" field: ' . print_R($rate, 1);
		}

		return true;
	}

	/**
	 * Validates "groups" value. must be an array (not associative)
	 * 
	 * @param type $groups
	 * @return boolean
	 */
	protected function isGroupsValid($groups) {
		return is_array($groups) && (empty($groups) || !Billrun_Util::isAssoc($groups));
	}

}
