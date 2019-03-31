<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Update subscriber in BillRun's cache
 *
 * @author shai
 * @todo implement
 */
class V3_updateSubscriberAction extends ApiAction {

	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		Billrun_Factory::log()->log("Execute update subscriber api call", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest();
		$this->setSuccess(true, $request);
		return true;
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
