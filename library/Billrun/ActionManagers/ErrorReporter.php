<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Trait to implement error reportimg.
 *
 */
trait Billrun_ActionManagers_ErrorReporter {

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
		$exception = new Billrun_Exceptions_Api($apiCode, $args);
		$exception->logLevel = $errorLevel;
		throw $exception;
	}
}
