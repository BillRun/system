<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing realtime controller class
 * Used for events in real-time
 * 
 * @package  Controller
 * @since    5.3
 */
class Realtime2Controller extends RealtimeController {

	use Billrun_Traits_Api_UserPermissions {
		allowed as allowedPermissions;
	}

	public function execute() {
		$request = $this->getRequest()->getRequest();
		Billrun_Factory::log('Realtime2 request: ' . print_r($request, 1), Zend_Log::WARN);

		header('Content-Type: application/json');
		$response = json_encode(array(
			'returnCode' => 2001,
			'msccData' => array(
				array(
					'event' => 1,
					'serviceId' => 2,
					'ratingGroup' => 100,
				),
			),
		));
		$this->setOutput(array($response, 1));
	}

	protected function allowed(array $input = array()) {
		if (Billrun_Factory::config()->getConfigValue('api.realtime2.allowed', 0)) {
			return true;
		}
		return $this->allowedPermissions($input);
	}

}
