<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class holds billrun cycle start and end times.
 * 
 * @package  DataTypes
 * @since    5.2
 */
class Billrun_DataTypes_CustomCycleTime extends Billrun_DataTypes_CycleTime {

	/**
	 * Create a new instance of the cycle time class.
	 * @param string $billrunKey - Billrun key to set the cycle times by.
	 */
	public function __construct($billrunKey, $recurrenceConfig, $invoicing_day = null, $activationDate = null) {
		$this->key = $billrunKey;
		$this->invoicing_day = $invoicing_day;
		$recurrenceOffset = Billrun_Utils_Cycle::getRecurrenceOffset($recurrenceConfig, $billrunKey, $activationDate);
		$startCycleKey = Billrun_Utils_Cycle::substractMonthsFromCycleKey($billrunKey, $recurrenceOffset ? $recurrenceOffset - 1 : $recurrenceConfig['frequency'] - 1);
		$this->start = Billrun_Billingcycle::getStartTime($startCycleKey, $invoicing_day);
		$endCycleKey = Billrun_Utils_Cycle::addMonthsToCycleKey($billrunKey, ($recurrenceOffset ? $recurrenceConfig['frequency'] - $recurrenceOffset : 0));
		$this->end = Billrun_Billingcycle::getEndTime($endCycleKey, $invoicing_day);
	}

}
