<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 4; see LICENSE.txt
 */

/**
 * Factory to create the record types.
 *
 * @author Tom Feigin
 */
class Billrun_Autorenew_Manager extends Billrun_ActionManagers_Manager {

	/** This function receives input and returns a subscriber action instance after
	 * it already parsed the input into itself.
	 * @return Billrun_Autorenew_Record
	 */
	public function getAction($options) {
		$this->options = $options;
		return parent::getAction();
	}

	/**
	 * Get the string that is the stump for the action class name to be constructed.
	 * @return string - String for action name.
	 */
	protected function getActionStump() {
		return __CLASS__;
	}

	/**
	 * Allocate the new action to return.
	 * @param string $actionClass - Name of the action to allocate.
	 * @return Billrun_ActionManagers_Action - Action to return.
	 */
	protected function allocateAction($actionClass) {
		return new $actionClass($this->options);
	}

	/**
	 * Get the action name from the input.
	 */
	protected function getActionName() {
		if (!isset($this->options['interval'])) {
			Billrun_Factory::log("getAction received invalid input", Zend_Log::INFO);
			return null;
		}

		$interval = $this->options['interval'];

		// Make sure that the type name are lower case with capital first.
		return ucfirst(strtolower($interval));
	}

}
