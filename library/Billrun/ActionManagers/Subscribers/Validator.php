<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
trait Billrun_ActionManagers_Subscribers_Validator {
	abstract protected function getSubscriberData();
		
	public function validate() {
		if(!$this->validateOverlap()) {
			return false;
		}
		
		if(!$this->validateServiceprovider()) {
			return false;
		}
		
		if(!$this->validatePlan()) {
			return false;
		}
		
		return true;
	}
	
	protected function validateOverlap() {
		$data = $this->getSubscriberData();
		
		// Get overlapping query.
		$overlapQuery = Billrun_Util::getOverlappingDatesQuery($data);
		$overlap = $this->collection->query($overlapQuery)->cursor()->current();
		return $overlap->isEmpty();
	}
	
	protected function validateServiceprovider() {
		$data = $this->getSubscriberData();
		if (!isset($data['service_provider'])) {
			return true;
		}

		$query = Billrun_Util::getDateBoundQuery();
		$query["name"] = $data['service_provider'];
		$coll = Billrun_Factory::db()->serviceprovidersCollection();
		$serviceProvider = $coll->query($query)->cursor()->current();
		return !$serviceProvider->isEmpty();
	}
	
	protected function validatePlan() {
		$data = $this->getSubscriberData();
		if (!isset($data['plan'])) {
			return true;
		}

		$planQuery = Billrun_Util::getDateBoundQuery();
		$planQuery["name"] = $data['plan'];
		$planQuery["service_provider"] = $data['service_provider'];
		$plansColl = Billrun_Factory::db()->plansCollection();
		$planEntity = $plansColl->query($planQuery)->cursor()->current();
		return !$planEntity->isEmpty();
	}
}
