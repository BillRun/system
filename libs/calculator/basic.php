<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'calculator.php';

/**
 * Billing basic calculator class
 *
 * @package  calculator
 * @since    1.0
 */
abstract class calculator_basic implements calculator
{

	/**
	 * write the calculation into DB
	 */
	abstract protected function writeDB($row);

	/**
	 * identify if the row belong to calculator
	 * @return boolean true if the row identify as belonging to the calculator, else false
	 */
	protected function identify($row) {
		return true;
	}
	
	public function getInstance($type)
	{
		
	}

}