<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing parser class for fixed size
 *
 * @package  Billing
 * @since    1.0
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
class billrun_parser_fixed extends billrun_parser {

	/**
	 *
	 * @var array the structure of the parser line
	 */
	protected $structure;

	public function setStructure($structure) {
		$this->structure = $structure;
		return $this;
	}

	/**
	 * general function to parse
	 * 
	 * @return mixed
	 */
	public function parse() {
		$pointer = 0;
		$ar_line = array();

		foreach ($this->structure as $key => $length) {
			$ar_line[$key] = trim(substr($this->line, $pointer, $length), "\n\r ");
			$pointer += $length;
		}
		$ar_line['stamp'] = md5(serialize($ar_line));

		if ($this->return == 'array') {
			return $ar_line;
		}
		return (object) $ar_line;
	}

}
