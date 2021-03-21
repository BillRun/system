<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi balances model for rates entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Reports extends Models_Entity {

	public static function isAllowedChangeDuringClosedCycle() {
		return true;
	}
}