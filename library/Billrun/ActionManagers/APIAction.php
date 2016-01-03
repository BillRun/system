<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Helper class to manage the API actions.
 *
 * @author Tom Feigin
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
		$this->errors = Billrun_Factory::config()->getConfigValue('errors', array());
	}
	
	/**
	 * Get the current error of the action.
	 * @return string the description for the current error.
	 */
	public function getError() {
		return $this->error;
	}
	
	public function getErrorCode() {
		return $this->errorCode;
	}

	
	/**
	 * Report an error.
	 * @param int $errorCode - Error index to report.
	 * @param Zend_Log_Filter_Priority $errorLevel
	 */
	protected function reportError($errorCode, $errorLevel=Zend_Log::INFO, array $args = array()) {
		if (!is_numeric($errorCode)) {
			$this->error = $errorCode;
			$this->errorCode = -1;
		} else {
			if (isset($this->errors[$errorCode])) {
				$this->error = $this->errors[$errorCode];
				
				if(!empty($args)) {
					$this->error=vsprintf($this->error, $args);
				}
				
				$this->errorCode = $errorCode;
			} else {
				$this->error = 'Unknown issue';
				$this->errorCode = 999999;
			}
		}
		Billrun_Factory::log($this->errorCode . ": " . $this->error, $errorLevel);
	}
	
}
