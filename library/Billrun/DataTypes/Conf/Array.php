<?php
/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex arra value object
 */
class Billrun_DataTypes_Conf_Array extends Billrun_DataTypes_Conf_Base {
	protected $array = array();
	
	public function __construct(&$obj) {
		$this->val = &$obj['v'];
		$this->array = &$obj['array'];
	}
	
	public function validate() {
		if (is_null($this->val)) {
			return false;
		}
		if (!is_array($this->val)) {
			$this->val = array($this->val);
		}
		$this->array = array_unique(array_merge($this->array, $this->val));
		$this->val = null;
		return true;
	}
	
	public function value() {
		return array_values($this->array);
	}

}
