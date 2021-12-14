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
class Billrun_Exceptions_Api extends Billrun_Exceptions_Base {

	const ERROR_CODE = 17577;

	/**
	 * List of invalid fields.
	 * @var array
	 */
	protected $apiCode = 0;

	/**
	 * Array of all api error codes.
	 * @var array
	 */
	protected $errors = array();
	protected $args = array();

	/**
	 * Create a new instance of the API exception class
	 * @param integer $apiCode - Api code to report.
	 * @param array $arguments - Arguments to be printed to the messsage.
	 * @param array $message - Message to print, using default if empty string. 
	 * Empty by default.
	 */
	public function __construct($apiCode, $arguments = array(), $message = "") {
		$exMessage = "API error.";
		if ($message) {
			$exMessage = $message;
		}
		parent::__construct($exMessage, self::ERROR_CODE);
		$this->initErrors();
		$this->apiCode = $apiCode;
		$this->args = $arguments;
	}

	/**
	 * Initialize the errors array
	 */
	protected function initErrors() {
		$iniDirectory = new RecursiveDirectoryIterator(APPLICATION_PATH . "/conf");
		$iniIterator = new RecursiveIteratorIterator($iniDirectory);
		$iniRegex = new RegexIterator($iniIterator, '/errors.ini$/i', RecursiveRegexIterator::GET_MATCH);

		$iniArray = array_keys(iterator_to_array($iniRegex));
		foreach ($iniArray as $iniFile) {
			$this->loadIniFile($iniFile);
		}
		Billrun_Factory::log("Done initializing error codes");
	}

	/**
	 * Load the contents of an error ini file
	 * @param string $iniFile
	 */
	protected function loadIniFile($iniFile) {
		// Check if exists.
		if (!file_exists($iniFile)) {
			Billrun_Factory::log("File does not exist: " . print_r($iniFile, 1));
			return;
		}

		// Read the contents
		$iniContent = parse_ini_file($iniFile);

		// Check that it has errors.
		if (!isset($iniContent['errors'])) {
			Billrun_Factory::log("Invalid ini error file: " . print_r($iniFile, 1));
			return;
		}

		$iniErrors = $iniContent['errors'];

		// Merge the results.
		$this->errors += $iniErrors;
	}

	/**
	 * Generate the array value to be displayed in the client for the exception.
	 * @return array.
	 */
	protected function generateDisplay() {
		$errorMessage = "General error.";
		if (isset($this->errors[$this->apiCode])) {
			$errorString = $this->errors[$this->apiCode];
			$errorMessage = vsprintf($errorString, $this->args);
		}
		return array("code" => $this->apiCode, "desc" => $errorMessage);
	}

}
