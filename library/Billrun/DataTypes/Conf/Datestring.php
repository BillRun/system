<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex date string value object
 */
class Billrun_DataTypes_Conf_Datestring extends Billrun_DataTypes_Conf_String {

	use Billrun_DataTypes_Conf_Range {
		validateInRange as rangeValidateValues;
	}

	public function __construct($obj) {
		parent::__construct($obj);

		// Override the string range
		$this->stringRange = array();

		$this->getRange($obj);
	}

	public function validate() {
		if (!parent::validate()) {
			return false;
		}

		if (!$this->validateRange()) {
			return false;
		}

		// Check if valid date string.
		return (strtotime($this->val) !== false);
	}

	protected function validateRangeType() {
		// Validate numeric.
		if (strtotime($this->range['min'] === false) ||
				strtotime($this->range['max'] === false)) {
			return false;
		}
		return true;
	}

	protected function validateInRange() {
		// Store temp values.
		$tempV = $this->val;
		$tempRange = $this->range;

		// Convert values.
		$this->val = strtotime($this->val);
		foreach ($this->range as $key => $value) {
			$this->range[$key] = strtotime($value);
		}

		// Validate converted values.
		$result = $this->rangeValidateValues();

		// Reset values.
		$this->val = $tempV;
		$this->range = $tempRange;

		return $result;
	}

}
