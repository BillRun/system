<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Credit class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Bill_Payment_Credit extends Billrun_Bill_Payment {

	protected $method = 'credit';

	public function __construct($options) {
		parent::__construct($options);	
	}
}