<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the api logic for the services.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       5.1
 */
class ServicesAction extends ApiAction {
	use Billrun_Traits_Api_UserPermissions;
	
	/**
	 * Get the correct action to use for this request.
	 * @return Billrun_ActionManagers_Action
	 * @todo - This is a generic function should find a better place to put it.
	 */
	protected function getAction() {
		$apiName = str_replace("Action", "", __CLASS__);
		$apiManagerInput = array(
			'input' => $this->getRequest(),
			'api_name' => $apiName
		);

		$this->manager = new Billrun_ActionManagers_APIManager($apiManagerInput);

		// This is the method which is going to be executed.
		return $this->manager->getAction();
	}

	/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		$this->allowed();
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/services/conf.ini');

		$request = $this->getRequest();

		// This is the method which is going to be executed.
		$action = $this->getAction($request);

		$output = "";
		// Check that received a valid action.
		if (is_string($action)) {
			// TODO: Report failed action. What do i write to the output if this happens?
			Billrun_Factory::log("Failed to get services action instance for received input", Zend_Log::ALERT);
			$errorCode = $this->manager->getErrorCode();
			$output = array(
				'status' => $errorCode == 0 ? 1 : 0,
				'desc' => $this->manager->getError(),
				'error_code' => $errorCode,
				'details' => 'Error'
			);
		} else {

			$output = $action->execute();

			// Set the raw input.
			// For security reasons (secret code) - the input won't be send back.
			//		$output['input'] = $request->getRequest();
		}
		$this->getController()->setOutput(array($output));
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_WRITE;
	}

}
