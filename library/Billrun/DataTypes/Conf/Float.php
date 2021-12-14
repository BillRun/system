<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Wrapper class for a complex float value object
 */
class Billrun_DataTypes_Conf_Float extends Billrun_DataTypes_Conf_Base {

	use Billrun_DataTypes_Conf_Range;

	public function __construct($obj) {
		$this->val = $obj['v'];
		$this->getRange($obj);
	}

	protected function validateRangeType() {
		if (filter_var($this->range['min'], FILTER_VALIDATE_FLOAT) === false ||
				filter_var($this->range['max'], FILTER_VALIDATE_FLOAT) === false) {
			return false;
		}
		return true;
	}

	public function validate() {
		if ((($this->val !== 0) && (empty($this->val))) ||
				filter_var($this->val, FILTER_VALIDATE_FLOAT) === false) {
			return false;
		}

		if (!$this->validateRange()) {
			return false;
		}

		return true;
	}

}
