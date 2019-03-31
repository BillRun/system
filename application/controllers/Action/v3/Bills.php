<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

class V3_paymentHistoryAction extends ApiAction {

	public function execute() {
		$this->forward('paymenthistory');
		return false;
	}
}