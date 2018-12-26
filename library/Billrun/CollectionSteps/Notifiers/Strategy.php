<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Collect Task Strategy
 *
 * @package  Billing
 * @since    5.0
 */
interface Billrun_CollectionSteps_Notifiers_Strategy {

	public function notify();
}
