<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing payment gateways connection class
 *
 * @since    5.10
 */
abstract class Billrun_PaymentGateway_Connection_ClientServer extends Billrun_PaymentGateway_Connection {

	public function __construct($options) {
		if (
			!isset($options['connection_type']) || !isset($options['host']) || !isset($options['user']) ||
			!isset($options['password'])
		) {
			throw new Exception('Missing connection details');
		}
		parent::__construct($options);
	}

}
