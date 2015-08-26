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
abstract class Billrun_ActionManagers_Manager {
	
	/**
	 * Array of options to hold for the manager.
	 * @var array
	 */
	protected $options;
	
	/**
	 * Create a new instance of the manager class.
	 * @param array $options - Array to initialize the manager with.
	 */
	public function __construct($options) {
		$this->options = $options;
	}
	/**
	 * Get the action name from the input.
	 */
	protected abstract function getActionName();
	
	/**
	 * Get the name of the action class to create.
	 * @param string $action - String to concatenate to the current class stub
	 * to create the name of the action requested.
	 */
	protected function getActionClassName($action) {
		return str_replace('_Manager', $action, __CLASS__);
	}
	
	/**
	 * Validate the input options parameters.
	 * @return true if valid.
	 */
	protected function validate() {
		return $this->options;
	}
	
	/**
	 * Allocate the new action to return.
	 * @param string $actionClass - Name of the action to allocate.
	 * @return Billrun_ActionManagers_Action - Action to return.
	 */
	protected function allocateAction($actionClass) {
		return new $actionClass();
	}
	
	/**
	 * This function receives input and returns a subscriber action instance after
	 * it already parsed the input into itself.
	 * @return Billrun_ActionManagers_Action Subscriber action
	 */
	public function getAction() {
		if(!$this->validate()) {
			Billrun_Factory::log("Action manager received invalid options", Zend_Log::NOTICE);
			return null;
		}
		
		$method = $this->getActionName();
		$actionClass = $this->getActionClassName($method);
		$action = $this->allocateAction($actionClass);
		if(!$action) {
			Billrun_Factory::log("getAction Action '$method' is invalid!", Zend_Log::INFO);
			return null;
		}
		
		return $action;
	}
}
