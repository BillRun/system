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
		if(isset($this->query['urt'])){
			Billrun_Utils_Mongo::convertQueryMongoDates($this->query['urt']);
		}
	}

	protected function runQuery() {
		$records = parent::runQuery();
		foreach($records as  &$record) {
			$record = Billrun_Utils_Mongo::recursiveConvertRecordMongoDatetimeFields($record, array('urt'));
		}
		return $records;
	}

}
