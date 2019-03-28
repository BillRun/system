<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Pay action class in version 3
 *
 * @package  Action
 * @since    5.0
 */
class V3_billAction extends ApiAction {
	
	public function execute() {
		$this->forward('bill');
		return false;
	}

}
