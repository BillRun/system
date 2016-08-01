<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Billrun_Subscriber_Entity extends Mongodloid_Entity {
	public function __construct($values, $lastPlan = null, $collection = null) {
		if(($lastPlan === null) || (isset($values['plan']) && ($values['plan'] != $lastPlan))) {
			$values['plan_activation'] = new MongoDate();
		}
		// TODO: I Assume that if the last plan is null then we must have a plan.
		if(($lastPlan !== null) && (isset($values['plan'])) && ($values['plan'] != $lastPlan)) {
			$values['plan_deactivation'] = new MongoDate();
		}
		
		parent::__construct($values, $collection);
	}
}
