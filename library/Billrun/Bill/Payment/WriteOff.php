<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun WriteOff Payment class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Bill_Payment_WriteOff extends Billrun_Bill_Payment {

	protected $method = 'write_off';

	public function __construct($options) {
		$this->dir = 'fc';
		parent::__construct($options);
	}

}
