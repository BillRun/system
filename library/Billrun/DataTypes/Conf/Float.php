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
		if(empty($this->val) || !is_float($this->val)) {
			return false;
		}
		
		// Check if has range
		if(!empty($this->range)) {
			// Validate numeric.
			if(!is_float($this->range['min']) || 
			   !is_float($this->range['max'])) {
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
