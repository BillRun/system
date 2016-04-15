<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
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
		// TODO: Not using Balances model here. Should it be used? and what for?
		// There is an already existing Balances model, is this the right one?
		// This is the method which is going to be executed.
		$action = $this->getAction();

		// Check that received a valid action.
		$output = "";
		if (is_string($action)) {
			// TODO: Report failed action. What do i write to the output if this happens?
			Billrun_Factory::log("Failed to get balances action instance for received input", Zend_Log::ALERT);

			$output = array(
				'status' => 0,
				'desc' => $action,
				'details' => 'Error'
			);
		} else {
			$output = $action->execute();

			// Set the raw input.
			$output['input'] = $this->getRequest()->getRequest();
		}
		$this->getController()->setOutput(array($output));
	}

}
