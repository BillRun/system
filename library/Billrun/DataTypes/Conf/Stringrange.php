<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Trait to handle Range validations on complex conf values.
 */
trait Billrun_DataTypes_Conf_Stringrange {
	protected $range = array();
	protected function getRange($obj) {
		if(!isset($obj['range'])) {
			return;
		}
		
		// TODO: Should we validate max and min existing? Set defaults?
		// I prefer strictly forcing using both max and min.
		$this->range = $obj['range'];
	}
	
	protected function validateRange() {
		// Check if has range
		if(empty($this->range)) {
			return true;
		}
		
		if(!is_array($this->range)) {
			return false;
		}
		
		return in_array($this->val, $this->range);
	}
}
