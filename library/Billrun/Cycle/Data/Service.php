<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This class represents the service data to be aggregated.
 */
class Billrun_Cycle_Data_Service implements Billrun_Cycle_Data_Line {
	use Billrun_Traits_DateSpan;
	
	protected $service = null;
	
	public function __construct(array $options) {
		if(!isset($options['service'])) {
			return;
		}
		
		$this->service = $options['service'];
		
		// TODO: Validate the service?
		
		$this->setSpan($options);
	}
	
	public function getLine() {
		// TODO: Implement
	}

}
