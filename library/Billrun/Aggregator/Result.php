<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Interface for an aggregate result
 *
 * @package  Aggregator
 * @since    5.2
 */
interface Billrun_Aggregator_Result {
	/**
	 * Save the results
	 */
	public function save();
}
