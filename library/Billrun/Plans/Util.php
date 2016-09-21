<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Util class for the plans
 *
 * @package  Util
 * @since    5.1
 */
class Billrun_Plans_Util {

	/**
	 * check if a plan name exists in the system
	 * 
	 * @param type $planName
	 * @return boolean
	 */
	public static function isPlanExists($planName) {
		$query = array_merge(Billrun_Utils_Mongo::getDateBoundQuery(), array('name' => $planName));
		return $planName === 'BASE' || (Billrun_Factory::db()->plansCollection()->query($query)->cursor()->count() > 0);
	}

}
