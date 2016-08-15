<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
trait Billrun_ActionManagers_Subscribers_Validator {
	abstract protected function getSubscriberData();
	protected $validatorData;	
	
	public function validate() {
		$this->validatorData = $this->getSubscriberData();
		if(!$this->validateOverlap()) {
			// [Subscribers error 1000]
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base");
			$this->reportError($errorCode, Zend_Log::NOTICE, array($this->validatorData['sid']));
			return false;
		}
		
		if(!$this->validateServiceprovider()) {
			// [Subscribers error 1040]
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 40;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($this->validatorData['service_provider']));
			return false;
		}
		
		if(!$this->validatePlan()) {
			// [Subscribers error 1041]
			$errorCode = Billrun_Factory::config()->getConfigValue("subscriber_error_base") + 41;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($this->validatorData['plan']));
			return false;
		}
		
		return true;
	}
	
	/**
	 * Validate that there is no overlapping record with the same SID
	 * @param type $new
	 * @return type
	 */
	protected function validateOverlap($new = true) {
		// Get overlapping query.
		$overlapQuery = Billrun_Util::getOverlappingDatesQuery($this->validatorData, $new);
		if(is_string($overlapQuery)) {
			Billrun_Factory::log("Invalid query: " . $overlapQuery);
			return false;
		}
		
		$overlap = $this->collection->query($overlapQuery)->cursor()->current();
		return $overlap->isEmpty();
	}
	
	protected function validateServiceprovider() {
		if (!isset($this->validatorData['service_provider'])) {
			return true;
		}

		$query = Billrun_Util::getDateBoundQuery();
		$query["name"] = $this->validatorData['service_provider'];
		$coll = Billrun_Factory::db()->serviceprovidersCollection();
		$serviceProvider = $coll->query($query)->cursor()->current();
		return !$serviceProvider->isEmpty();
	}
	
	protected function validatePlan() {
		if (!isset($this->validatorData['plan'])) {
			return true;
		}

		$planQuery = Billrun_Util::getDateBoundQuery();
		$planQuery["name"] = $this->validatorData['plan'];
		$planQuery["service_provider"] = $this->validatorData['service_provider'];
		$plansColl = Billrun_Factory::db()->plansCollection();
		$planEntity = $plansColl->query($planQuery)->cursor()->current();
		return !$planEntity->isEmpty();
	}
}
