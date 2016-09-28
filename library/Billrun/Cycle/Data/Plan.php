<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the plan data to be aggregated.
 */
class Billrun_Cycle_Data_Plan implements Billrun_Cycle_Data_Line {
	use Billrun_Traits_DateSpan;
	
	protected $plan = null;
	
	public function __construct(array $options) {
		if(!isset($options['plan'])) {
			return;
		}
		
		$this->plan = $options['plan'];
		
		// TODO: Validate the service?
		
		$this->setSpan($options);
	}

	// TODO: Implement
	public function getLine() {
		
	}

}
