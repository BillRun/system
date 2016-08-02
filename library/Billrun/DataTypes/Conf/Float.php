<?php

class Billrun_DataTypes_Conf_Float extends Billrun_DataTypes_Conf_Base {
	protected $range = array();
	public function __construct($obj) {
		$this->val = $obj['v'];
		if(isset($obj['Range'])) {
			$range['max'] = $obj['Range']['M'];
			$range['min'] = $obj['Range']['m'];
		}
	}
	
	public function validate() {
		if((($this->val !== 0) && (empty($this->val))) || 
			!filter_var($this->val, FILTER_VALIDATE_FLOAT)) {
			return false;
		}
		
		// Check if has range
		if(!empty($this->range)) {
			// Validate numeric.
			if(!filter_var($this->range['min'], FILTER_VALIDATE_FLOAT) || 
			   !filter_var($this->range['max'], FILTER_VALIDATE_FLOAT)) {
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
