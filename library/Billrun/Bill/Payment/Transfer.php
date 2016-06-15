<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Transfer class
 *
 * @package  Billrun
 * @since    1
 */
abstract class Billrun_Bill_Payment_Transfer extends Billrun_Bill_Payment {

	public function __construct($options) {
		if (isset($options['dir'])) {
			$this->dir = $options['dir'];
		}
		else {
			throw new Exception('dir not supplied.');
		}
		parent::__construct($options);
		if (isset($options['payer_name']) && isset($options['deposit_slip_bank'])) {
			$this->data['payer_name'] = $options['payer_name'];
			$this->data['deposit_slip_bank'] = $options['deposit_slip_bank'];
		} else {
			throw new Exception('Billrun_Bill_Payment_Transfer: Insufficient options supplied.');
		}
	}

}