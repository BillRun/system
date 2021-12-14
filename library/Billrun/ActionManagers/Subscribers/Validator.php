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
		if (!$this->validateOverlap()) {
			// [Subscribers error 1000]
			$errorCode = 0;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($this->validatorData['sid']));
			return false;
		}

		if (!$this->validateServiceprovider()) {
			// [Subscribers error 1040]
			$errorCode = 40;
			$this->reportError($errorCode, Zend_Log::NOTICE, array($this->validatorData['service_provider']));
			return false;
		}

		if (!$this->validatePlan()) {
			// [Subscribers error 1041]
			$errorCode = 41;
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
		$overlapQuery = Billrun_Utils_Mongo::getOverlappingDatesQuery($this->validatorData, $new);
//		Billrun_Factory::log(print_r($overlapQuery,1));
		if (is_string($overlapQuery)) {
			throw new Exception($overlapQuery);
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

		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query["name"] = $this->validatorData['service_provider'];
		$coll = Billrun_Factory::db()->serviceprovidersCollection();
		$serviceProvider = $coll->query($query)->cursor()->current();
		return !$serviceProvider->isEmpty();
	}

	protected function validatePlan() {
		if (!isset($this->validatorData['plan'])) {
			return true;
		}

		$planQuery = Billrun_Utils_Mongo::getDateBoundQuery();
		$planQuery["name"] = $this->validatorData['plan'];
		if (isset($this->validatorData['service_provider'])) {
			$planQuery["service_provider"] = $this->validatorData['service_provider'];
		}
		$plansColl = Billrun_Factory::db()->plansCollection();
		$planEntity = $plansColl->query($planQuery)->cursor()->current();
		return !$planEntity->isEmpty();
	}

}
