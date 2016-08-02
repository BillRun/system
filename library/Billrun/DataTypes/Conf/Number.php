<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex number value object
 */
class Billrun_DataTypes_Conf_Number extends Billrun_DataTypes_Conf_Base {
	protected $range = array();
	public function __construct($obj) {
		$this->val = $obj['v'];
		if(isset($obj['Range'])) {
			$this->range['max'] = $obj['Range']['M'];
			$this->range['min'] = $obj['Range']['m'];
		}
	}
	
	public function validate() {
		if((($this->val !== 0) && (empty($this->val))) || 
			!Billrun_Util::IsIntegerValue($this->val)) {
			return false;
		}
		
		// Check if has range
		if(!empty($this->range)) {
			// Validate numeric.
			if(!Billrun_Util::IsIntegerValue($this->range['min']) || 
			   !Billrun_Util::IsIntegerValue($this->range['max'])) {
				return false;
			}
			
			// Check range.
			if(($this->val > $this->range['max']) ||
			   ($this->val < $this->range['min'])) {
				return false;
			}
		}
		
		return true;
	}
}
