<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract parser class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class parser extends base {

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
	 * general function to parse
	 * 
	 * @return mixed
	 */
	abstract public function parse();
}