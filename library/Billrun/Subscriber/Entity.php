<?php

/**
 * 
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
class Billrun_Subscriber_Entity extends Mongodloid_Entity {

	public function __construct($values, $lastPlan = null, $services = array(), $collection = null) {
		if ((($lastPlan === null) || (isset($values['plan'])) && ($values['plan'] != $lastPlan))) {
			$values['plan_activation'] = new Mongodloid_Date();
		}

		$valuesServices = empty($values['services']) ? array() : $values['services'];
		$subscriberServices = $this->getSubscriberServices($valuesServices, $services);
		if ($subscriberServices) {
			$values['services'] = $subscriberServices;
		}

		parent::__construct($values, $collection);
	}

	protected function getSubscriberServices($services, $oldServices) {
		return $services; // temporary fix until we separate services deactivation dates
		if (!is_array($services)) {
			return array();
		}

		$serviceTime = strtotime('tomorrow midnight');
		$subscriberServices = array();

		// Get the diff
		$oldServicesNames = $this->getNames($oldServices);
		$removedServices = array_diff($oldServicesNames, $services);

		foreach ($oldServices as $service) {
			// Check if removed
			if (in_array($service['name'], $removedServices)) {
				$service['deactivation'] = $serviceTime;
			}
			$subscriberServices[] = $service;
		}

		$addedServices = array_diff($services, $oldServicesNames);
		foreach ($addedServices as $service) {
			$service['activation'] = $serviceTime;
			$subscriberServices[] = $service;
		}

		return $subscriberServices;
	}

	protected function getNames($array) {
		$names = array();

		foreach ($array as $k => $v) {
			if ($k !== "name") {
				continue;
			}

			$names[] = $v;
		}

		return $names;
	}

}
