<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing parser class for fixed size
 *
 * @package  Billing
 * @since    0.5
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
class Billrun_Parser_Fixed extends Billrun_Parser_Csv {

	/**
	 * general method to parse
	 *
	 * @return mixed
	 */
	public function parseLine($line) {
		$pointer = 0;
		$ar_line = array();

		if (array_sum($this->structure) > strlen(trim($line, "\n\r"))) {
			Billrun_Factory::log('Incompatible number of fields for line ' . $line, Zend_Log::WARN);
			return FALSE;
		}
		foreach ($this->structure as $key => $length) {
			$ar_line[$key] = trim(substr($line, $pointer, $length), "\n\r ");
			$pointer += $length;
		}
		return $ar_line;
	}

		/**
	 * 
	 * @param resource $fileHandler
	 */
	public function setFileHandler($fileHandler) {
		$this->fileHandler = $fileHandler;
	}
	
}
