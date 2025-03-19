<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Collect action class
 *
 * @package  Action
 * 
 * @since    2.6
 */
class CollectAction extends ApiAction {
	
	use Billrun_Traits_Api_UserPermissions;

	public function execute() {
		Billrun_Factory::log()->log("Execute collect api call", Zend_Log::INFO);
		$request = $this->getRequest();
		if (RUNNING_FROM_CLI) {
			$extraParams = $this->getController()->getParameters();
			if (!empty($extraParams) && isset($extraParams['aids'])) {
				$aids = $extraParams['aids'];
			}
		} else {
			$this->allowed();
		}

		Billrun_Factory::log()->log("Processing request parameters", Zend_Log::DEBUG);
		$aids = !empty($extraParams) && isset($extraParams['aids']) ? Billrun_Util::verify_array($extraParams['aids'], 'int') : array();
		try {
			$jsonAids = $request->getPost('aids', '[]');
			$aids = array_merge($aids, json_decode($jsonAids, TRUE));
			if (!is_array($aids) || json_last_error()) {
				return $this->setError('Illegal account ids', $request->getPost());
			}
			$collection = Billrun_Factory::collection();
			Billrun_Factory::log()->log("Started collecting", Zend_Log::DEBUG);
			$result = $collection->collect($aids);
			Billrun_Factory::log()->log("Processing collector response", Zend_Log::DEBUG);
			if (RUNNING_FROM_CLI) {
				foreach ($result as $colection_state => $aids) {
					$this->getController()->addOutput("aids " . $colection_state . " : " . implode(", ", $aids));
				}
			} else {
				$this->getController()->setOutput(array(array(
						'status' => 1,
						'desc' => 'success',
						'details' => $result,
						'input' => $request->getRequest(),
				)));
			}
		} catch (Exception $e) {
			$this->setError($e->getMessage(), $request->getRequest());
		}
	}
	
	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_ADMIN;
	}

}
