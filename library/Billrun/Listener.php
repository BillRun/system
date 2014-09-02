<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract listener class
 *
 * @package  Billing
 * @since    0.5
 */
abstract class Billrun_Listener extends Billrun_Base {

	/**
	 * Type of object
	 *
	 * @var string
	 */
	static protected $type = 'listener';

	public function __construct($options = array()) {
		parent::__construct($options);
	}

	/**
	 * general function to listen
	 */
	abstract public function listen();

	abstract public function doAfterListen($data);
}
