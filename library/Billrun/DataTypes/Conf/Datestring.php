<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex date string value object
 */
class Billrun_DataTypes_Conf_Datestring extends Billrun_DataTypes_Conf_String {
	public function validate() {
		if(!parent::validate()) {
			return false;
		}
		
		// Check if valid date string.
		return (strtotime($this->val) !== false);
	}
}
