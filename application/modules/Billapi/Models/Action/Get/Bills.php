<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billapi unique get operation
 * Retrieve list of entities while the key or name field is unique
 * This is Bills unique get
 *
 * @package  Billapi
 * @since    5.3
 */
class Models_Action_Get_Bills extends Models_Action_Get {

	/**
	 * adds data from Billrun collection to invoice get
	 */
	protected function runQuery() {
		$bills = parent::runQuery();
		$this->enrichWithBillrunData($bills);
		return $bills;
	}
	
	protected function enrichWithBillrunData(&$bills) {
		$addedFields = array('email_sent');
		$project = array_fill_keys($addedFields, 1);
		foreach ($bills as &$bill) {
			if ($bill['type'] !== 'inv') {
				continue;
			}
			$billrunData = Billrun_Billrun::getBillrunData($bill['aid'], $bill['billrun_key'], true, $project);
			foreach ($addedFields as $addedField) {
				if (isset($billrunData[$addedField])) {
					$bill[$addedField] = $billrunData[$addedField];
				}
			}
		}
	}

}
