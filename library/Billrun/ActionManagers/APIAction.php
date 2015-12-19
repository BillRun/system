<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the API actions.
 *
 * @author tom
 */
abstract class Billrun_ActionManagers_APIAction {
	
	/**
	 * This members holds the error message to be reported.
	 */
	protected $error = "Successful";
	protected $errorCode = 0;
	protected $errors = array();

	protected function __construct($params) {
		if (isset($params['error'])) {
			$this->error = $params['error'];
		}
		$this->errors = $errors = Billrun_Factory::config()->getConfigValue('errors', array());
	}
	
	/**
	 * Get the current error of the action.
	 * @return string the description for the current error.
	 */
	public function getError() {
		return $this->error;
	}
	
	protected function getErrorCode() {
		return $this->errorCode;
	}

	
	/**
	 * Report an error.
	 * @param string $error - Error string to report.
	 * @param int $errorCode - Code
	 * @param Zend_Log_Filter_Priority $errorLevel
	 */
	protected function reportError($error, $errorLevel=Zend_Log::INFO) {
		if (is_numeric($error)) {
			if (isset($this->errors[$error])) {
				$this->error = $this->errors[$error];
				$this->errorCode = $error;
			} else {
				$this->error = 'Unknown issue';
				$this->errorCode = 999999;
			}
		} else {
			$this->error = $error;
			$this->errorCode = -1;
		}
		Billrun_Factory::log($this->errorCode . " " . $this->error, $errorLevel);
	}
	
}
