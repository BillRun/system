<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex number value object
 */
class Billrun_DataTypes_Conf_Number extends Billrun_DataTypes_Conf_Base {
	use Billrun_DataTypes_Conf_Range;
	public function __construct($obj) {
		$this->val = $obj['v'];
		$this->getRange($obj);
	}
	
	public function validate() {
		if((($this->val !== 0) && (empty($this->val))) || 
			!Billrun_Util::IsIntegerValue($this->val)) {
			return false;
		}
		
		if(!$this->validateRange()) {
			return false;
		}
		return true;
	}
	
	protected function validateRangeType() {
		// Validate numeric.
		if(!Billrun_Util::IsIntegerValue($this->range['min']) || 
		   !Billrun_Util::IsIntegerValue($this->range['max'])) {
			return false;
		}
		return true;
	}
}
