<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing abstract parser class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Parser extends Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "parser";

	/**
	 *
	 * @var string the line to parse 
	 */
	protected $line = '';

	/**
	 *
	 * @var string the return type of the parser (object or array)
	 */
	protected $return = 'array';

	public function __construct($options) {

		parent::__construct($options);

		if (isset($options['return'])) {
			$this->return = $options['return'];
		}
	}

	/**
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
	 * 
	 * @return mixed the parser itself (for concatening methods)
	 */
	public function setLine($line) {
		$this->line = $line;
		return $this;
	}

	/**
	 * general function to parse
	 * 
	 * @return mixed
	 */
	abstract public function parse();
}
