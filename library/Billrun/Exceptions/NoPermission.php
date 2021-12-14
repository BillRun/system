<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a no permissions exception in the billrun system
 *
 * @package  Exceptions
 * @since    5.2
 */
class Billrun_Exceptions_NoPermission extends Billrun_Exceptions_Base {

	const ERROR_CODE = 17575;
	const ERROR_CODE_USER_LOGOUT = 17574;

	public function __construct($message = "No permissions.") {
		if ($this->userLoggedin()) {
			parent::__construct($message, self::ERROR_CODE);
		} else {
			$this->logLevel = Zend_Log::INFO;
			parent::__construct('No user login', self::ERROR_CODE_USER_LOGOUT);
		}
	}

	/**
	 * Generate the array value to be displayed in the client for the exception.
	 * @return array.
	 */
	protected function generateDisplay() {
		return $this->message;
	}

	/**
	 * method to check is user logged-in
	 * @return true if user logged-in else false
	 */
	protected function userLoggedin() {
		$user = Billrun_Factory::user();
		if (!$user || !$user->valid()) {
			Billrun_Factory::log("Failed to get billrun user", Zend_Log::INFO);
			return false;
		}
		return true;
	}

}
