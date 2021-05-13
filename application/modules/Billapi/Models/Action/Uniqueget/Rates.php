<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi get Reports operation
 * Retrieve list of entities
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Uniqueget_Rates extends Models_Action_Uniqueget {

	use Models_Config;

	public function getConfigParams($params) {
		return $this->getRatesConfigParams($params);
	}
}
