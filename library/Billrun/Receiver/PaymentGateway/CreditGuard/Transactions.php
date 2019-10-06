<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing transactions receiver for Credit Guard files.
 *
 * @package  Billing
 * @since    5.9
 */
class Billrun_Receiver_PaymentGateway_CreditGuard_Transactions extends Billrun_Receiver_NonCDRs_PaymentGateway {

	static protected $type = 'CreditGuardTransactions';
	protected $gatewayName = 'CreditGuard';
	protected $actionType = 'transactions';

	public function __construct($options) {
		$options['type'] = self::$type;
		parent::__construct($options);
	}
}