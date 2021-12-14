<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Representing a default update operation
 *
 * @package  Balances
 * @since    4.5
 */
class Billrun_Balances_Update_Default extends Billrun_Balances_Update_Inc {

	/**
	 * Reconfigure the updater operation with a record
	 * @param array $record - Record to use for reconfiguring the operation.
	 * @param boolean $verbose - If true, return both the instance and a boolean 
	 * indicator for change, array of "changed"=> boolean and "instance" => object.
	 * @return Billrun_Balances_Update_Operation | boolean - A reconfigured operation 
	 * instance or this if cannot reconfigure, false on error.
	 */
	public function reconfigure($record, $verbose = false) {
		$options = array();
		$options['zero'] = $this->ignoreOveruse;
		$options['recurring'] = $this->recurring;

		$result = Billrun_Balances_Util::getOperation($record, $options);

		if ($verbose) {
			return array("changed" => true, "instance" => $result);
		}

		return $result;
	}

}
