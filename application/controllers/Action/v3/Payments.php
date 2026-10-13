<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Payments action class in version 3
 *
 * @package  Action
 * @since    5.0
 */
class V3_paymentsAction extends ApiAction {
	
	public function execute() {
		$this->forward('payments');
		return false;
	}

	/**
	 * Same level as the payments action this forwards to.
	 */
	public static function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
