<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Representing a set update operation
 *
 * @package  Balances
 * @since    4.5
 */
class Billrun_Balances_Update_Set extends Billrun_Balances_Update_Operation {

	/**
	 * Get the mongo operation to execute.
	 * @param mixed $valueToSet - Value to set.
	 * @return string - $inc or $set
	 */
	protected function getMongoOperation($valueToSet) {
		return '$set';
	}
	
	/**
	 * Is an increment operation.
	 * @return boolean true if is increment.
	 */
	public function isIncrement() {
		return false;
	}
}
