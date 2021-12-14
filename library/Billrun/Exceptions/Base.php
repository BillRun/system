<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a basic exception in the billrun system
 *
 * @package  Exceptions
 * @since    5.2
 */
abstract class Billrun_Exceptions_Base extends Exception {

	use Billrun_Exceptions_InputContainer;

	/**
	 * The log level of the exception
	 * @var integer
	 */
	public $logLevel = Zend_Log::CRIT;

	/**
	 * Create a new instnace of the billrun exception base
	 * @param type $message
	 * @param type $code
	 * @param \Exception $previous
	 */
	public function __construct($message = "", $code = 0, \Exception $previous = null) {
		parent::__construct($message, $code, $previous);
	}

	/**
	 * Generate the output of the exception.
	 * @return json encoded array.
	 */
	public function output() {
		$output = array();
		$output['status'] = 0;
		$output['code'] = $this->code;
		$output['message'] = $this->message;
		$output['display'] = $this->generateDisplay();
		if ($this->input) {
			$output['input'] = $this->input;
		}

		return json_encode($output);
	}

	/**
	 * Generate the array value to be displayed in the client for the exception.
	 * @return array.
	 */
	abstract protected function generateDisplay();
}
