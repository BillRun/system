<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex menu value object
 */
class Billrun_DataTypes_Conf_Menu extends Billrun_DataTypes_Conf_Base {
	
	public function __construct($obj) {
		$this->val = $obj['v'];
	}
	
	public function validate() {
		// TODO: Add validations.
		return true;
	}
}
