<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing abstract receiver class
 *
 * @package  Billing
 * @since    1.0
 */
abstract class Billrun_Receiver extends Billrun_Base {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'receiver';

	/**
	 * the receiver workspace path of files
	 * this is the place where the files will be received
	 * 
	 * @var string
	 */
	protected $workspace;

	/**
	 * general function to receive
	 *
	 * @return array list of files received
	 */
	abstract public function receive();
}
