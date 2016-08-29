<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Representing an inc update operation
 *
 * @package  Balances
 * @since    4.5
 */
class Billrun_Balances_Update_Inc extends Billrun_Balances_Update_Operation {

	/**
	 * Get the mongo operation to execute.
	 * @param mixed $valueToSet - Value to set.
	 * @return string - $inc or $set
	 */
	protected function getMongoOperation($valueToSet) {
		if(is_array($valueToSet) || is_numeric($valueToSet) !== true) {
			return '$set';
		}
		
		return '$inc';
	}

	/**
	 * Is an increment operation.
	 * @return boolean true if is increment.
	 */
	public function isIncrement() {
		return true;
	}

}
