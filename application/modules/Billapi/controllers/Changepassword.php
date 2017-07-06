<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/modules/Billapi/controllers/Billapi.php';

/**
 * Billapi controller for updating BillRun entities
 *
 * @package  Billapi
 * @since    5.3
 */
class ChangepasswordController extends BillapiController {
	
	public function init() {
		parent::init();
	}

	protected function checkPermissions() {
		$request = $this->getRequest()->getRequest();
		unset($request['update']);
		$adjustedRequest = array_merge(json_decode($request['query'], true), $request);
		unset($adjustedRequest['query']);
		$emailTimeout = Billrun_Factory::config()->getConfigValue('changepassword.email.link_expire', '24 hours');
		$timeLimit = strtotime('-' . $emailTimeout);
		if ($adjustedRequest[Billrun_Utils_Security::TIMESTAMP_FIELD] < $timeLimit) {
			throw new Exception('Your password reset link has expired. Please request password reset again.');
		}
		if (Billrun_Utils_Security::validateData($adjustedRequest)) { // validation by secret
			return true;
		}
		if (!isset($this->settings['permission'])) {
			Billrun_Factory::log("No permissions settings for API call.", Zend_Log::ERR);
			throw new Exception('Error');
		}
		throw new Exception('Oops! Something went wrong. Please request password reset again.');
	}

}
