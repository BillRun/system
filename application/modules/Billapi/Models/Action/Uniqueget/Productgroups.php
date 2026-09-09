<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key field is unique
 * This is products groups unique get
 *
 * @package  Billapi
 * @since    5.15
 */
class Models_Action_Uniqueget_Productgroups extends Models_Action_Uniqueget {

	protected function initGroup() {
		$this->group = 'key';
	}
}
