<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the action managers.
 *
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
	public function __construct($options = array()) {
		$this->options = $options;
	}

	/**
	 * Get the action name from the input.
	 */
	protected abstract function getActionName();

	/**
	 * Get the string that is the stump for the action class name to be constructed.
	 * @return string - String for action name.
	 */
	protected function getActionStump() {
		return __CLASS__;
	}

	/**
	 * Get the name of the action class to create.
	 * @param string $action - String to concatenate to the current class stub
	 * to create the name of the action requested.
	 */
	protected function getActionClassName($action) {
		return str_replace('_Manager', '_' . $action, $this->getActionStump());
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
	 * Validate the name of the action class that was created for the received input.
	 * @param string $actionClassName - Name of the action class to create.
	 * @return boolean true if valid.
	 */
	protected function validateActionClassName($actionClassName) {
		// Check if the class exists.
		return class_exists($actionClassName, true);
	}

	/**
	 * Get the instance of the action object to return.
	 * @param string $actionClass - Name of the class to create.
	 * @return Object - Action manager to return.
	 */
	protected function getActionInstance($actionClass) {
		// Validate the action class.
		if (!$this->validateActionClassName($actionClass)) {
			Billrun_Factory::log("getAction Action '$actionClass' is an invalid class!", Zend_Log::ERR);
			return null;
		}

		$action = $this->allocateAction($actionClass);
		if (!$action) {
			Billrun_Factory::log("getAction Action '$actionClass' is invalid!", Zend_Log::ERR);
			return null;
		}

		return $action;
	}

	/**
	 * This function receives input and returns a subscriber action instance after
	 * it already parsed the input into itself.
	 * @return Billrun_ActionManagers_Action Subscriber action
	 */
	public function getAction() {
		if (!$this->validate()) {
			Billrun_Factory::log("Action manager received invalid options", Zend_Log::ERR);
			return null;
		}

		// Get the method to create an action by.
		$method = $this->getActionName();

		// Get the name of the action class.
		$actionClass = $this->getActionClassName($method);

		// Return the action instance.
		return $this->getActionInstance($actionClass);
	}

}
