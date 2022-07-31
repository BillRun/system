<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * BillRun Payment Wire Transfer class
 *
 * @package  Billrun
 * @since    5.0
 * @todo forbid payments without due date
 */
class Billrun_Bill_Payment_WireTransfer extends Billrun_Bill_Payment_Transfer {

	protected $method = 'wire_transfer';

	public function __construct($options) {
		parent::__construct($options);
		if (!isset($options['payer_name'], $options['aaddress'], $options['azip'], $options['acity'], $options['IBAN'], $options['bank_name'], $options['BIC']/*, $options['due_date']*/)) {
			throw new Exception('Billrun_Bill_Payment_WireTransfer: Insufficient options supplied.');
		}
	}

}