<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the updaters.
 *
 * @author tom
 */
// TODO: Create abstract manager class and extend it.
class Billrun_ActionManagers_Balances_Updaters_Manager extends Billrun_ActionManagers_Manager {
	
	static $updaterTranslator = 
		array('charging_plan_name'		  => 'ChargingPlan',
			  'charging_plan_external_id' => 'ChargingPlan',
			  'pp_includes_name'		  => 'PrepaidInclude',
			  'pp_includes_external_id'	  => 'PrepaidInclude',
			  'id'						  => 'Id',
			  '_id'						  => 'Id',
			  'secret'					  => 'Secret');
	
	/**
	 * This function receives filter name and returns an updater.
	 * @return type Balances action
	 */
	public function getAction() {
		$action = parent::getAction();
		
		$filterName = $this->options['filter_name'];
		
		/**
		 * Parse the input data.
		 */
		if(!$action->parse($filterName)) {
			Billrun_Factory::log("getAction Action failed to parse input! " . 
								  print_r($filterName, 1), Zend_Log::INFO);
			return null;
		}
		
		return $action;
	}
	
	/**
	 * Allocate the new action to return.
	 * @param string $actionClass - Name of the action to allocate.
	 * @return Billrun_ActionManagers_Action - Action to return.
	 */
	protected function allocateAction($actionClass) {
		return new $actionClass($this->options['options']);
	}
	
	/**
	 * Validate the input options parameters.
	 * @return true if valid.
	 */
	protected function validate() {
		// Validate that received all required paramters.
		if(!parent::validate() || 
		   !isset($this->options['options'])		  || 
		   !isset($this->options['filter_name'])) {
			return false;
		}
		
		$filterName = $this->options['filter_name'];
		
		// Check that the filter name is correct.
		if(!isset(self::$updaterTranslator[$filterName])) {
			Billrun_Factory::log("Filter name " . 
								 print_r($filterName,1) . 
								 " not found in translator!", Zend_Log::NOTICE);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get the name of the action class to create.
	 * @param string $action - String to concatenate to the current class stub
	 * to create the name of the action requested.
	 */
	protected function getActionClassName($action) {
		return str_replace('_Manager', $action, __CLASS__);
	}
	
	/**
	 * Get the action name from the input.
	 * @param array $options - Array of options containing: 
	 *						   'input' : The input to parse for the action.
	 */
	protected function getActionName($options) {
		$filterName = $options['filter_name'];
		
		if(!isset(self::$updaterTranslator[$filterName])) {
			Billrun_Factory::log("Filter name not found in translator!", Zend_Log::NOTICE);
			return false;
		}
		
		return self::$updaterTranslator[$filterName];
	}

}
