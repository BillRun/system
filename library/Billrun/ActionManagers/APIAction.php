<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the API actions.
 *
 */
abstract class Billrun_ActionManagers_APIAction {
	
	/**
	 * Base API code.
	 * @var int
	 */
	protected $baseCode;

	/**
	 * Report an error.
	 * @param int $errorCode - Error index to report.
	 * @param Zend_Log_Filter_Priority $errorLevel
	 */
	protected function reportError($errorCode, $errorLevel = Zend_Log::INFO, array $args = array()) {
		$apiCode = $this->baseCode + $errorCode;
		throw new Billrun_Exceptions_Api($apiCode);
	}

}
