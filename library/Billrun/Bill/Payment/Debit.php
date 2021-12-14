<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Debit class
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Bill_Payment_Debit extends Billrun_Bill_Payment {

	protected $method = 'debit';

	public function __construct($options) {
		parent::__construct($options);
		if (isset($options['billrun_key'], $options['dd_stamp'])) {
			$this->data['billrun_key'] = $options['billrun_key'];
			$this->data['dd_stamp'] = $options['dd_stamp'];
		} else {
			throw new Exception('Billrun_Bill_Payment_Debit: Insufficient options supplied.');
		}
	}

}
