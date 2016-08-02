<?php

class Billrun_DataTypes_Conf_Number extends Billrun_DataTypes_Conf_Base {
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
			!Billrun_Util::IsIntegerValue($this->val)) {
			return false;
		}
		
		// Check if has range
		if(!empty($this->range)) {
			Billrun_Factory::log("WE HAVE RANGE!");
			// Validate numeric.
			if(!Billrun_Util::IsIntegerValue($this->range['min']) || 
			   !Billrun_Util::IsIntegerValue($this->range['max'])) {
				return false;
			}
			
			Billrun_Factory::log("Checking range! " . "Min: " . $this->range['min'] . " Val: " . $this->val . " Max: " . $this->range['max']);
			
			// Check range.
			if(($this->val > $this->range['max']) ||
			   ($this->val < $this->range['min'])) {
				return false;
			}
		}
		
		return true;
	}
}
