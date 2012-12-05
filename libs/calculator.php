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
	public function load();

	/**
	 * execute the calculation process
	 */
	public function calc();

	/**
	 * execute write the calculation output into DB
	 */
	public function write();
}
