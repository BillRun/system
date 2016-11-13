<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator update row for customer pricing postpaid calc in row level
 *
 * @package     calculator
 * @subpackage  updaterow
 * @since       5.3
 */
class Billrun_Calculator_Updaterow_Customerpricing_Postpaid extends Billrun_Calculator_Updaterow_Customerpricing {

	protected function init() {
		parent::init();
	}
	
	protected function validate() {
		if (!isset($this->row['usagev'])) {
			Billrun_Factory::log("Line with stamp " . $this->row['stamp'] . " is missing volume information", Zend_Log::ALERT);
			return false;
		}
		return parent::validate();
	}
	
	public function update() {
		return parent::update();
	}
	
}