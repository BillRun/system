<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing calculator rate class
 * The class is basic rate that can evaluate record rate by different factors
 * 
 * @package  calculator
 */
class Billrun_Calculator_Rate_RoamingSgsn extends Billrun_Calculator_Rate_Sgsn {
	use Billrun_Traits_IncomingRoaming;
	
	/**
	 * @see Billrun_Calculator_Rate::getLineRate
	 */
	protected function getLineRate($row, $usage_type) {
		return $this->getRoamingLineRate($row, $usage_type);
	}
	
	/**
	 * @see Billrun_Calculator_Rate::getAdditionalProperties
	 */
	protected function getAdditionalProperties() {
		$props = parent::getAdditionalProperties();
		$props[] = 'plmn';
		return $props;
	}

}
