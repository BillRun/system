<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
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
		$row = str_getcsv($line, $this->separator);
		if (count($this->structure) > count($row)) {
			Billrun_Factory::log('Incompatible number of fields for line ' . $line, Zend_Log::WARN);
			$row = array_merge($row, array_fill(0, count($this->structure) - count($row), FALSE));
		} else if (count($this->structure) < count($row)) {
			Billrun_Factory::log('Incompatible number of fields for line ' . $line . '. Skipping.', Zend_Log::ALERT);
			return FALSE;
		}
		$keys = array_column($this->structure, 'name');
		$row = array_combine($keys, $row);
		if ($this->return == 'array') {
			return $row;
		}
		return (object) $row;
	}

}
