<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
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
class BalancesAction extends ApiAction{
	
	/**
	 * The logic to be executed when this API plugin is called.
	 */
	public function execute() {
		$request = $this->getRequest();

		// This is the method which is going to be executed.
		$action = Billrun_Balances_Actions_Manager::getSubscriberAction($request);
		
		// Check that received a valid action.
		if(!$action) {
			// TODO: Report failed action. What do i write to the output if this happens?
			Billrun_Factory::log("Failed to get balances action instance for received input", Zend_Log::ALERT);
			return;
		}
		
		$output = $action->execute();
		
		// Set the raw input.
		$output['input'] = $request->getRequest();
		
		$this->getController()->setOutput(array($output));
	}
}
