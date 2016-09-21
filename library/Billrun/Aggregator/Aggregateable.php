<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Interface for an aggregateable record
 *
 * @package  Aggregator
 * @since    5.2
 */
interface Billrun_Aggregate_Aggregateable {
	public function aggregate();
}
