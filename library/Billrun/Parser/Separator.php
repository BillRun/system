<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing parser class for separator
 *
 * @package  Billing
 * @since    0.5
 * @todo should make first derivative parser text and then fixed parser will inherited text parser
 */
class Billrun_Parser_Separator extends Billrun_Parser_Csv {

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
		if (isset($options['structure'])) {
			$this->setStructure($options['structure']);
		}
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
	public function parseLine($line) {

		$row = explode($this->separator, rtrim($line, "{$this->separator}\t\n\r\0\x0B"));
		if (count($this->structure) > count($row)) {
			Billrun_Factory::log('Incompatible number of fields for line ' . $line, Zend_Log::WARN);
			$row = array_merge($row, array_fill(0, count($this->structure) - count($row), FALSE));
		} else if (count($this->structure) < count($row)) {
			Billrun_Factory::log('Incompatible number of fields for line ' . $line . '. Skipping.', Zend_Log::ALERT);
			return FALSE;
		}
		$row = array_combine($this->structure, $row);
		if ($this->return == 'array') {
			return $row;
		}
		return (object) $row;
	}

}
