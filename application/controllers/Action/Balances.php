<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * This class holds the balances logic for the subscribers.
 *
 * @package     Controllers
 * @subpackage  Action
 * @since       4.0
 */
class BalancesAction extends ApiAction {

	use Billrun_Traits_Api_UserPermissions;

	/**
	 * Get the correct action to use for this request.
	 * @return Billrun_ActionManagers_Action
	 * @todo - This is a generic function should find a better place to put it.
	 */
	protected function getAction() {
		// TODO: Maybe add this functionallity (get api name) to the basic API action?
		$apiName = str_replace("Action", "", __CLASS__);
		$apiManagerInput = array(
			'input' => $this->getRequest(),
			'api_name' => $apiName
		);

		$manager = new Billrun_ActionManagers_APIManager($apiManagerInput);

		// This is the method which is going to be executed.
		return $manager->getAction();
	}

	/**
	 * The logic to be executed when this API plugin is called.
	 * @todo: This function is very generic, same as subscribers API, should be moved
	 * to a more generic class.
	 */
	public function execute() {
		$this->allowed();

		// TODO: Not using Balances model here. Should it be used? and what for?
		// There is an already existing Balances model, is this the right one?
		// This is the method which is going to be executed.
		$action = $this->getAction();

		$output = $action->execute();

		// Set the raw input.
		$output['input'] = $this->getRequest()->getRequest();
		$this->getController()->setOutput(array($output));
	}

	protected function getPermissionLevel() {
		return Billrun_Traits_Api_IUserPermissions::PERMISSION_READ;
	}

}
