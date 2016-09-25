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
	
	public function __construct($message = "No permissions.") {
		parent::__construct($message, self::ERROR_CODE);
	}
	
	/**
	 * Generate the array value to be displayed in the client for the exception.
	 * @return array.
	 */
	protected function generateDisplay() {
		return $this->message;
	}

}
