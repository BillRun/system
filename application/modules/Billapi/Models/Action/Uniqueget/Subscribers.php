<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is subscribers unique get
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Uniqueget_Subscribers extends Models_Action_Uniqueget {

	protected function runQuery() {
		$this->query['type'] = 'subscriber';
		return parent::runQuery();
	}

	protected function initGroup() {
		$this->group = 'sid';
	}
	
	protected function getCustomFieldsKey() {
		return $this->getCollectionName() . ".subscriber";
	}

}
