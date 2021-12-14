<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Balance action class of version 3
 *
 * @package  Action
 * @since    0.5
 */
class V3_balanceAction extends ApiAction {

	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		$options = [
			'fake_cycle' => true,
			'generate_pdf' => false,
			'output' => 'invoice_meta_data',
		];
		$this->forward('generateExpected', $options);
		return false;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
