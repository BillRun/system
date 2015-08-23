<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the action managers.
 *
 * @author tom
 */
// TODO: Create abstract manager class and extend it.
class Billrun_ActionManagers_Balances_Manager {
	
	/**
	 * This function receives input and returns a balances action instance after
	 * it already parsed the input into itself.
	 * @param type $input
	 * @return type Balances action
	 */
	public static function getAction($input) {
		$methodInput = $input->get('method');
		if(empty($methodInput)) {
			Billrun_Factory::log("getAction received invalid input", Zend_Log::INFO);
			return null;
		}
		$method = '_' . ucfirst(strtolower($methodInput));
		 
		$actionClass = str_replace('_Manager', $method, __CLASS__);
		$action = new $actionClass();
		
		if(!$action) {
			Billrun_Factory::log("getAction Action '$method' is invalid!", Zend_Log::INFO);
			return null;
		}
		
		/**
		 * Parse the input data.
		 */
		if(!$action->parse($input)) {
			Billrun_Factory::log("getAction Action failed to parse input! " . print_r($input, 1), Zend_Log::INFO);
			return null;
		}
		
		return $action;
	}
}
