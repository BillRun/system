<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing denials receiver for Credit Guard files.
 *
 * @package  Billing
 * @since    5.9
 */
class Billrun_Receiver_PaymentGateway_CreditGuard_Denials extends Billrun_Receiver_NonCDRs_PaymentGateway {

	static protected $type = 'CreditGuardDenials';
	protected $gatewayName = 'CreditGuard';
	protected $actionType = 'denials';

	public function __construct($options) {
		$options['type'] = self::$type;
		parent::__construct($options);
	}
}