<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex string value object
 */
class Billrun_DataTypes_Conf_String extends Billrun_DataTypes_Conf_Base {
	use Billrun_DataTypes_Conf_Stringrange;
	protected $reg = "";
	public function __construct($obj) {
		$this->val = $obj['v'];
		if(isset($obj['re'])) {
			$this->reg = $obj['re'];
		}
		$this->getStringRange($obj);
	}
	
	public function validate() {
		if(empty($this->val) || !is_string($this->val)) {
			return false;
		}
		
		// Check if has reg ex
		if(!empty($this->reg)) {
			// Validate regex.
			// http://stackoverflow.com/questions/4440626/how-can-i-validate-regex
			if(!is_string($this->reg) || (@preg_match($this->reg, null) === false)) {
				return false;
			}
			
			// Validate the regex
			return (preg_match($this->reg, $this->val) === 1);
		}
		
		if(!$this->validateStringRange()) {
			return false;
		}
		
		return true;
	}
}
