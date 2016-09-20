<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Trait for the subscriber update and create to handle the input subscriber services.
 */
trait Billrun_ActionManagers_Subscribers_Servicehandler {
	
	/**
	 * Set the subscriber services to the update/create record.
	 * @param type $services
	 * @return array The array of services to set.
	 */
	protected function getSubscriberServices($services) {
		if(empty($services) || !is_array($services)) {
			return array();
		}
		
		$proccessedServices = array();
		foreach ($services as $current) {
			// Check that it has the name
			if(!isset($current['name'])) {
				Billrun_Factory::log("Invalid service: " . print_r($current,1));
				continue;
			}
			
			$serviceObj = new Billrun_DataTypes_Subscriberservice($current['name']);
			if(!$serviceObj->isValid()) {
				continue;
			}
			// Initialize the activation date to now.
			$serviceData = $serviceObj->getService();
			$serviceData['activation'] = strtotime("midnight");
			$proccessedServices[] = $serviceData;
		}
		
		return $proccessedServices;
	}
}
