<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is accounts unique get
 *
 * @package  Billapi
 * @since    5.5
 */
class Models_Action_Import_Accounts extends Models_Action_Import {

	protected function getCollectionName() {
		return 'accounts';
	}
	
	protected function getEntityModel($params) {
		return new Models_Accounts($params);
	}
}
