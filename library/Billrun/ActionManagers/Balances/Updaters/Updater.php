<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Description of Updater
 *
 * @author tom
 */
abstract class Billrun_ActionManagers_Balances_Updaters_Updater {
	
	protected $isIncrement = true;
	
	/**
	 * Create a new instance of the updater class.
	 */
	public function __construct($increment = true) {
		$this->isIncrement = $increment;
	}

	/**
	 * Get billrun subscriber instance.
	 * @param type $subscriberId If of the subscriber to load.
	 * @param type $dateRecord Array that has to and from fields for the query.
	 */
	protected function getSubscriber($subscriberId, $dateRecord) {
		// Get subscriber query.
		$subscriberQuery = $this->getSubscriberQuery($subscriberId, $dateRecord);
		
		// Get the subscriber.
		return Billrun_Factory::subscriber()->load($subscriberQuery);
	}
	
	/**
	 * Validate the service provider fields.
	 * @param type $subscriber
	 * @param type $planRecord
	 * @return boolean
	 */
	protected function validateServiceProviders($subscriber, $planRecord) {
		// Get the service provider to check that it fits the subscriber's.
		$subscriberServiceProvider = $subscriber->{'service_provider'};
		
		// Check if mismatching serivce providers.
		if($planRecord['service_provider'] != $subscriberServiceProvider) {
			$planServiceProvider = $planRecord['service_provider'];
			Billrun_Factory::log("Failed updating balance! mismatching service prociders: subscriber: $subscriberServiceProvider plan: $planServiceProvider");
			return false;
		}
		
		return true;
	}
}
