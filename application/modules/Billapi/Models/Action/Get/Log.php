<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi get Log operation
 * Retrieve list of entities
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Get_Log extends Models_Action_Get {
	
	protected function __construct(array $params = array()) {
		parent::__construct($params);
		Billrun_Utils_Mongo::convertQueryMongoDates($this->query);
	}
	
	protected function getDateFields() {
		$fields = parent::getDateFields();
		$fields[] = 'urt';
		return array_unique($fields);
	}

}
