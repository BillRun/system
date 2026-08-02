<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2026 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * @package  Billapi
 */
class Models_Eventsettings extends Models_Entity {

	protected $errorCode = 90500;

	protected function init($params) {
		parent::init($params);
		$this->validateFraudRecurrence();
	}

	protected function validateFraudRecurrence() {
		$recurrence = Billrun_Util::getIn($this->update, 'recurrence', null);
		$dateRange = Billrun_Util::getIn($this->update, 'date_range', null);
		if (empty($recurrence) || empty($dateRange)) {
			return true;
		}
		$recurrenceBaseUnits = $recurrence['value'] * (($recurrence['type'] ?? '') == 'hourly' ? 60 : 1);
		$dateRangeBaseUnits = $dateRange['value'] * (($dateRange['type'] ?? '') == 'hourly' ? 60 : 1);
		if ($dateRangeBaseUnits < $recurrenceBaseUnits) {
			throw new Billrun_Exceptions_Api($this->errorCode, [], 'Event recurrence must be less than or equal to date range');
		}
		return true;
	}

}
