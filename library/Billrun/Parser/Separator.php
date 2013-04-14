<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing parser class for separator
 *
 * @package  Billing
 * @since    0.5
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
class Billrun_Parser_Separator extends Billrun_Parser {

	/**
	 * the structure of row
	 * 
	 * @var array
	 */
	protected $structure;

	/**
	 * the separator character
	 * 
	 * @var string 
	 */
	protected $separator = ",";

	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['separator'])) {
			$this->setSeparator((string) $options['separator']);
		}
	}

	/**
	 * method to set structure of the parsed file
	 * 
	 * @param array $structure the structure of the parsed file
	 * 
	 * @return Billrun_Parser_Fixed self instance
	 */
	public function setStructure($structure) {
		$this->structure = $structure;
		return $this;
	}

	/**
	 * method to set separator of the parsed file
	 * 
	 * @param string $separator the structure of the parsed file
	 * 
	 * @return Billrun_Parser_Fixed self instance
	 */
	public function setSeparator($separator) {
		$this->separator = $separator;
		return $this;
	}

	/**
	 * method to get separator of the parsed file
	 * 
	 * @param string $separator the structure of the parsed file
	 * 
	 * @return Billrun_Parser_Fixed self instance
	 */
	public function getSeparator() {
		return $this->separator;
	}

	/**
	 * method to parse separtor
	 * basicaly it's just attached the values array into array keys
	 * 
	 * @return mixed
	 */
	public function parse() {

		$row = array_combine($this->structure, $this->line);
		$row['stamp'] = md5(serialize($this->line));

		if ($this->return == 'array') {
			return $row;
		}
		return (object) $row;
	}

	/**
	 * method to receive the line
	 * 
	 * @return string the line that parsed
	 */
	public function getLine() {
		return $this->line;
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

}
