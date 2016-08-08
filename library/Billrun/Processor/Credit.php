<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing credit processor class
 *
 * @package  Billing
 * @since    2.0
 */
class Billrun_Processor_Credit extends Billrun_Processor_Json {

	static protected $type = 'credit';

	public function processData() {
		parent::processData();
		foreach ($this->data['data'] as &$row) {
			$row['urt'] = new MongoDate($row['urt']['sec']);
		}
		return true;
	}

}
