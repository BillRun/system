<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi subscribers model for subscribers entity
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Charges extends Models_Entity {

	/**
	 * Return the key field
	 *
	 * @return String
	 */
	protected function getKeyField() {
		return 'key';
	}

}
