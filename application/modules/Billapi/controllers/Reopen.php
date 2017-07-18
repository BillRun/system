<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/modules/Billapi/controllers/Billapi.php';

/**
 * Billapi controller for reopen expired BillRun entities
 *
 * @package  Billapi
 * @since    5.5
 */
class ReopenController extends BillapiController {

	public function init() {
		parent::init();
	}

}
