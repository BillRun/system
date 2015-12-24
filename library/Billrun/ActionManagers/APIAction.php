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

	protected function __construct($params) {
		if (isset($params['error'])) {
			$this->error = $params['error'];
		}
	}
	
	/**
	 * Get the current error of the action.
	 * @return string the description for the current error.
	 */
	public function getError() {
		return $this->error;
	}
	
	/**
	 * Report an error.
	 * @param string $error - Error string to report.
	 * @param Zend_Log_Filter_Priority $errorLevel
	 */
	protected function reportError($error, $errorLevel=Zend_Log::INFO) {
		$this->error = $error;
		Billrun_Factory::log($error, $errorLevel);
	}
}
