<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Field validator for the subscriber SOC.
 *
 * @author Shani
 */
trait Billrun_FieldValidator_SOC {

	/**
	 * Validate the input SOC for the subscriber
	 * @param $SOC string - The SOC to validate
	 * @return boolean
	 */
	protected function validateSOC(&$SOC) {
		// If the update doesn't affect the plan there is no reason to validate it.
		if(!isset($SOC)) {
			return TRUE;
		}
		$dataSlowness = Billrun_Factory::config()->getConfigValue('realtimeevent.data.slowness', array());
		return isset($dataSlowness[$SOC]);
	}
}
