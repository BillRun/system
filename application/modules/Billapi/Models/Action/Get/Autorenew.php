<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi get operation for Auto renew
 *
 * @package  Billapi
 * @since    5.6
 */
class Models_Action_Get_Autorenew extends Models_Action_Get {

	protected function getDateFields() {
		return array('from', 'to', 'next_renew', 'last_renew');
	}

}
