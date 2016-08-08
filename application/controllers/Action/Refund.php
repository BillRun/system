<?php

//

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Refund action class
 *
 * @package  Action
 * @since    0.5
 */
class RefundAction extends Action_Base {

	/**
	 * backward compatible method to execute the credit
	 */
	public function execute() {
		$this->forward("credit");
		return false;
	}

}
