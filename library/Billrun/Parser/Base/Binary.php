<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing parser class for binary size
 *
 * @package  Billing
 * @since    0.5
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
abstract class Billrun_Parser_Base_Binary extends Billrun_Parser {

	protected $parsedBytes = 0;

	/**
	 * Get the amount of bytes that were parsed on the last parsing run.
	 * @return int	 containing the count of the bytes that were processed/parsed.
	 */
	public function getLastParseLength() {
		return $this->parsedBytes;
	}

	/**
	 * method to set the line of the parser
	 *
	 * @param string $line the line to set to the parser
	 * @return Object the parser itself (for concatening methods)
	 */
	public function setLine($line) {
		$this->line = $line;
		return $this;
	}

	/**
	 *
	 * @return string the line that parsed
	 */
	public function getLine($fp) {
		return $this->line;
	}

	abstract public function parseHeader($data);

	abstract public function parseTrailer($data);

	abstract public function parseField($data, $fileDesc);
	
	/**
	 * Set the amount of bytes that were parsed on the last parsing run.
	 * @param $parsedBytes	Containing the count of the bytes that were processed/parsed.
	 */
	public function setLastParseLength($record_length) {
		$this->parsedBytes = $record_length;
	}
	
}
