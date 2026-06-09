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
class Models_Action_Get_Reports extends Models_Action_Get {

	protected function runQuery() {
		$records = parent::runQuery();
		foreach ($records as &$record) {
			$this->convertConditionsMongodloidDates($record);
		}
		return $records;
	}
	
	protected function convertConditionsMongodloidDates(&$record) {
		if (empty($record['conditions'])) {
			return;
		}
		foreach ($record['conditions'] as $cond_key => $condition) {
			if(!empty($condition['value'])) {
				$converted_date = Billrun_Utils_Mongo::convertMongodloidDatesToReadable($condition['value']);
				$record['conditions'][$cond_key]['value'] = $converted_date;
			}
		}
	}
}
