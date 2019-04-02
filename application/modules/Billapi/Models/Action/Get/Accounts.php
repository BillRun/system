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
 * @since    5.3
 */
class Models_Action_Get_Accounts extends Models_Action_Get {

	protected function runQuery() {
		$this->query['type'] = 'account';
		return parent::runQuery();
	}

	protected function getCollectionName() {
		return 'subscribers';
	}
	
	protected function getCustomFieldsKey() {
		return $this->getCollectionName() . ".account";
	}
	
	protected function getDateFields() {
		$fields = parent::getDateFields();
		$fields[] = 'payment_gateway.active.generate_token_time';
		return array_unique($fields);
	}

}
