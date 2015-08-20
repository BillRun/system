<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the updaters.
 *
 * @author tom
 */
// TODO: Create abstract manager class and extend it.
class Billrun_ActionManagers_Balances_Updaters_Manager {
	
	static $updaterTranslator = 
		array('charging_plan_name'		  => 'ChargingPlan',
			  'charging_plan_external_id' => 'ChargingPlan',
			  'pp_includes_name'		  => 'Account',
			  'pp_includes_external_id'	  => 'Account',
			  'id'						  => 'Id',
			  '_id'						  => 'Id');
	
	/**
	 * This function receives filter name and returns an updater.
	 * @param type $filterName
	 * @return type Balances action
	 */
	public static function getUpdater($filterName) {
		if(!isset(self::$updaterTranslator[$filterName])) {
			// TODO: Log error!
			return false;
		}
		
		$updater = self::$updaterTranslator[$filterName];
		 
		$actionClass = str_replace('_Manager', $updater, __CLASS__);
		$action = new $actionClass();
		
		if(!$action) {
			Billrun_Factory::log("getAction Action '$updater' is invalid!", Zend_Log::INFO);
			return null;
		}
		
		/**
		 * Parse the input data.
		 */
		if(!$action->parse($filterName)) {
			Billrun_Factory::log("getAction Action failed to parse input! " . print_r($filterName, 1), Zend_Log::INFO);
			return null;
		}
		
		return $action;
	}
}
