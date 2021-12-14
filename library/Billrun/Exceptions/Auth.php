<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents an API exception in the billrun system
 *
 * @package  Exceptions
 * @since    5.2
 */
class Billrun_Exceptions_Auth extends Billrun_Exceptions_Api {

	const ERROR_CODE = 17578;

	/**
	 * Generate the array value to be displayed in the client for the exception.
	 * @return array.
	 */
	protected function generateDisplay() {
		$errorMessage = "Authentication error.";
		if (isset($this->errors[$this->apiCode])) {
			$errorString = $this->errors[$this->apiCode];
			$errorMessage = vsprintf($errorString, $this->args);
		}
		return array("code" => $this->apiCode, "desc" => $errorMessage);
	}

}
