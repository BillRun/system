<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Billrun_Subscriber_Entity extends Mongodloid_Entity {
	public function __construct($values, $lastPlan = null, $services = array(), $collection = null) {
		if((($lastPlan === null) || (isset($values['plan'])) && ($values['plan'] != $lastPlan))) {
			$values['plan_activation'] = new MongoDate();
		}
		
		// Services time
		$serviceTime = strtotime("midnight");
		
		if(is_array($values['services'])) {
			// Get the diff
			$addedServices = array_diff($services, $values['services']);
			$removedServices = array_diff($values['services'], $services);
			
			foreach ($removedServices as $removed) {
				$values['services'][$removed['name']]['deactivation'] = $serviceTime;
			}
			
			// Go through the diffs.
			foreach ($addedServices as $added) {
				$values['services'][$added['name']]['activation'] = $serviceTime;
			}
		}
		
		parent::__construct($values, $collection);
	}
}
