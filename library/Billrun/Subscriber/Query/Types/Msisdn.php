<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing class for subscriber query by msisdn.
 *
 * @package  Billing
 * @since    4
 */
class Billrun_Subscriber_Query_Types_Msisdn extends Billrun_Subscriber_Query_Base {

	/**
	 * get the field name in the parameters and the field name to set in the query.
	 * @return array - Key is the field name in the parameters and value is the field
	 * name in the query.
	 */
	protected function getKeyFields() {
		return array('msisdn' => 'msisdn');
	}

}
