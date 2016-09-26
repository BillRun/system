<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the service data to be aggregated.
 */
class Billrun_Cycle_Data_Service extends Billrun_Cycle_Data_Plan {
	
	public function __construct(array $options) {
		if(!isset($options['service'])) {
			return;
		}
		
		$this->stumpLine = $options['stump_line'];
		$this->plan = $options['service'];
		$this->charges = $options['charges'];
	}
	
	/**
	 * Translate the plan values to service values.
	 * @return type
	 */
	protected function getFlatLine() {
		$flatLine = parent::getFlatLine();
		$planValue = $flatLine['plan'];
		unset($flatLine['plan']);
		$flatLine['service'] = $planValue;
		return $flatLine;
	}

}
