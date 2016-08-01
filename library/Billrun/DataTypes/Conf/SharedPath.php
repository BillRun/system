<?php

class Billrun_DataTypes_Conf_SharedPath extends Billrun_DataTypes_Conf_Base {
	public function __construct($obj) {
		$path = $obj['v'];
		// Convert to shared path.
		// TODO: Yonatan! How do I convert to shared path??????
		$sharedPath = $path;
		
		$this->val = $sharedPath;
	}
	
	public function validate() {
		// TODO: Should we check here file exists???? I am not sure, it might
		// not have been created yet.
		if(empty($this->val) || !is_string($this->val)) {
			return false;
		}
		
		return true;
	}
}
