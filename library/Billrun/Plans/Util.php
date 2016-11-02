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
		return $planName === 'BASE' || self::isPlanExistsInDB($planName);
	}

	/**
	 * Check if the plan exists in the DB
	 * @param string $planName - Plan name to find
	 * @return boolean True if found
	 */
	protected static function isPlanExistsInDB($planName) {
		$query = Billrun_Utils_Mongo::getDateBoundQuery();
		$query['name'] = $planName;
		$plansCol = Billrun_Factory::db()->plansCollection();
		$count = $plansCol->query($query)->cursor()->count();
		return $count > 0;
	}
}
