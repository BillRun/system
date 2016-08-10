<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Billrun_Subscriber_Entity extends Mongodloid_Entity {
	public function __construct($values, $lastPlan = null, $collection = null) {
		if(($lastPlan === null) || (isset($values['plan']) && ($values['plan'] != $lastPlan))) {
			$values['plan_activation'] = new MongoDate();
		}
		
		parent::__construct($values, $collection);
	}
}
