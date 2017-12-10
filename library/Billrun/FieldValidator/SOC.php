<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Field validator for the subscriber SOC.
 *
 */
trait Billrun_FieldValidator_SOC {

	/**
	 * Validate the input SOC for the subscriber
	 * @param $SOC string - The SOC to validate
	 * @return boolean
	 */
	protected function validateSOC($SOC) {
		$dataSlowness = Billrun_Factory::config()->getConfigValue('realtimeevent.data.slowness', array());
		return isset($dataSlowness['bandwidth_cap'][$SOC]);
	}

}
