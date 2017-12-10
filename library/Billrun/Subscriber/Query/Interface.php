<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing class for subscriber query by imsi.
 *
 * @package  Billing
 * @since    4
 */
interface Billrun_Subscriber_Query_Interface {

	/**
	 * Get the query for a subscriber by received parameters.
	 * @param type $params - Params to get the subscriber query by.
	 * @return array Query to get the subscriber from the mongo.
	 */
	public function getQuery($params);
}
