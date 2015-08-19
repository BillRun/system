<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the action managers.
 *
 * @author tom
 */
class Billrun_Subscriber_Actions_Manager {
	
	/**
	 * This function receives input and returns a subscriber action instance after
	 * it already parsed the input into itself.
	 * @param type $input
	 * @return type Subscriber action
	 */
	public static function getSubscriberAction($input) {
		$methodInput = $input->get('method');
		if(empty($methodInput)) {
			Billrun_Factory::log("getSubscriberAction received invalid input", Zend_Log::INFO);
			return null;
		}
		$method = ucfirst(strtolower($methodInput));
		 
		$actionClass = str_replace('Manager', $method, __CLASS__);
		$action = new $actionClass();
		
		if(!$action) {
			Billrun_Factory::log("getSubscriberAction Action '$method' is invalid!", Zend_Log::INFO);
			return null;
		}
		
		/**
		 * Parse the input data.
		 */
		if(!$action->parse($input)) {
			Billrun_Factory::log("getSubscriberAction Action failed to parse input! " . print_r($input, 1), Zend_Log::INFO);
			return null;
		}
		
		return $action;
	}
}
