<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Trait to handle Range validations on complex conf values.
 */
trait Billrun_DataTypes_Conf_Range {

	protected $range = array();

	protected function getRange($obj) {
		if (!isset($obj['range'], $obj['range']['max'], $obj['range']['min'])) {
			return;
		}

		// TODO: Should we validate max and min existing? Set defaults?
		// I prefer strictly forcing using both max and min.
		$this->range['max'] = $obj['range']['max'];
		$this->range['min'] = $obj['range']['min'];
	}

	protected function validateRange() {
		// Check if has range
		if (empty($this->range)) {
			return true;
		}

		if (!$this->validateRangeType()) {
			return false;
		}

		if (!$this->validateInRange()) {
			return false;
		}

		return true;
	}

	protected function validateInRange() {
		// Check range.
		if (($this->val > $this->range['max']) ||
				($this->val < $this->range['min'])) {
			return false;
		}
		return true;
	}

	protected function validateRangeType() {
		return true;
	}

}
