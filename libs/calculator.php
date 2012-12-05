<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing interface calculator class
 *
 * @package  calculator
 * @since    1.0
 */
interface calculator
{

	/**
	 * load the data to calculate
	 */
	abstract public function load();

	/**
	 * execute the calculation process
	 */
	abstract public function calc();

	/**
	 * execute write down the calculation output
	 */
	abstract public function write();
}
