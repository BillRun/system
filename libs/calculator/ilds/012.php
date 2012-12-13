<?php

/**
 * @package			Billing
 * @copyright		Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */
require_once LIBS_PATH . DIRECTORY_SEPARATOR . 'calculator' . DIRECTORY_SEPARATOR . 'ilds.php';

/**
 * Billing calculator class for 012 ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class calculator_ilds_012 extends calculator_ilds
{

	/**
	 * the type of the calculator
	 * @var string
	 */
	protected $type = '012';

	protected function calcChargeLine($charge)
	{
		return round($charge / 1000, 3);
	}

}