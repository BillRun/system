<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents a payment gatewayde
 *
 * @since    5.2
 */
class Billrun_PaymentGateway_Stripe extends Billrun_PaymentGateway {

	protected $omnipayName = 'Stripe';

	public function getSessionTransactionId() {
		
	}

}
