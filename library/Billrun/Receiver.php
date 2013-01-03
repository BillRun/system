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
	 * the receiver workspace path of files
	 * @var string directory path
	 */
	protected $workspace;

	/**
	 * general function to receive
	 *
	 * @return mixed
	 */
	abstract public function receive();
}
