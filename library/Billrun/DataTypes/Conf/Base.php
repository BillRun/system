<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Abstract wrapper class for a complex object
 */
abstract class Billrun_DataTypes_Conf_Base {

	/**
	 * Wrapper value
	 * @var mixed
	 */
	protected $val = null;

	public abstract function validate();

	/**
	 * Get the value of the complex object
	 * @return mixed
	 */
	public function value() {
		return $this->val;
	}

}
